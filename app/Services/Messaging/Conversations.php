<?php

namespace App\Services\Messaging;

use App\Events\ConversationChanged;
use App\Models\Conversation;
use App\Models\ConversationMember;
use App\Models\DirectMessage;
use App\Models\MessageAttachment;
use App\Models\MessageBlock;
use App\Models\Profile;
use App\Models\PushDevice;
use App\Services\Push\PushDevices;
use App\Services\Push\PushMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection as BaseCollection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * The messaging between adult profiles (docs/messagerie.md): direct conversations, which start as a
 * request the other profile accepts or declines, and groups run by their admins. The only place that
 * writes conversations, members, messages, reads and blocks. Every list is read with a fixed number
 * of queries, whatever the number of conversations or messages.
 *
 * New messages, deletions and reads are announced on the conversation's private channel (Laravel
 * Reverb, docs/reverb.md) and pushed to the other members (FCM, `social` channel), after the response.
 */
class Conversations
{
    /** Messages per page of a conversation. */
    public const PAGE = 50;

    /** Conversations listed at most (the most recent first). */
    public const INBOX_LIMIT = 100;

    /** Private disk of the attachments (never served directly). */
    public const DISK = 'local';

    public function __construct(private PushDevices $devices) {}

    /** The profile may use the messaging: an adult profile with a username. */
    public static function allowed(?Profile $profile): bool
    {
        return $profile !== null && ! $profile->is_kid && $profile->username !== null;
    }

    /**
     * The conversations of [me], the most recent first: requests it received are left out (they
     * are listed by [requests]). Each with its active members and their profiles, its last message
     * and `unread_count`.
     *
     * @return Collection<int, Conversation>
     */
    public function inbox(Profile $me): Collection
    {
        return Conversation::query()
            ->whereIn('id', $this->conversationIdsOf($me))
            ->where('status', '!=', Conversation::REJECTED)
            ->where(fn (Builder $query) => $query
                ->where('status', '!=', Conversation::PENDING)
                ->orWhere('requested_by_profile_id', $me->id))
            ->with(['activeMembers.profile', 'lastMessage'])
            ->withCount(['messages as unread_count' => fn (Builder $query) => $this->unread($query, $me)])
            // A request without any message yet has no last_message_at: its creation date.
            ->orderByRaw('coalesce(last_message_at, created_at) desc')
            ->orderByDesc('id')
            ->limit(self::INBOX_LIMIT)
            ->get();
    }

    /**
     * The requests [me] received and has not answered, the most recent first, with who sent them
     * and their first message.
     *
     * @return Collection<int, Conversation>
     */
    public function requests(Profile $me): Collection
    {
        return $this->requestsQuery($me)->with(['requester', 'firstMessage'])->latest()->get();
    }

    public function requestsCount(Profile $me): int
    {
        return $this->requestsQuery($me)->count();
    }

    /**
     * Messages of the others [me] has not read, in all its conversations, the requests it received
     * included (the badge of « Messages » in the apps). One query.
     */
    public function unreadCount(Profile $me): int
    {
        return $this->unread(DirectMessage::query(), $me)
            ->whereIn('conversation_id', Conversation::query()
                ->select('id')
                ->whereIn('id', $this->conversationIdsOf($me))
                ->where('status', '!=', Conversation::REJECTED))
            ->count();
    }

    /**
     * A conversation of [me], with its active members and their profiles. 404 for a conversation
     * it is not (or no longer) a member of, a declined request, or a member that became a child's
     * profile.
     */
    public function find(Profile $me, int $id): Conversation
    {
        $conversation = Conversation::query()
            ->whereKey($id)
            ->where('status', '!=', Conversation::REJECTED)
            ->with('activeMembers.profile')
            ->first();
        abort_if($conversation === null || $this->membership($conversation, $me) === null, 404);
        abort_if($conversation->activeMembers->contains(fn (ConversationMember $member) => $member->profile->is_kid), 404);

        return $conversation;
    }

