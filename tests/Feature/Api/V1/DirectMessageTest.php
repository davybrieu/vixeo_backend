<?php

use App\Events\ConversationChanged;
use App\Models\Conversation;
use App\Models\DirectMessage;
use App\Models\MessageAttachment;
use App\Models\Profile;
use App\Models\User;
use App\Services\AccountExport;
use App\Services\Messaging\Conversations;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

function messagingProfile(string $username, array $attributes = []): Profile
{
    return Profile::factory()->for(User::factory()->create([]))->create(['username' => $username, ...$attributes]);
}

/** Acts as the account that owns [profile]. */
function actingAsProfile(Profile $profile): mixed
{
    Sanctum::actingAs($profile->user);

    return test();
}

function conversationWith(Profile $from, Profile $to, bool $accepted = true): int
{
    $id = actingAsProfile($from)->postJson("/api/v1/messages/with/{$to->username}")->assertOk()->json('conversation_id');
    if ($accepted) {
        actingAsProfile($to)->postJson("/api/v1/messages/{$id}/accept")->assertOk();
    }

    return $id;
}

beforeEach(fn () => Storage::fake('local'));

it('handles message requests, acceptance, read receipts and blocking', function () {
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');

    $conversation = conversationWith($alice, $bob, accepted: false);
    // The same conversation when asked again.
    actingAsProfile($alice)->postJson('/api/v1/messages/with/@Bob')->assertOk()->assertJsonPath('conversation_id', $conversation);

    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}", ['body' => '  Salut Bob  '])
        ->assertCreated()
        ->assertJsonPath('data.body', 'Salut Bob')
        ->assertJsonPath('data.deleted', false);

    // Bob answers by accepting first; the request is not in his conversations.
    actingAsProfile($bob)->postJson("/api/v1/messages/{$conversation}", ['body' => 'Coucou'])->assertForbidden();
    actingAsProfile($bob)->getJson('/api/v1/messages')->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('requests_count', 1);
    actingAsProfile($bob)->getJson('/api/v1/messages/requests')
        ->assertOk()
        ->assertJsonPath('data.0.sender.username', 'alice')
        ->assertJsonPath('data.0.preview', 'Salut Bob');
    actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}")->assertOk()->assertJsonPath('conversation.is_request', true);

    actingAsProfile($bob)->postJson("/api/v1/messages/{$conversation}/accept")->assertOk()->assertJsonPath('accepted', true);
    actingAsProfile($bob)->getJson('/api/v1/messages')
        ->assertOk()
        ->assertJsonPath('data.0.participant.username', 'alice')
        ->assertJsonPath('data.0.last_message.body', 'Salut Bob')
        ->assertJsonPath('data.0.unread_count', 1)
        ->assertJsonPath('unread_count', 1)
        ->assertJsonPath('requests_count', 0);

    $page = actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}")->assertOk()
        ->assertJsonPath('data.0.body', 'Salut Bob')
        ->assertJsonPath('has_more', false)
        ->json();
    // Dates carry their time zone (an app reads them as UTC, not as local time).
    expect($page['data'][0]['created_at'])->toMatch('/[+-]\d\d:\d\d$|Z$/');

    actingAsProfile($bob)->postJson("/api/v1/messages/{$conversation}/read")->assertOk();
    actingAsProfile($bob)->getJson('/api/v1/messages')->assertJsonPath('data.0.unread_count', 0);
    actingAsProfile($alice)->getJson("/api/v1/messages/{$conversation}")->assertOk()->assertJsonPath('data.0.read_by.0', $bob->id);

    actingAsProfile($bob)->postJson('/api/v1/messages/block/alice')->assertOk();
    actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}")->assertJsonPath('blocked', true);
    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}", ['body' => 'Encore un message'])->assertForbidden();
    actingAsProfile($alice)->postJson('/api/v1/messages/with/bob')->assertForbidden();

    actingAsProfile($bob)->deleteJson('/api/v1/messages/block/alice')->assertOk()->assertJsonPath('blocked', false);
    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}", ['body' => 'Merci'])->assertCreated();
});

