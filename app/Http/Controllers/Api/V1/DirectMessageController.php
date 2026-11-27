<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Middleware\ResolveProfile;
use App\Http\Requests\Api\V1\Messages\AttachmentRequest;
use App\Http\Requests\Api\V1\Messages\GroupMembersRequest;
use App\Http\Requests\Api\V1\Messages\GroupNameRequest;
use App\Http\Requests\Api\V1\Messages\GroupRequest;
use App\Http\Requests\Api\V1\Messages\MessagesPageRequest;
use App\Http\Requests\Api\V1\Messages\SendMessageRequest;
use App\Http\Resources\V1\Messages\ChatMemberResource;
use App\Http\Resources\V1\Messages\ConversationResource;
use App\Http\Resources\V1\Messages\MessageRequestResource;
use App\Http\Resources\V1\Messages\MessageResource;
use App\Models\MessageBlock;
use App\Models\Profile;
use App\Services\Messaging\Conversations;
use App\Services\Social\SharedCards;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The messaging for account profiles with a username (docs/messagerie.md): direct conversations
 * (a request first), groups, photos and videos, read receipts and blocks. Every route acts as the account's automatically resolved profile.
 */
class DirectMessageController extends Controller
{
    public function __construct(private Conversations $conversations) {}

    /**
     * The conversations.
     *
     * The 100 most recent conversations of the active profile, without the requests it received
     * (GET /messages/requests). `unread_count`: unread messages of these conversations;
     * `requests_count`: requests waiting for an answer.
     */
    public function index(Request $request): JsonResponse
    {
        $me = $this->me($request);
        $conversations = $this->conversations->inbox($me);

        return response()->json([
            'data' => ConversationResource::collection($conversations),
            'unread_count' => (int) $conversations->sum('unread_count'),
            'requests_count' => $this->conversations->requestsCount($me),
        ]);
    }

    /**
     * The unread messages.
     *
     * Messages of the others the active profile has not read, in all its conversations, the
     * requests it received included: the badge of « Messages » in the apps (« 9+ » above 9).
     * `requests_count`: requests waiting for an answer.
     */
    public function count(Request $request): JsonResponse
    {
        $me = $this->me($request);

        return response()->json([
            'unread_count' => $this->conversations->unreadCount($me),
            'requests_count' => $this->conversations->requestsCount($me),
        ]);
    }

    /**
     * The requests received.
     *
     * Direct conversations other profiles started with the active profile, waiting for it to
     * accept or decline them.
     */
    public function requests(Request $request): JsonResponse
    {
        return response()->json(['data' => MessageRequestResource::collection($this->conversations->requests($this->me($request)))]);
    }

    /**
     * Write to a member.
     *
     * The direct conversation with this member; a new one is a request the member accepts or
     * declines (until then, only the active profile writes, without attachments).
     */
    public function start(Request $request, string $username): JsonResponse
    {
        $me = $this->me($request);
        $conversation = $this->conversations->start($me, $this->member($username));

        return response()->json(['conversation_id' => (int) $conversation->id]);
    }

    /**
     * Create a group.
     *
     * The active profile runs it (`admin`); 31 members at most.
     */
    public function createGroup(GroupRequest $request): JsonResponse
    {
        $conversation = $this->conversations->createGroup($this->me($request), $request->name(), $request->usernames());

        return response()->json(['conversation_id' => (int) $conversation->id], 201);
    }

    /** Accept a request. */
    public function accept(Request $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->accept($me, $this->conversations->find($me, $conversation));

        return response()->json(['accepted' => true]);
    }

    /**
     * Decline a request.
     *
     * The conversation is hidden from both profiles; the member may ask again.
     */
    public function decline(Request $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->decline($me, $this->conversations->find($me, $conversation));

        return response()->json(['declined' => true]);
    }

    /**
     * A conversation.
     *
     * Its 20 latest messages (oldest first), or the 20 before `before`; `has_more`: older ones
     * exist. `is_request`: a request the active profile received; `blocked`: it blocked the other
     * profile of a direct conversation.
     */
    public function messages(MessagesPageRequest $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $found = $this->conversations->find($me, $conversation);
        [$messages, $hasMore] = $this->conversations->page($found, $request->before());
        $others = $this->conversations->others($found, $me);
        $direct = $found->isGroup() ? null : $others->first();

        return response()->json([
            'conversation' => [
                'id' => (int) $found->id,
                /** `direct` or `group`. */
                'type' => (string) $found->type,
                'name' => $found->name === null ? null : (string) $found->name,
                /** `pending` or `accepted`. */
                'status' => (string) $found->status,
                'is_request' => $found->isRequestFor($me),
            ],
            /** The members besides the active profile. */
            'participants' => ChatMemberResource::collection($others),
            /** The active profile runs this group. */
            'is_admin' => (bool) $this->conversations->membership($found, $me)?->isAdmin(),
            'participant' => $direct === null ? null : new ChatMemberResource($direct),
            'blocked' => $direct !== null && MessageBlock::query()
                ->where('blocker_profile_id', $me->id)
                ->where('blocked_profile_id', $direct->profile_id)
                ->exists(),
            'data' => MessageResource::collection($messages),
            'has_more' => $hasMore,
        ]);
    }