    /** [profile] is an active member of conversation [id] (its private broadcast channel). */
    public function isMember(Profile $profile, int $id): bool
    {
        return ConversationMember::query()
            ->where('conversation_id', $id)
            ->where('profile_id', $profile->id)
            ->whereNull('left_at')
            ->whereHas('conversation', fn (Builder $query) => $query->where('status', '!=', Conversation::REJECTED))
            ->exists();
    }

    public function membership(Conversation $conversation, Profile $profile): ?ConversationMember
    {
        return $conversation->activeMembers->firstWhere('profile_id', $profile->id);
    }

    /**
     * The members of [conversation] other than [me].
     *
     * @return BaseCollection<int, ConversationMember>
     */
    public function others(Conversation $conversation, Profile $me): BaseCollection
    {
        return $conversation->activeMembers->where('profile_id', '!=', $me->id)->values();
    }

    /**
     * A page of messages, oldest first: the latest ones, or those before message [before] (to
     * scroll up). With their attachments and who read them.
     *
     * @return array{0: Collection<int, DirectMessage>, 1: bool} the messages, and whether older ones exist
     */
    public function page(Conversation $conversation, ?int $before = null): array
    {
        $messages = $conversation->messages()
            ->with(['attachments', 'reads:id,message_id,profile_id'])
            ->when($before !== null, fn (Builder $query) => $query->where('id', '<', $before))
            ->orderByDesc('id')
            ->limit(self::PAGE + 1)
            ->get();

        return [$messages->take(self::PAGE)->reverse()->values(), $messages->count() > self::PAGE];
    }

    /**
     * The direct conversation between [me] and [target], started as a request when there is none
     * (a declined one is not reused: the request can be made again).
     */
    public function start(Profile $me, Profile $target): Conversation
    {
        abort_if($target->id === $me->id, 422, __('You cannot write to yourself.'));
        abort_if($this->blocked($me->id, [$target->id]), 403, __('This conversation is not available.'));
        $low = min($me->id, $target->id);
        $high = max($me->id, $target->id);

        // Two taps at once must not open two conversations.
        return Cache::lock("messages:direct:{$low}:{$high}", 10)->block(5, function () use ($me, $low, $high) {
            $existing = Conversation::query()
                ->where('type', Conversation::DIRECT)
                ->where('profile_low_id', $low)
                ->where('profile_high_id', $high)
                ->where('status', '!=', Conversation::REJECTED)
                ->first();

            return $existing ?? DB::transaction(function () use ($me, $low, $high) {
                $conversation = Conversation::create([
                    'type' => Conversation::DIRECT,
                    'profile_low_id' => $low,
                    'profile_high_id' => $high,
                    'status' => Conversation::PENDING,
                    'requested_by_profile_id' => $me->id,
                ]);
                $conversation->members()->createMany([
                    ['profile_id' => $low, 'role' => ConversationMember::MEMBER, 'joined_at' => now()],
                    ['profile_id' => $high, 'role' => ConversationMember::MEMBER, 'joined_at' => now()],
                ]);

                return $conversation;
            });
        });
    }

    /**
     * A group named [name], run by [me] (its admin), with the members [usernames]. Starts with a
     * message saying who created it.
     *
     * @param  list<string>  $usernames
     */
    public function createGroup(Profile $me, string $name, array $usernames): Conversation
    {
        $others = $this->profilesNamed($usernames)->reject(fn (Profile $profile) => $profile->id === $me->id);
        abort_if($others->isEmpty(), 422, __('Add at least one member.'));
        abort_if($this->blocked($me->id, $others->modelKeys()), 403, __('A selected member cannot be added to this group.'));

        return DB::transaction(function () use ($me, $name, $others) {
            $conversation = Conversation::create([
                'type' => Conversation::GROUP,
                'name' => $name,
                'created_by_profile_id' => $me->id,
                'status' => Conversation::ACCEPTED,
                'last_message_at' => now(),
            ]);
            $conversation->members()->createMany([
                ['profile_id' => $me->id, 'role' => ConversationMember::ADMIN, 'joined_at' => now()],
                ...$others->map(fn (Profile $profile) => [
                    'profile_id' => $profile->id,
                    'role' => ConversationMember::MEMBER,
                    'joined_at' => now(),
                ])->all(),
            ]);
            $conversation->messages()->create([
                'sender_profile_id' => $me->id,
                'body' => __(':name created the group « :group ».', ['name' => $me->name, 'group' => $name]),
            ]);

            return $conversation;
        });
    }

