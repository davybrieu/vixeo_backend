<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Models\Profile;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DirectMessageController extends Controller
{
    private function activeProfile(Request $request): Profile
    {
        $profile = ResolveProfile::profile($request);
        abort_unless($profile instanceof Profile, 422, 'Sélectionnez un profil.');
        abort_if($profile->is_kid || $profile->username === null, 403, 'La messagerie est réservée aux profils adultes.');
        return $profile;
    }

    private function conversation(Request $request, int $id): array
    {
        $profile = $this->activeProfile($request);
        $conversation = DB::table('direct_conversations')->where('id', $id)->first();
        abort_unless($conversation && in_array($profile->id, [(int) $conversation->profile_low_id, (int) $conversation->profile_high_id], true), 404);
        $otherId = (int) ($conversation->profile_low_id == $profile->id ? $conversation->profile_high_id : $conversation->profile_low_id);
        $other = Profile::query()->whereKey($otherId)->where('is_kid', false)->firstOrFail();
        return [$profile, $conversation, $other];
    }

    public function index(Request $request): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $rows = DB::table('direct_conversations as c')
            ->join('direct_messages as m', function ($join) {
                $join->on('m.conversation_id', '=', 'c.id')
                    ->whereRaw('m.id = (select max(dm.id) from direct_messages dm where dm.conversation_id = c.id)');
            })
            ->where(function ($q) use ($profile) {
                $q->where('c.profile_low_id', $profile->id)->orWhere('c.profile_high_id', $profile->id);
            })
            ->select(['c.id as conversation_id', 'c.profile_low_id', 'c.profile_high_id', 'c.last_message_at',
                'm.id as message_id', 'm.body', 'm.sender_profile_id', 'm.created_at'])
            ->orderByDesc('c.last_message_at')->limit(100)->get();

        $data = $rows->map(function ($row) use ($profile) {
            $otherId = (int) ($row->profile_low_id == $profile->id ? $row->profile_high_id : $row->profile_low_id);
            $other = Profile::query()->select(['id', 'username', 'name', 'avatar'])->find($otherId);
            if (! $other || $other->username === null) return null;
            $unread = DB::table('direct_messages')->where('conversation_id', $row->conversation_id)
                ->where('sender_profile_id', '!=', $profile->id)->whereNull('read_at')->count();
            return [
                'id' => (int) $row->conversation_id,
                'participant' => ['id' => (int) $other->id, 'username' => $other->username, 'name' => $other->name, 'avatar' => $other->avatar],
                'last_message' => ['id' => (int) $row->message_id, 'body' => (string) $row->body, 'sender_profile_id' => (int) $row->sender_profile_id, 'created_at' => (string) $row->created_at],
                'unread_count' => $unread,
                'updated_at' => (string) $row->last_message_at,
            ];
        })->filter()->values();

        $unread = DB::table('direct_messages as m')->join('direct_conversations as c', 'c.id', '=', 'm.conversation_id')
            ->where(function ($q) use ($profile) { $q->where('c.profile_low_id', $profile->id)->orWhere('c.profile_high_id', $profile->id); })
            ->where('m.sender_profile_id', '!=', $profile->id)->whereNull('m.read_at')->count();
        return response()->json(['data' => $data, 'unread_count' => $unread]);
    }

    public function start(Request $request, string $username): JsonResponse
    {
        $profile = $this->activeProfile($request);
        $target = Profile::query()->where('username', $username)->where('is_kid', false)->firstOrFail();
        abort_if($target->id === $profile->id, 422, 'Vous ne pouvez pas vous écrire à vous-même.');
        $low = min($profile->id, $target->id);
        $high = max($profile->id, $target->id);
        $id = DB::table('direct_conversations')->where('profile_low_id', $low)->where('profile_high_id', $high)->value('id');
        if (! $id) {
            $id = DB::table('direct_conversations')->insertGetId([
                'profile_low_id' => $low, 'profile_high_id' => $high, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }
        return response()->json(['conversation_id' => (int) $id]);
    }

    public function messages(Request $request, int $conversation): JsonResponse
    {
        [, , $other] = $this->conversation($request, $conversation);
        $before = $request->integer('before') ?: null;
        $rows = DB::table('direct_messages')->where('conversation_id', $conversation)
            ->when($before, fn ($q) => $q->where('id', '<', $before))->orderByDesc('id')->limit(50)->get()->reverse()->values();
        return response()->json(['participant' => ['id' => (int) $other->id, 'username' => $other->username, 'name' => $other->name, 'avatar' => $other->avatar], 'data' => $rows->map(fn ($m) => [
            'id' => (int) $m->id, 'sender_profile_id' => (int) $m->sender_profile_id,
            'body' => (string) $m->body, 'read_at' => $m->read_at, 'created_at' => (string) $m->created_at,
        ])]);
    }

    public function send(Request $request, int $conversation): JsonResponse
    {
        [$profile] = $this->conversation($request, $conversation);
        $validated = $request->validate(['body' => ['required', 'string', 'max:5000']]);
        $body = trim($validated['body']);
        if ($body === '') throw ValidationException::withMessages(['body' => 'Le message ne peut pas être vide.']);
        $id = DB::table('direct_messages')->insertGetId([
            'conversation_id' => $conversation, 'sender_profile_id' => $profile->id, 'body' => $body,
            'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('direct_conversations')->where('id', $conversation)->update(['last_message_at' => now(), 'updated_at' => now()]);
        $message = DB::table('direct_messages')->where('id', $id)->first();
        return response()->json(['data' => [
            'id' => (int) $message->id, 'sender_profile_id' => (int) $message->sender_profile_id,
            'body' => (string) $message->body, 'read_at' => null, 'created_at' => (string) $message->created_at,
        ]], 201);
    }

    public function read(Request $request, int $conversation): JsonResponse
    {
        [$profile] = $this->conversation($request, $conversation);
        DB::table('direct_messages')->where('conversation_id', $conversation)
            ->where('sender_profile_id', '!=', $profile->id)->whereNull('read_at')->update(['read_at' => now()]);
        return response()->json(['read' => true]);
    }
}