    /**
     * Send a message.
     *
     * A text, and / or a title, a review or a list (`share`, « Recommander à un ami ») the sender
     * can see.
     */
    public function send(SendMessageRequest $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $found = $this->conversations->find($me, $conversation);
        $share = $request->share();
        $shared = $share === null ? null : app(SharedCards::class)->snapshot($me, $share['type'], $share['id']);
        $message = $this->conversations->send($me, $found, $request->body(), $shared);

        return response()->json(['data' => new MessageResource($message)], 201);
    }

    /**
     * Send a photo or a video.
     *
     * Multipart field `file`: JPEG, PNG, WebP or GIF (15 MB at most), MP4, MOV or WebM (100 MB at
     * most). Once a request is accepted.
     */
    public function attach(AttachmentRequest $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $message = $this->conversations->attach($me, $this->conversations->find($me, $conversation), $request->attachment());

        return response()->json(['data' => new MessageResource($message)], 201);
    }

    /**
     * Download an attachment.
     *
     * For the members of its conversation only.
     */
    public function attachment(Request $request, int $attachment): StreamedResponse
    {
        $file = $this->conversations->attachment($this->me($request), $attachment);

        return Storage::disk($file->disk)->response($file->path, $file->original_name, [
            'Content-Type' => $file->mime_type,
            // A file never changes: the apps keep it.
            'Cache-Control' => 'private, max-age=604800, immutable',
        ]);
    }

    /**
     * Delete a message.
     *
     * One of the active profile's messages, for everybody (its files too).
     */
    public function deleteMessage(Request $request, int $conversation, int $message): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->delete($me, $this->conversations->find($me, $conversation), $message);

        return response()->json(['deleted' => true]);
    }

    /** Mark the conversation read. */
    public function read(Request $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->markRead($me, $this->conversations->find($me, $conversation));

        return response()->json(['read' => true]);
    }

    /** Rename a group (its admins). */
    public function updateGroup(GroupNameRequest $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->rename($me, $this->conversations->find($me, $conversation), $request->name());

        return response()->json(['updated' => true, 'name' => $request->name()]);
    }

    /** Add members to a group (its admins). */
    public function addGroupMembers(GroupMembersRequest $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->addMembers($me, $this->conversations->find($me, $conversation), $request->usernames());

        return response()->json(['added' => true]);
    }

    /** Remove a member from a group (its admins). */
    public function removeGroupMember(Request $request, int $conversation, string $username): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->removeMember($me, $this->conversations->find($me, $conversation), $username);

        return response()->json(['removed' => true]);
    }

    /**
     * Leave a group.
     *
     * An only admin names another one first; the last member leaving deletes the group.
     */
    public function leaveGroup(Request $request, int $conversation): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->leave($me, $this->conversations->find($me, $conversation));

        return response()->json(['left' => true]);
    }

    /**
     * Hand a group over.
     *
     * The member becomes its admin; the active profile, a member.
     */
    public function transferGroupAdmin(Request $request, int $conversation, string $username): JsonResponse
    {
        $me = $this->me($request);
        $this->conversations->transferAdmin($me, $this->conversations->find($me, $conversation), $username);

        return response()->json(['transferred' => true]);
    }

    /**
     * Block a member.
     *
     * Neither can write to the other, start a conversation nor add the other to a group.
     */
    public function block(Request $request, string $username): JsonResponse
    {
        $this->conversations->block($this->me($request), $this->member($username));

        return response()->json(['blocked' => true]);
    }

    /** Unblock a member. */
    public function unblock(Request $request, string $username): JsonResponse
    {
        $this->conversations->unblock($this->me($request), $this->member($username));

        return response()->json(['blocked' => false]);
    }

    /** The active profile, which must be an adult profile with a username. */
    private function me(Request $request): Profile
    {
        $profile = ResolveProfile::profile($request);
        abort_if($profile === null, 422, __('Select a profile.'));
        abort_unless(Conversations::allowed($profile), 403, __('Messaging requires a profile username.'));

        return $profile;
    }

    /** A member, by username (404 otherwise). */
    private function member(string $username): Profile
    {
        return Profile::query()
            ->where('username', mb_strtolower(ltrim($username, '@')))
            ->firstOrFail();
    }
}