    /** [me] accepts the request it received: both can write now. */
    public function accept(Profile $me, Conversation $conversation): void
    {
        abort_unless($conversation->isRequestFor($me), 403);
        abort_if(
            $this->blocked($me->id, $this->others($conversation, $me)->pluck('profile_id')->all()),
            403,
            __('This request can no longer be accepted.'),
        );
        $conversation->update(['status' => Conversation::ACCEPTED]);
        $this->broadcast($conversation, 'updated');
    }

    /** [me] declines the request it received: hidden from both; it may be made again. */
    public function decline(Profile $me, Conversation $conversation): void
    {
        abort_unless(! $conversation->isGroup() && $conversation->isRequestFor($me), 403);
        $conversation->update(['status' => Conversation::REJECTED]);
    }

    public function send(Profile $me, Conversation $conversation, string $body): DirectMessage
    {
        $this->assertCanWrite($me, $conversation);
        $message = DB::transaction(function () use ($me, $conversation, $body) {
            $message = $conversation->messages()->create(['sender_profile_id' => $me->id, 'body' => $body]);
            $conversation->update(['last_message_at' => $message->created_at]);

            return $message;
        });
        $message->setRelation('attachments', new Collection)->setRelation('reads', new Collection);
        $this->announce($me, $conversation, $message, $body);

        return $message;
    }

    /** A photo or a video, as a message of its own (once a request is accepted). */
    public function attach(Profile $me, Conversation $conversation, UploadedFile $file): DirectMessage
    {
        $this->assertCanWrite($me, $conversation, attachment: true);
        $path = $file->store('message-attachments', self::DISK);
        abort_unless(is_string($path), 500, __('The file could not be saved.'));
        $mime = (string) $file->getMimeType();

        try {
            $message = DB::transaction(function () use ($me, $conversation, $file, $path, $mime) {
                $message = $conversation->messages()->create(['sender_profile_id' => $me->id, 'body' => '']);
                $message->attachments()->create([
                    'disk' => self::DISK,
                    'path' => $path,
                    'original_name' => mb_substr($file->getClientOriginalName(), 0, 255),
                    'mime_type' => $mime,
                    'size' => (int) $file->getSize(),
                ]);
                $conversation->update(['last_message_at' => $message->created_at]);

                return $message;
            });
        } catch (Throwable $exception) {
            Storage::disk(self::DISK)->delete($path);
            throw $exception;
        }
        $message->load('attachments')->setRelation('reads', new Collection);
        $this->announce($me, $conversation, $message, str_starts_with($mime, 'image/') ? __('Photo') : __('Video'));

        return $message;
    }

    /** An attachment [me] may download: a member of its conversation, message not deleted. */
    public function attachment(Profile $me, int $id): MessageAttachment
    {
        $attachment = MessageAttachment::query()
            ->whereKey($id)
            ->whereHas('message', fn (Builder $message) => $message
                ->whereNull('deleted_at')
                ->whereHas('conversation', fn (Builder $conversation) => $conversation
                    ->where('status', '!=', Conversation::REJECTED)
                    ->whereHas('activeMembers', fn (Builder $member) => $member->where('profile_id', $me->id))))
            ->first();
        abort_if($attachment === null, 404);

        return $attachment;
    }

    /** [me] deletes one of its messages, for everybody (its files too). */
    public function delete(Profile $me, Conversation $conversation, int $messageId): void
    {
        $message = $conversation->messages()
            ->whereKey($messageId)
            ->where('sender_profile_id', $me->id)
            ->whereNull('deleted_at')
            ->with('attachments')
            ->first();
        abort_if($message === null, 404);

        $this->deleteFiles($message->attachments);
        DB::transaction(function () use ($me, $message) {
            $message->attachments()->delete();
            $message->update(['body' => '', 'deleted_at' => now(), 'deleted_by_profile_id' => $me->id]);
        });
        $this->broadcast($conversation, 'deleted', $message->id, $me->id);
    }

