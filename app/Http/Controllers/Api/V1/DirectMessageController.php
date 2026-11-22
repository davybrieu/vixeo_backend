<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Models\Profile;
use App\Models\PushDevice;
use App\Services\Push\PushDevices;
use App\Services\Push\PushMessage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DirectMessageController extends Controller
{
    private function activeProfile(Request $request): Profile
    {
        $profile = ResolveProfile::profile($request);
        abort_unless($profile instanceof Profile, 422, 'Sélectionnez un profil.');
        abort_if($profile->is_kid || $profile->username === null, 403, 'La messagerie est réservée aux profils adultes.');
        return $profile;
    }

    private function members(int $conversationId): array
    {
        return DB::table('direct_conversation_members')->where('conversation_id', $conversationId)
            ->whereNull('left_at')->pluck('profile_id')->map(fn ($id) => (int) $id)->all();
    }

    private function conversation(Request $request, int $id): array
    {
        $profile = $this->activeProfile($request);
        $conversation = DB::table('direct_conversations')->where('id', $id)->first();
        abort_unless($conversation && in_array((int) $profile->id, $this->members($id), true), 404);
        abort_if($conversation->status === 'rejected', 404);
        $otherIds = array_values(array_filter($this->members($id), fn ($memberId) => $memberId !== (int) $profile->id));
        $others = Profile::query()->whereIn('id', $otherIds)->where('is_kid', false)->get(['id', 'username', 'name', 'avatar']);
        abort_if($others->count() !== count($otherIds), 404);
        return [$profile, $conversation, $others];
    }

    private function blocked(int $a, int $b): bool
    {
        return DB::table('direct_message_blocks')->where(function ($q) use ($a, $b) {
            $q->where('blocker_profile_id', $a)->where('blocked_profile_id', $b);
        })->orWhere(function ($q) use ($a, $b) {
            $q->where('blocker_profile_id', $b)->where('blocked_profile_id', $a);
        })->exists();
    }

    private function notify(array $profileIds, Profile $sender, int $conversationId, string $body, bool $request = false): void
    {
        $devices = PushDevice::query()->whereIn('profile_id', array_values(array_diff($profileIds, [(int) $sender->id])))
            ->where('social', true)->get();
        if ($devices->isEmpty()) return;
        app(PushDevices::class)->send($devices, new PushMessage(
            title: $request ? 'Nouvelle demande de message' : $sender->name,
            body: mb_strimwidth($body, 0, 120, '…'),
            path: '/messages/'.$conversationId,
            channel: PushMessage::SOCIAL,
            tag: 'message-'.$conversationId,
        ));
    }

    public function index(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $ids = DB::table('direct_conversation_members')->where('profile_id', $profile->id)->whereNull('left_at')->pluck('conversation_id');
        $rows = DB::table('direct_conversations')->whereIn('id', $ids)
            ->where('status', '!=', 'rejected')->orderByDesc('last_message_at')->orderByDesc('id')->limit(100)->get();
        $data = $rows->map(function ($row) use ($profile) {
            $memberIds = $this->members((int) $row->id);
            $otherIds = array_values(array_filter($memberIds, fn ($id) => $id !== (int) $profile->id));
            $participants = Profile::query()->whereIn('id', $otherIds)->get(['id', 'username', 'name', 'avatar'])
                ->map(fn ($p) => ['id' => (int) $p->id, 'username' => $p->username, 'name' => $p->name, 'avatar' => $p->avatar])->values();
            $last = DB::table('direct_messages')->where('conversation_id', $row->id)->orderByDesc('id')->first();
            $unread = DB::table('direct_messages as m')->where('m.conversation_id', $row->id)
                ->where('m.sender_profile_id', '!=', $profile->id)->whereNull('m.deleted_at')
                ->whereNotExists(fn ($q) => $q->selectRaw('1')->from('direct_message_reads as r')
                    ->whereColumn('r.message_id', 'm.id')->where('r.profile_id', $profile->id))->count();
            return [
                'id' => (int) $row->id, 'type' => $row->type, 'name' => $row->name,
                'participants' => $participants,
                'participant' => $row->type === 'direct' ? ($participants->first() ?? null) : null,
                'last_message' => $last ? ['id' => (int) $last->id, 'body' => $last->deleted_at ? 'Message supprimé' : (string) $last->body,
                    'sender_profile_id' => (int) $last->sender_profile_id, 'created_at' => (string) $last->created_at] : null,
                'unread_count' => $unread, 'status' => $row->status,
                'is_request' => $row->status === 'pending' && (int) $row->requested_by_profile_id !== (int) $profile->id,
                'updated_at' => (string) ($row->last_message_at ?? $row->created_at),
            ];
        })->values();
        return response()->json([
            'data' => $data,
            'unread_count' => $data->sum('unread_count'),
            'requests_count' => $data->where('is_request', true)->count(),
        ]);
    }

    public function start(Request $request, string $username): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $target = Profile::query()->where('username', $username)->where('is_kid', false)->firstOrFail();
        abort_if($target->id === $profile->id, 422, 'Vous ne pouvez pas vous écrire à vous-même.');
        abort_if($this->blocked((int) $profile->id, (int) $target->id), 403, 'Cette conversation n’est pas disponible.');
        $low = min($profile->id, $target->id);
        $high = max($profile->id, $target->id);
        $conversation = DB::table('direct_conversations')->where('type', 'direct')
            ->where('profile_low_id', $low)->where('profile_high_id', $high)->first();
        if (! $conversation) {
            $id = DB::table('direct_conversations')->insertGetId([
                'type' => 'direct', 'profile_low_id' => $low, 'profile_high_id' => $high,
                'status' => 'pending', 'requested_by_profile_id' => $profile->id,
                'created_at' => now(), 'updated_at' => now(),
            ]);
            DB::table('direct_conversation_members')->insert([
                ['conversation_id' => $id, 'profile_id' => $low, 'role' => 'member', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()],
                ['conversation_id' => $id, 'profile_id' => $high, 'role' => 'member', 'joined_at' => now(), 'created_at' => now(), 'updated_at' => now()],
            ]);
        } else {
            $id = (int) $conversation->id;
        }
        return response()->json(['conversation_id' => (int) $id]);
    }

    public function createGroup(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:80'],
            'usernames' => ['required', 'array', 'min:1', 'max:30'],
            'usernames.*' => ['required', 'string', 'distinct'],
        ]);
        $others = Profile::query()->whereIn('username', $validated['usernames'])->where('is_kid', false)->get();
        if ($others->count() !== count($validated['usernames'])) {
            throw ValidationException::withMessages(['usernames' => 'Un ou plusieurs membres sont introuvables.']);
        }
        foreach ($others as $other) {
            abort_if($this->blocked((int) $profile->id, (int) $other->id), 403, 'Un membre sélectionné ne peut pas être ajouté à ce groupe.');
        }
        $ids = $others->pluck('id')->push($profile->id)->unique()->sort()->values()->all();
        $id = DB::table('direct_conversations')->insertGetId([
            'type' => 'group', 'name' => trim($validated['name']),
            'created_by_profile_id' => $profile->id,
            'profile_low_id' => $ids[0], 'profile_high_id' => $ids[1],
            'status' => 'accepted', 'created_at' => now(), 'updated_at' => now(),
        ]);
        foreach ($ids as $memberId) {
            DB::table('direct_conversation_members')->insert([
                'conversation_id' => $id, 'profile_id' => $memberId,
                'role' => $memberId === (int) $profile->id ? 'admin' : 'member',
                'joined_at' => now(), 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        DB::table('direct_messages')->insert([
            'conversation_id' => $id, 'sender_profile_id' => $profile->id,
            'body' => $profile->name.' a créé le groupe « '.trim($validated['name']).' ». ',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('direct_conversations')->where('id', $id)->update(['last_message_at' => now()]);
        return response()->json(['conversation_id' => (int) $id], 201);
    }

    public function requests(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $ids = DB::table('direct_conversation_members')->where('profile_id', $profile->id)->whereNull('left_at')->pluck('conversation_id');
        $rows = DB::table('direct_conversations')->whereIn('id', $ids)->where('type', 'direct')
            ->where('status', 'pending')->where('requested_by_profile_id', '!=', $profile->id)->orderByDesc('created_at')->get();
        return response()->json(['data' => $rows->map(function ($row) use ($profile) {
            $senderId = (int) $row->requested_by_profile_id;
            $sender = Profile::query()->find($senderId);
            return ['conversation_id' => (int) $row->id, 'sender' => $sender ? [
                'id' => (int) $sender->id, 'username' => $sender->username, 'name' => $sender->name, 'avatar' => $sender->avatar,
            ] : null, 'preview' => DB::table('direct_messages')->where('conversation_id', $row->id)->orderBy('id')->value('body')];
        })->filter()->values()]);
    }

    public function accept(Request $request, int $conversation): JsonResponse
    {
        [$profile, $row] = $this->conversation($request, $conversation);
        abort_unless($row->status === 'pending' && (int) $row->requested_by_profile_id !== (int) $profile->id, 403);
        DB::table('direct_conversations')->where('id', $conversation)->update(['status' => 'accepted', 'updated_at' => now()]);
        return response()->json(['accepted' => true]);
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        [$profile, $row, $others] = $this->conversation($request, $conversation);
        $before = $request->integer('before') ?: null;
        $query = DB::table('direct_messages')->where('conversation_id', $conversation)
            ->when($before, fn ($q) => $q->where('id', '<', $before))
            ->orderByDesc('id')->limit(50)->get()->reverse()->values();
        return response()->json([
            'conversation' => ['id' => (int) $row->id, 'type' => $row->type, 'name' => $row->name, 'status' => $row->status],
            'participants' => $others->map(fn ($p) => ['id' => (int) $p->id, 'username' => $p->username, 'name' => $p->name, 'avatar' => $p->avatar])->values(),
            'participant' => $row->type === 'direct' ? ($others->first() ? ['id' => (int) $others->first()->id, 'username' => $others->first()->username, 'name' => $others->first()->name, 'avatar' => $others->first()->avatar] : null) : null,
            'data' => $query->map(function ($m) use ($profile) {
                $attachments = DB::table('direct_message_attachments')->where('message_id', $m->id)->get();
                $reads = DB::table('direct_message_reads')->where('message_id', $m->id)->pluck('profile_id')->map(fn ($id) => (int) $id)->all();
                return [
                    'id' => (int) $m->id, 'sender_profile_id' => (int) $m->sender_profile_id,
                    'body' => $m->deleted_at ? 'Ce message a été supprimé.' : (string) $m->body,
                    'deleted' => $m->deleted_at !== null, 'read_by' => $reads,
                    'created_at' => (string) $m->created_at,
                    'attachments' => $attachments->map(fn ($a) => ['id' => (int) $a->id, 'name' => $a->original_name, 'mime_type' => $a->mime_type, 'size' => (int) $a->size, 'url' => '/messages/attachments/'.$a->id])->values(),
                ];
            }),
        ]);
    }

    public function send(Request $request, int $conversation, PushDevices $push): JsonResponse
    {
        [$profile, $row] = $this->conversation($request, $conversation);
        abort_if($row->status !== 'accepted' && (int) $row->requested_by_profile_id !== (int) $profile->id, 403, 'En attente de réponse à votre demande de message.');
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $body = trim($validated['body']);
        if ($body === '') throw ValidationException::withMessages(['body' => 'Le message ne peut pas être vide.']);
        $id = DB::table('direct_messages')->insertGetId([
            'conversation_id' => $conversation, 'sender_profile_id' => $profile->id, 'body' => $body,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('direct_conversations')->where('id', $conversation)->update(['last_message_at' => now(), 'updated_at' => now()]);
        $this->notify($this->members($conversation), $profile, $conversation, $body, $row->status === 'pending');
        return response()->json(['data' => ['id' => (int) $id, 'sender_profile_id' => (int) $profile->id, 'body' => $body, 'read_by' => [], 'created_at' => now()->toIso8601String(), 'attachments' => []]], 201);
    }

    public function attach(Request $request, int $conversation): JsonResponse
    {
        [$profile, $row] = $this->conversation($request, $conversation);
        abort_if($row->status !== 'accepted', 403, 'Cette conversation n’est pas encore acceptée.');
        $request->validate(['file' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,mov,webm', 'max:102400']]);
        $file = $request->file('file');
        $mime = (string) $file->getMimeType();
        abort_unless(str_starts_with($mime, 'image/') || str_starts_with($mime, 'video/'), 422, 'Format non pris en charge.');
        if (str_starts_with($mime, 'image/')) abort_if($file->getSize() > 15 * 1024 * 1024, 422, 'Image limitée à 15 Mo.');
        $path = Storage::disk('local')->putFile('message-attachments', $file);
        $id = DB::table('direct_messages')->insertGetId([
            'conversation_id' => $conversation, 'sender_profile_id' => $profile->id, 'body' => '',
            'created_at' => now(), 'updated_at' => now(),
        ]);
        $attachmentId = DB::table('direct_message_attachments')->insertGetId([
            'message_id' => $id, 'disk' => 'local', 'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
            'mime_type' => $mime, 'size' => $file->getSize(), 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('direct_conversations')->where('id', $conversation)->update(['last_message_at' => now(), 'updated_at' => now()]);
        $this->notify($this->members($conversation), $profile, $conversation, 'Pièce jointe');
        return response()->json(['data' => ['id' => $id, 'sender_profile_id' => (int) $profile->id, 'body' => '', 'created_at' => now()->toIso8601String(),
            'attachments' => [['id' => $attachmentId, 'name' => $file->getClientOriginalName(), 'mime_type' => $mime, 'size' => $file->getSize(), 'url' => '/messages/attachments/'.$attachmentId]]]], 201);
    }

    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        [$profile] = $this->activeProfile($request);
        $file = DB::table('direct_message_attachments as a')->join('direct_messages as m', 'm.id', '=', 'a.message_id')
            ->join('direct_conversation_members as cm', 'cm.conversation_id', '=', 'm.conversation_id')
            ->where('a.id', $attachment)->where('cm.profile_id', $profile->id)->whereNull('cm.left_at')->select('a.*')->first();
        abort_unless($file, 404);
        return Storage::disk($file->disk)->download($file->path, $file->original_name, ['Content-Type' => $file->mime_type]);
    }

    public function deleteMessage(Request $request, int $conversation, int $message): JsonResponse
    {
        [$profile] = $this->conversation($request, $conversation);
        $row = DB::table('direct_messages')->where('id', $message)->where('conversation_id', $conversation)->first();
        abort_unless($row && (int) $row->sender_profile_id === (int) $profile->id, 404);
        DB::table('direct_messages')->where('id', $message)->update(['deleted_at' => now(), 'deleted_by_profile_id' => $profile->id, 'body' => '']);
        return response()->json(['deleted' => true]);
    }

    public function read(Request $request, int $conversation): JsonResponse
    {
        [$profile] = $this->conversation($request, $conversation);
        $ids = DB::table('direct_messages')->where('conversation_id', $conversation)->where('sender_profile_id', '!=', $profile->id)
            ->whereNull('deleted_at')->pluck('id');
        foreach ($ids as $id) {
            DB::table('direct_message_reads')->updateOrInsert(['message_id' => $id, 'profile_id' => $profile->id], ['read_at' => now()]);
        }
        return response()->json(['read' => true]);
    }

    public function block(Request $request, string $username): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $target = Profile::query()->where('username', $username)->where('is_kid', false)->firstOrFail();
        abort_if($target->id === $profile->id, 422);
        DB::table('direct_message_blocks')->updateOrInsert(
            ['blocker_profile_id' => $profile->id, 'blocked_profile_id' => $target->id],
            ['created_at' => now(), 'updated_at' => now()]
        );
        return response()->json(['blocked' => true]);
    }

    public function unblock(Request $request, string $username): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $target = Profile::query()->where('username', $username)->where('is_kid', false)->firstOrFail();
        DB::table('direct_message_blocks')->where('blocker_profile_id', $profile->id)->where('blocked_profile_id', $target->id)->delete();
        return response()->json(['blocked' => false]);
    }
}