it('counts the unread messages of every conversation, requests included', function () {
    $me = messagingProfile('me');
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $carol = messagingProfile('carol');

    // Alice's conversation: two messages to read.
    $withAlice = conversationWith($me, $alice);
    actingAsProfile($alice)->postJson("/api/v1/messages/{$withAlice}", ['body' => 'Un'])->assertCreated();
    actingAsProfile($alice)->postJson("/api/v1/messages/{$withAlice}", ['body' => 'Deux'])->assertCreated();
    // Bob's request, not answered yet: one more.
    $fromBob = conversationWith($bob, $me, accepted: false);
    actingAsProfile($bob)->postJson("/api/v1/messages/{$fromBob}", ['body' => 'Salut'])->assertCreated();
    // My own request to Carol and my own messages: not counted.
    $toCarol = conversationWith($me, $carol, accepted: false);
    actingAsProfile($me)->postJson("/api/v1/messages/{$toCarol}", ['body' => 'Coucou'])->assertCreated();

    actingAsProfile($me)->getJson('/api/v1/messages/count')
        ->assertOk()
        ->assertExactJson(['unread_count' => 3, 'requests_count' => 1]);

    actingAsProfile($me)->postJson("/api/v1/messages/{$withAlice}/read")->assertOk();
    actingAsProfile($me)->getJson('/api/v1/messages/count')->assertJsonPath('unread_count', 1);

    // A declined request no longer counts.
    actingAsProfile($me)->deleteJson("/api/v1/messages/{$fromBob}/request")->assertOk();
    actingAsProfile($me)->getJson('/api/v1/messages/count')
        ->assertExactJson(['unread_count' => 0, 'requests_count' => 0]);
});

it('hides a declined request from both profiles and lets it be made again', function () {
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $conversation = conversationWith($alice, $bob, accepted: false);

    actingAsProfile($bob)->deleteJson("/api/v1/messages/{$conversation}/request")->assertOk();
    actingAsProfile($alice)->getJson("/api/v1/messages/{$conversation}")->assertNotFound();
    actingAsProfile($bob)->getJson('/api/v1/messages/requests')->assertJsonCount(0, 'data');

    $again = actingAsProfile($alice)->postJson('/api/v1/messages/with/bob')->assertOk()->json('conversation_id');
    expect($again)->not->toBe($conversation);
});

it('allows messaging for the single profile of an account', function () {
    $alice = messagingProfile('alice');
    $other = messagingProfile('kiddo');

    actingAsProfile($other)->getJson('/api/v1/messages')->assertOk();
    actingAsProfile($alice)->postJson('/api/v1/messages/with/kiddo')->assertOk();
    actingAsProfile($alice)->postJson('/api/v1/messages/with/alice')->assertUnprocessable();
});

it('lists the conversations with a fixed number of queries', function () {
    $me = messagingProfile('me');
    foreach (range(1, 6) as $i) {
        $other = messagingProfile("member{$i}");
        $id = conversationWith($me, $other);
        actingAsProfile($other)->postJson("/api/v1/messages/{$id}", ['body' => "Bonjour {$i}"])->assertCreated();
    }

    DB::enableQueryLog();
    actingAsProfile($me)->getJson('/api/v1/messages')->assertOk()->assertJsonCount(6, 'data')->assertJsonPath('unread_count', 6);
    $queries = count(DB::getQueryLog());
    DB::disableQueryLog();

    // Sanctum, the profile, the conversations, their members, profiles and last messages, the requests.
    expect($queries)->toBeLessThanOrEqual(9);
});

it('pages the messages of a conversation', function () {
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $conversation = conversationWith($alice, $bob);
    $rows = collect(range(1, 25))->map(fn (int $i) => [
        'conversation_id' => $conversation, 'sender_profile_id' => $alice->id, 'body' => "Message {$i}",
        'created_at' => now(), 'updated_at' => now(),
    ]);
    DirectMessage::query()->insert($rows->all());

    // 20 at a time (pages of 20, docs/messagerie.md).
    $latest = actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}")->assertOk()
        ->assertJsonCount(20, 'data')
        ->assertJsonPath('has_more', true)
        ->assertJsonPath('data.19.body', 'Message 25')
        ->json('data');

    actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}?before={$latest[0]['id']}")->assertOk()
        ->assertJsonCount(5, 'data')
        ->assertJsonPath('has_more', false)
        ->assertJsonPath('data.0.body', 'Message 1');
});