    /** The messages of the others are read by [me]; how many were not yet. */
    public function markRead(Profile $me, Conversation $conversation): int
    {
        $unread = $this->unread($conversation->messages()->getQuery(), $me)->pluck('id');
        $read = 0;
        $now = now();
        foreach ($unread->chunk(500) as $ids) {
            $read += DB::table('direct_message_reads')->insertOrIgnore(
                $ids->map(fn (int $id) => ['message_id' => $id, 'profile_id' => $me->id, 'read_at' => $now])->values()->all(),
            );
        }
        // Only when something changed: reading again announces nothing (no loop between apps).
        if ($read > 0) {
            $this->broadcast($conversation, 'read', senderId: $me->id);
        }

        return $read;
    }

    public function rename(Profile $me, Conversation $conversation, string $name): void
    {
        $this->assertAdmin($me, $conversation);
        $conversation->update(['name' => $name]);
        $this->broadcast($conversation, 'updated');
    }

    /** @param  list<string>  $usernames */
    public function addMembers(Profile $me, Conversation $conversation, array $usernames): void
    {
        $this->assertAdmin($me, $conversation);
        $active = $conversation->activeMembers->pluck('profile_id');
        $new = $this->profilesNamed($usernames)->reject(fn (Profile $profile) => $active->contains($profile->id));
        if ($new->isEmpty()) {
            return;
        }
        abort_if(
            $active->count() + $new->count() > Conversation::MAX_GROUP_MEMBERS,
            422,
            __('A group is limited to :count members.', ['count' => Conversation::MAX_GROUP_MEMBERS]),
        );
        abort_if($this->blocked($me->id, $new->modelKeys()), 403, __('A selected member cannot be added to this group.'));

        // A member who left comes back as the same row.
        ConversationMember::query()->upsert(
            $new->map(fn (Profile $profile) => [
                'conversation_id' => $conversation->id,
                'profile_id' => $profile->id,
                'role' => ConversationMember::MEMBER,
                'joined_at' => now(),
                'left_at' => null,
            ])->values()->all(),
            ['conversation_id', 'profile_id'],
            ['role', 'joined_at', 'left_at'],
        );
        $this->broadcast($conversation, 'updated');
    }

    public function removeMember(Profile $me, Conversation $conversation, string $username): void
    {
        $this->assertAdmin($me, $conversation);
        $member = $this->memberNamed($conversation, $username);
        abort_if($member->profile_id === $me->id, 422, __('Use « Leave the group » to leave it.'));
        $member->update(['left_at' => now(), 'role' => ConversationMember::MEMBER]);
        $this->broadcast($conversation, 'updated');
    }

    /** [me] leaves a group; the last member leaving deletes it. An only admin names another first. */
    public function leave(Profile $me, Conversation $conversation): void
    {
        abort_unless($conversation->isGroup(), 404);
        $member = $this->membership($conversation, $me);
        $others = $this->others($conversation, $me);
        if ($others->isEmpty()) {
            $this->destroy($conversation);

            return;
        }
        abort_if(
            $member?->isAdmin() && ! $others->contains(fn (ConversationMember $other) => $other->isAdmin()),
            422,
            __('Name another admin before leaving the group.'),
        );
        $member?->update(['left_at' => now(), 'role' => ConversationMember::MEMBER]);
        $this->broadcast($conversation, 'updated');
    }

    /** The admin hands the group over to another member (and becomes a member). */
    public function transferAdmin(Profile $me, Conversation $conversation, string $username): void
    {
        $actor = $this->assertAdmin($me, $conversation);
        $target = $this->memberNamed($conversation, $username);
        if ($target->profile_id === $me->id) {
            return;
        }
        DB::transaction(function () use ($conversation, $actor, $target) {
            $actor->update(['role' => ConversationMember::MEMBER]);
            $target->update(['role' => ConversationMember::ADMIN]);
            $conversation->update(['created_by_profile_id' => $target->profile_id]);
        });
        $this->broadcast($conversation, 'updated');
    }

    public function block(Profile $me, Profile $target): void
    {
        abort_if($target->id === $me->id, 422, __('You cannot block yourself.'));
        MessageBlock::query()->firstOrCreate(['blocker_profile_id' => $me->id, 'blocked_profile_id' => $target->id]);
    }

    public function unblock(Profile $me, Profile $target): void
    {
        MessageBlock::query()->where('blocker_profile_id', $me->id)->where('blocked_profile_id', $target->id)->delete();
    }

    /**
     * Before deleting profiles (a profile, or an account and its profiles): removes the files the
     * database will cascade away (their messages, their direct conversations), gives each group
     * they ran alone a new admin (its oldest member), and deletes the groups nobody else is in.
     *
     * @param  list<int>  $profileIds
     */
    public function forgetProfiles(array $profileIds): void
    {
        if ($profileIds === []) {
            return;
        }
        $this->deleteFiles(MessageAttachment::query()
            ->whereHas('message', fn (Builder $message) => $message->where(fn (Builder $query) => $query
                ->whereIn('sender_profile_id', $profileIds)
                ->orWhereHas('conversation', fn (Builder $conversation) => $conversation
                    ->whereIn('profile_low_id', $profileIds)
                    ->orWhereIn('profile_high_id', $profileIds))))
            ->get(['id', 'disk', 'path']));

        $groups = Conversation::query()
            ->where('type', Conversation::GROUP)
            ->whereHas('activeMembers', fn (Builder $member) => $member->whereIn('profile_id', $profileIds))
            ->with('activeMembers')
            ->get();
        foreach ($groups as $group) {
            $remaining = $group->activeMembers->reject(fn (ConversationMember $member) => in_array($member->profile_id, $profileIds, true));
            if ($remaining->isEmpty()) {
                $this->destroy($group);
            } elseif (! $remaining->contains(fn (ConversationMember $member) => $member->isAdmin())) {
                $remaining->sortBy('id')->first()?->update(['role' => ConversationMember::ADMIN]);
            }
        }
    }

    /** Pushes [message] to the apps of [profileIds] that keep the social activity on. */
    public function push(array $profileIds, PushMessage $message): void
    {
        if (! $this->devices->available()) {
            return;
        }
        $devices = PushDevice::query()->whereIn('profile_id', $profileIds)->where('social', true)->get();
        if ($devices->isNotEmpty()) {
            $this->devices->send($devices, $message);
        }
    }

    /** Real-time messaging is configured (Reverb, with its application key). */
    public static function realtime(): bool
    {
        return config('broadcasting.default') === 'reverb' && filled(config('broadcasting.connections.reverb.key'));
    }

    /** @return Builder<Conversation> */
    private function requestsQuery(Profile $me): Builder
    {
        return Conversation::query()
            ->whereIn('id', $this->conversationIdsOf($me))
            ->where('type', Conversation::DIRECT)
            ->where('status', Conversation::PENDING)
            ->where('requested_by_profile_id', '!=', $me->id);
    }

    /** @return Builder<ConversationMember> */
    private function conversationIdsOf(Profile $me): Builder
    {
        return ConversationMember::query()->select('conversation_id')->where('profile_id', $me->id)->whereNull('left_at');
    }

    /**
     * Messages of the others that [me] has not read (deleted ones don't count).
     *
     * @param  Builder<DirectMessage>  $query
     * @return Builder<DirectMessage>
     */
    private function unread(Builder $query, Profile $me): Builder
    {
        return $query
            ->where('sender_profile_id', '!=', $me->id)
            ->whereNull('deleted_at')
            ->whereDoesntHave('reads', fn (Builder $read) => $read->where('profile_id', $me->id));
    }

    /**
     * Adult profiles by username (with or without @, any case); 422 when one is missing.
     *
     * @param  list<string>  $usernames
     * @return Collection<int, Profile>
     */
    private function profilesNamed(array $usernames): Collection
    {
        $names = array_values(array_unique(array_map(fn (string $name) => mb_strtolower(ltrim(trim($name), '@')), $usernames)));
        $profiles = Profile::query()->whereIn('username', $names)->where('is_kid', false)->get();
        if ($profiles->count() !== count($names)) {
            throw ValidationException::withMessages(['usernames' => __('One or more members cannot be found.')]);
        }

        return $profiles;
    }