it('sends, serves and deletes photos for the members only', function () {
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $outsider = messagingProfile('carol');
    $pending = conversationWith($alice, $outsider, accepted: false);
    $conversation = conversationWith($alice, $bob);

    // Not before the request is accepted.
    actingAsProfile($alice)->post("/api/v1/messages/{$pending}/attachments", ['file' => UploadedFile::fake()->image('a.jpg')], ['Accept' => 'application/json'])
        ->assertForbidden();
    actingAsProfile($alice)->post("/api/v1/messages/{$conversation}/attachments", ['file' => UploadedFile::fake()->create('a.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
        ->assertUnprocessable();

    $message = actingAsProfile($alice)->post("/api/v1/messages/{$conversation}/attachments", ['file' => UploadedFile::fake()->image('plage.jpg', 40, 40)], ['Accept' => 'application/json'])
        ->assertCreated()
        ->assertJsonPath('data.attachments.0.name', 'plage.jpg')
        ->assertJsonPath('data.attachments.0.mime_type', 'image/jpeg')
        ->json('data');
    $attachment = MessageAttachment::query()->findOrFail($message['attachments'][0]['id']);
    Storage::disk('local')->assertExists($attachment->path);

    actingAsProfile($bob)->get("/api/v1/messages/attachments/{$attachment->id}")->assertOk()->assertHeader('Content-Type', 'image/jpeg');
    actingAsProfile($outsider)->getJson("/api/v1/messages/attachments/{$attachment->id}")->assertNotFound();

    // Only its sender deletes it, for everybody, with its file.
    actingAsProfile($bob)->deleteJson("/api/v1/messages/{$conversation}/{$message['id']}")->assertNotFound();
    actingAsProfile($alice)->deleteJson("/api/v1/messages/{$conversation}/{$message['id']}")->assertOk();
    Storage::disk('local')->assertMissing($attachment->path);
    actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}")
        ->assertJsonPath('data.0.deleted', true)
        ->assertJsonPath('data.0.body', 'Ce message a été supprimé.')
        ->assertJsonCount(0, 'data.0.attachments');
    actingAsProfile($bob)->getJson("/api/v1/messages/attachments/{$attachment->id}")->assertNotFound();
});

it('creates group conversations and restricts them to their members', function () {
    $alice = messagingProfile('alice', ['name' => 'Alice']);
    $bob = messagingProfile('bob');
    messagingProfile('carol');

    $conversation = actingAsProfile($alice)->postJson('/api/v1/messages/groups', ['name' => 'Cinéma', 'usernames' => ['bob', '@Carol']])
        ->assertCreated()
        ->json('conversation_id');
    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}", ['body' => 'Bienvenue !'])->assertCreated();

    actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}")
        ->assertOk()
        ->assertJsonPath('conversation.type', 'group')
        ->assertJsonPath('conversation.name', 'Cinéma')
        ->assertJsonPath('is_admin', false)
        ->assertJsonPath('data.0.body', 'Alice a créé le groupe « Cinéma ».')
        ->assertJsonPath('data.1.body', 'Bienvenue !');

    $outsider = messagingProfile('david');
    actingAsProfile($outsider)->getJson("/api/v1/messages/{$conversation}")->assertNotFound();
    actingAsProfile($alice)->postJson('/api/v1/messages/groups', ['name' => 'Vide', 'usernames' => ['personne']])->assertUnprocessable();
});

it('lets the admins run a group', function () {
    $alice = messagingProfile('alice', ['name' => 'Alice']);
    $bob = messagingProfile('bob');
    $carol = messagingProfile('carol');
    $david = messagingProfile('david');
    $conversation = actingAsProfile($alice)->postJson('/api/v1/messages/groups', ['name' => 'Cinéma', 'usernames' => ['bob', 'carol']])->json('conversation_id');

    actingAsProfile($bob)->putJson("/api/v1/messages/{$conversation}/group", ['name' => 'Séries'])->assertForbidden();
    actingAsProfile($alice)->putJson("/api/v1/messages/{$conversation}/group", ['name' => 'Séries'])->assertOk()->assertJsonPath('name', 'Séries');

    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}/group/members", ['usernames' => ['david']])->assertOk();
    actingAsProfile($david)->getJson("/api/v1/messages/{$conversation}")->assertOk()->assertJsonPath('conversation.name', 'Séries');

    actingAsProfile($alice)->deleteJson("/api/v1/messages/{$conversation}/group/members/carol")->assertOk();
    actingAsProfile($carol)->getJson("/api/v1/messages/{$conversation}")->assertNotFound();

    // The only admin hands the group over before leaving.
    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}/group/leave")->assertUnprocessable();
    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}/group/admin/bob")->assertOk();
    actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}")->assertJsonPath('is_admin', true);
    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}/group/leave")->assertOk();
    actingAsProfile($alice)->getJson("/api/v1/messages/{$conversation}")->assertNotFound();
});