    private function memberNamed(Conversation $conversation, string $username): ConversationMember
    {
        $name = mb_strtolower(ltrim($username, '@'));
        $member = $conversation->activeMembers->first(fn (ConversationMember $member) => $member->profile->username === $name);
        abort_if($member === null, 404);

        return $member;
    }

    private function assertAdmin(Profile $me, Conversation $conversation): ConversationMember
    {
        abort_unless($conversation->isGroup(), 404);
        $member = $this->membership($conversation, $me);
        abort_unless($member?->isAdmin() === true, 403, __('Only the admins can manage the group.'));

        return $member;
    }

    /** A request is answered by accepting it; its sender writes, but sends files once accepted. */
    private function assertCanWrite(Profile $me, Conversation $conversation, bool $attachment = false): void
    {
        if ($conversation->isPending()) {
            abort_if($conversation->isRequestFor($me), 403, __('Accept the request to answer.'));
            abort_if($attachment, 403, __('Photos and videos can be sent once the request is accepted.'));
        }
        if (! $conversation->isGroup()) {
            abort_if(
                $this->blocked($me->id, $this->others($conversation, $me)->pluck('profile_id')->all()),
                403,
                __('You can no longer write to this member.'),
            );
        }
    }

    /**
     * [profileId] blocked one of [others], or one of them blocked it.
     *
     * @param  list<int>  $others
     */
    private function blocked(int $profileId, array $others): bool
    {
        if ($others === []) {
            return false;
        }

        return MessageBlock::query()
            ->where(fn (Builder $query) => $query->where('blocker_profile_id', $profileId)->whereIn('blocked_profile_id', $others))
            ->orWhere(fn (Builder $query) => $query->whereIn('blocker_profile_id', $others)->where('blocked_profile_id', $profileId))
            ->exists();
    }

    /** Deletes a conversation, its messages and their files. */
    private function destroy(Conversation $conversation): void
    {
        $this->deleteFiles(MessageAttachment::query()
            ->whereHas('message', fn (Builder $message) => $message->where('conversation_id', $conversation->id))
            ->get(['id', 'disk', 'path']));
        $conversation->delete();
    }

    /** @param  iterable<MessageAttachment>  $attachments */
    private function deleteFiles(iterable $attachments): void
    {
        foreach ($attachments as $attachment) {
            Storage::disk($attachment->disk)->delete($attachment->path);
        }
    }

    /** A new message: on the conversation's channel now, pushed to the other members after the response. */
    private function announce(Profile $sender, Conversation $conversation, DirectMessage $message, string $preview): void
    {
        $this->broadcast($conversation, 'sent', $message->id, $sender->id, $message->body);

        $recipients = $this->others($conversation, $sender)->pluck('profile_id')->all();
        if ($recipients === []) {
            return;
        }
        [$title, $body] = match (true) {
            $conversation->isPending() => [__('New message request'), __(':name: :message', ['name' => $sender->name, 'message' => $preview])],
            $conversation->isGroup() => [(string) $conversation->name, __(':name: :message', ['name' => $sender->name, 'message' => $preview])],
            default => [$sender->name, $preview],
        };
        $push = new PushMessage(
            title: $title,
            body: Str::limit($body, 120, '…'),
            path: "/messages/{$conversation->id}",
            channel: PushMessage::SOCIAL,
            tag: "message-{$conversation->id}",
        );
        dispatch(fn () => app(self::class)->push($recipients, $push))->afterResponse();
    }

    /** Tells the apps showing the conversation that it changed (`message.sent`, `.deleted`, `.read`, `.updated`). */
    private function broadcast(
        Conversation $conversation,
        string $change,
        ?int $messageId = null,
        ?int $senderId = null,
        string $body = '',
    ): void {
        if (! self::realtime()) {
            return;
        }
        $event = new ConversationChanged($conversation->id, $change, $messageId, $senderId, $body);
        dispatch(function () use ($event) {
            // Reverb down: the apps read the conversation again when they come back to it.
            try {
                broadcast($event);
            } catch (Throwable $exception) {
                report($exception);
            }
        })->afterResponse();
    }
}