it('keeps a group when one of its members deletes its profile', function () {
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $carol = messagingProfile('carol');
    $conversation = actingAsProfile($alice)->postJson('/api/v1/messages/groups', ['name' => 'Cinéma', 'usernames' => ['bob', 'carol']])->json('conversation_id');
    actingAsProfile($bob)->postJson("/api/v1/messages/{$conversation}", ['body' => 'Salut'])->assertCreated();

    // Alice (the admin, and the lowest id) deletes her account: Bob, the oldest member, runs the group.
    Sanctum::actingAs($alice->user);
    $this->postJson('/api/v1/me/delete', [])->assertNoContent();

    expect(Conversation::query()->find($conversation))->not->toBeNull();
    actingAsProfile($bob)->getJson("/api/v1/messages/{$conversation}")->assertOk()
        ->assertJsonPath('is_admin', true)
        ->assertJsonPath('data.0.body', 'Salut');
    actingAsProfile($carol)->getJson("/api/v1/messages/{$conversation}")->assertOk()->assertJsonCount(1, 'participants');
});

it('removes message files and conversations when the account is deleted', function () {
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $conversation = conversationWith($alice, $bob);
    $id = actingAsProfile($bob)->post("/api/v1/messages/{$conversation}/attachments", ['file' => UploadedFile::fake()->image('a.png')], ['Accept' => 'application/json'])
        ->assertCreated()->json('data.attachments.0.id');
    $path = MessageAttachment::query()->findOrFail($id)->path;

    actingAsProfile($alice)->postJson('/api/v1/me/delete', [])->assertNoContent();

    Storage::disk('local')->assertMissing($path);
    expect(Conversation::query()->find($conversation))->toBeNull();
});

it('exports the messages a profile sent', function () {
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $conversation = conversationWith($alice, $bob);
    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}", ['body' => 'Mon message'])->assertCreated();
    actingAsProfile($alice)->postJson('/api/v1/messages/block/bob')->assertOk();

    $export = app(AccountExport::class)->build($alice->user);

    expect($export['profiles'][0]['messages'][0]['body'])->toBe('Mon message')
        ->and($export['profiles'][0]['message_blocks'])->toBe(['bob']);
});

it('announces the changes of a conversation on its channel', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'key']);
    Event::fake([ConversationChanged::class]);
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $conversation = conversationWith($alice, $bob);

    actingAsProfile($alice)->postJson("/api/v1/messages/{$conversation}", ['body' => 'Salut'])->assertCreated();
    actingAsProfile($bob)->postJson("/api/v1/messages/{$conversation}/read")->assertOk();

    Event::assertDispatched(ConversationChanged::class, fn (ConversationChanged $event) => $event->broadcastAs() === 'message.sent'
        && $event->broadcastWith()['body'] === 'Salut');
    Event::assertDispatched(ConversationChanged::class, fn (ConversationChanged $event) => $event->broadcastAs() === 'message.read');
    // Reading again changes nothing, so announces nothing (no loop between two apps).
    $found = app(Conversations::class)->find($bob, $conversation);
    expect(app(Conversations::class)->markRead($bob, $found))->toBe(0);
});

it('authorizes the channel of a conversation for its members only', function () {
    config(['broadcasting.default' => 'reverb', 'broadcasting.connections.reverb.key' => 'key', 'broadcasting.connections.reverb.secret' => 'secret', 'broadcasting.connections.reverb.app_id' => '1']);
    $alice = messagingProfile('alice');
    $bob = messagingProfile('bob');
    $carol = messagingProfile('carol');
    $conversation = conversationWith($alice, $bob);
    $auth = ['socket_id' => '1234.5678', 'channel_name' => "private-messages.{$conversation}"];
    // The channels were registered on the broadcaster of the tests (null): again on Reverb's.
    require base_path('routes/channels.php');

    actingAsProfile($bob)->postJson('/api/broadcasting/auth', $auth)->assertOk()->assertJsonStructure(['auth']);
    actingAsProfile($carol)->postJson('/api/broadcasting/auth', $auth)->assertForbidden();
});
