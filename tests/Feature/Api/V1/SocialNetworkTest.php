<?php

use App\Models\DiaryEntry;
use App\Models\Profile;
use App\Models\ProfileList;
use App\Models\Report;
use App\Models\SocialActivity;
use App\Models\SocialNotification;
use App\Models\TmdbRating;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Laravel\Sanctum\Sanctum;
use Tests\Feature\Api\V1\Catalog\TmdbFixtures as Tmdb;

/*
 * The social network of movies and TV shows (docs/social.md, docs/moderation.md): lists and top
 * 10s, comments, spoilers, diary, feed, the social part of a title page, tastes in common, blocks,
 * reports, titles sent in a message and the profile's settings.
 */

beforeEach(function () {
    config([
        'services.tmdb.token' => 'test-token',
        'services.tmdb.api_key' => null,
        'services.tmdb.base_url' => 'https://api.themoviedb.org/3',
    ]);
    Http::preventStrayRequests();
    // Any movie or TV show: « Film 550 »… (Fight Club and Game of Thrones for 550 and 1399).
    Http::fake(function (Request $request) {
        if (preg_match('#/3/movie/(\d+)\?#', $request->url(), $m) === 1) {
            $id = (int) $m[1];

            return Http::response(['id' => $id, 'title' => $id === 550 ? 'Fight Club' : "Film {$id}", 'poster_path' => "/p{$id}.jpg"] + Tmdb::movieDetails());
        }
        if (preg_match('#/3/tv/(\d+)\?#', $request->url(), $m) === 1) {
            return Http::response(['id' => (int) $m[1]] + Tmdb::showDetails());
        }

        return Http::response(['results' => []]);
    });
});

function socialMember(string $username, array $attributes = []): Profile
{
    return Profile::factory()->for(User::factory())->create(['username' => $username, ...$attributes]);
}

/** Acts as [profile]: its account signed in, its id in X-Profile-Id. */
function as_(Profile $profile): mixed
{
    Sanctum::actingAs($profile->user);

    return test()->withHeader('X-Profile-Id', (string) $profile->id);
}

function follows(Profile $follower, Profile $followed): void
{
    DB::table('profile_follows')->insert([
        'follower_profile_id' => $follower->id, 'followed_profile_id' => $followed->id,
        'accepted_at' => now(), 'created_at' => now(), 'updated_at' => now(),
    ]);
}

function socialReview(Profile $author, int $movie, float $value, ?string $comment = null, array $attributes = []): TmdbRating
{
    return TmdbRating::forceCreate($attributes + [
        'profile_id' => $author->id, 'target' => "movie/{$movie}", 'type' => 'movie', 'tmdb_id' => $movie,
        'value' => $value, 'comment' => $comment, 'title' => "Film {$movie}", 'rated_at' => now(),
    ]);
}

/** Marks a movie watched by the synchronisation, as the player and the « Vu » button do. */
function watch(Profile $profile, int $movie, string $at = '2026-11-20T20:00:00Z'): void
{
    as_($profile)->postJson("/api/v1/profiles/{$profile->id}/sync", ['changes' => [[
        'source' => 'tmdb', 'item_id' => "movie:{$movie}", 'type' => 'movie', 'title' => "Film {$movie}",
        'watched' => ['value' => true, 'at' => $at],
    ]]])->assertOk();
}

describe('lists and top 10s', function () {
    it('creates a top 10 with its titles, in order, and caps it at 10', function () {
        $lea = socialMember('lea');

        $list = as_($lea)->postJson('/api/v1/social/lists', [
            'title' => 'Mon top 10 de 2026', 'kind' => 'top', 'items' => ['movie:551', 'movie:550', 'tv:1399', 'movie:550'],
        ])->assertCreated()
            ->assertJsonPath('data.kind', 'top')
            ->assertJsonPath('data.items_count', 3)
            ->assertJsonPath('data.items.*.item_id', ['movie:551', 'movie:550', 'tv:1399'])
            ->assertJsonPath('data.items.1.title', 'Fight Club')
            ->assertJsonPath('data.items.2.title', 'Game of Thrones')
            ->assertJsonPath('data.cover.0', 'https://image.tmdb.org/t/p/w342/p551.jpg')
            ->json('data');
        // In the feed once it has a title.
        expect(SocialActivity::query()->where('profile_list_id', $list['id'])->count())->toBe(1);

        as_($lea)->postJson('/api/v1/social/lists', ['title' => 'Trop', 'kind' => 'top', 'items' => array_map(fn ($i) => "movie:{$i}", range(1, 11))])
            ->assertUnprocessable()->assertJsonPath('message', 'Un top 10 contient 10 titres au plus.');

        // Reordered, then a title added at the end and one removed.
        as_($lea)->putJson("/api/v1/social/lists/{$list['id']}", ['title' => 'Mon top', 'items' => ['tv:1399', 'movie:551']])
            ->assertOk()->assertJsonPath('data.items.*.item_id', ['tv:1399', 'movie:551'])->assertJsonPath('data.title', 'Mon top');
        as_($lea)->postJson("/api/v1/social/lists/{$list['id']}/items", ['item' => 'movie:552'])
            ->assertOk()->assertJsonPath('data.items.*.item_id', ['tv:1399', 'movie:551', 'movie:552']);
        as_($lea)->deleteJson("/api/v1/social/lists/{$list['id']}/items/movie/551")
            ->assertOk()->assertJsonPath('data.items.*.position', [0, 1])->assertJsonPath('data.items_count', 2);

        // « Ajouter à une liste »: which lists have a title.
        as_($lea)->getJson('/api/v1/social/lists?item=movie:552')->assertOk()->assertJsonPath('data.0.contains', true);
        as_($lea)->getJson('/api/v1/social/lists?item=movie:550')->assertOk()->assertJsonPath('data.0.contains', false);
    });

    it('is seen by those who see the account, liked and commented', function () {
        $lea = socialMember('lea', ['is_private' => true]);
        $max = socialMember('max');
        $list = ProfileList::query()->create(['profile_id' => $lea->id, 'title' => 'Horreur', 'kind' => 'list', 'items_count' => 0]);

        as_($max)->getJson("/api/v1/social/lists/{$list->id}")->assertForbidden();
        as_($max)->putJson("/api/v1/social/lists/{$list->id}/like")->assertForbidden();
        follows($max, $lea);

        as_($max)->putJson("/api/v1/social/lists/{$list->id}/like")->assertOk()->assertJsonPath('likes_count', 1);
        as_($max)->postJson("/api/v1/social/lists/{$list->id}/comments", ['body' => '  Super liste !  '])
            ->assertCreated()->assertJsonPath('data.body', 'Super liste !')->assertJsonPath('data.can_delete', true);
        expect(SocialNotification::query()->where('profile_id', $lea->id)->pluck('type')->sort()->values()->all())
            ->toBe(['list_comment', 'list_like']);
        as_($max)->getJson("/api/v1/social/lists/{$list->id}")->assertOk()
            ->assertJsonPath('data.liked', true)->assertJsonPath('data.comments_count', 1)->assertJsonPath('data.is_mine', false);

        // Not one's own list; nobody else changes it.
        as_($lea)->putJson("/api/v1/social/lists/{$list->id}/like")->assertUnprocessable();
        as_($max)->putJson("/api/v1/social/lists/{$list->id}", ['title' => 'À moi'])->assertNotFound();
        as_($max)->getJson('/api/v1/social/profiles/lea/lists')->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('reviews: spoilers and comments', function () {
    it('keeps the spoilers flag and lets the review be commented', function () {
        $lea = socialMember('lea');
        $max = socialMember('max');
        $id = as_($lea)->putJson("/api/v1/profiles/{$lea->id}/tmdb/ratings/movie/550", ['value' => 9, 'comment' => 'La fin !', 'contains_spoilers' => true])
            ->assertOk()->assertJsonPath('data.contains_spoilers', true)->json('data.review_id');

        $comment = as_($max)->postJson("/api/v1/social/reviews/{$id}/comments", ['body' => 'Pas d’accord', 'contains_spoilers' => true])
            ->assertCreated()->json('data');
        as_($max)->getJson("/api/v1/social/reviews/{$id}")->assertOk()
            ->assertJsonPath('data.author.username', 'lea')
            ->assertJsonPath('data.contains_spoilers', true)
            ->assertJsonPath('data.comments_count', 1);
        as_($max)->getJson("/api/v1/social/reviews/{$id}/comments")->assertOk()
            ->assertJsonPath('data.0.body', 'Pas d’accord')->assertJsonPath('data.0.contains_spoilers', true)
            ->assertJsonPath('has_more', false);
        expect(SocialNotification::query()->where('profile_id', $lea->id)->value('type'))->toBe('review_comment');

        // Deleted by the author of the review; a third profile can't.
        $zoe = socialMember('zoe');
        as_($zoe)->deleteJson("/api/v1/social/comments/{$comment['id']}")->assertForbidden();
        as_($lea)->deleteJson("/api/v1/social/comments/{$comment['id']}")->assertNoContent();
        as_($max)->getJson("/api/v1/social/reviews/{$id}/comments")->assertOk()->assertJsonCount(0, 'data');
    });
});

describe('the diary', function () {
    it('logs a title that becomes watched, then another viewing, and counts the year', function () {
        $lea = socialMember('lea');
        watch($lea, 550, '2026-03-10T20:00:00Z');
        watch($lea, 550, '2026-03-10T20:00:00Z');

        $entry = DiaryEntry::query()->sole();
        expect($entry->title)->toBe('Fight Club')->and($entry->runtime)->toBe(139)->and($entry->rewatch)->toBeFalse()
            ->and($entry->genres)->toBe(['Drame']);

        as_($lea)->postJson("/api/v1/profiles/{$lea->id}/diary", ['item' => 'movie:550', 'watched_at' => '2026-06-01T21:00:00Z'])
            ->assertCreated()->assertJsonPath('data.rewatch', true)->assertJsonPath('data.item_id', 'movie:550');
        socialReview($lea, 550, 8.5);

        as_($lea)->getJson('/api/v1/social/profiles/lea/diary')->assertOk()
            ->assertJsonPath('data.0.watched_at', '2026-06-01T21:00:00+00:00')
            ->assertJsonPath('data.0.rating', 8.5)
            ->assertJsonCount(2, 'data');
        as_($lea)->getJson('/api/v1/social/profiles/lea/stats?year=2026')->assertOk()
            ->assertJsonPath('data.entries_count', 2)
            ->assertJsonPath('data.movies_count', 2)
            ->assertJsonPath('data.rewatches_count', 1)
            ->assertJsonPath('data.minutes', 278)
            ->assertJsonPath('data.months.2', 1)
            ->assertJsonPath('data.genres.0', ['name' => 'Drame', 'count' => 2])
            ->assertJsonPath('data.best_rated.0.value', 8.5);

        as_($lea)->deleteJson("/api/v1/profiles/{$lea->id}/diary/{$entry->id}")->assertNoContent();
        expect(DiaryEntry::query()->count())->toBe(1);
    });
});

describe('the feed', function () {
    it('shows what the followed profiles did, groups the watched titles and leaves out the muted ones', function () {
        [$me, $lea, $max, $zoe] = [socialMember('me'), socialMember('lea'), socialMember('max'), socialMember('zoe')];
        follows($me, $lea);
        follows($me, $max);
        watch($lea, 600);
        watch($max, 600);
        watch($zoe, 600);
        // Lea also reviewed what she watched: the review only.
        watch($lea, 700);
        as_($lea)->putJson("/api/v1/profiles/{$lea->id}/tmdb/ratings/movie/700", ['value' => 7, 'comment' => 'Bien'])->assertOk();
        as_($me)->postJson('/api/v1/social/lists', ['title' => 'Ma liste', 'kind' => 'list', 'items' => ['movie:550']])->assertCreated();

        $feed = as_($me)->getJson('/api/v1/social/feed')->assertOk()->json();
        expect(array_column($feed['data'], 'kind'))->toBe(['list', 'review', 'watched'])
            ->and($feed['data'][1]['review']['author']['username'])->toBe('lea')
            ->and($feed['data'][2]['title']['item_id'])->toBe('movie:600')
            ->and($feed['data'][2]['profiles_count'])->toBe(2)
            ->and($feed['next'])->toBeNull();

        as_($me)->postJson('/api/v1/social/profiles/lea/mute')->assertOk();
        $kinds = array_column(as_($me)->getJson('/api/v1/social/feed')->json('data'), 'kind');
        expect($kinds)->toBe(['list', 'watched']);
    });

    it('« Pour toi »: the reviews and lists of the whole network, the most interesting first', function () {
        [$me, $lea, $max, $zoe] = [socialMember('me'), socialMember('lea'), socialMember('max'), socialMember('zoe')];
        $private = socialMember('ana', ['is_private' => true]);
        // Not followed: Pour toi shows them anyway.
        as_($lea)->putJson("/api/v1/profiles/{$lea->id}/tmdb/ratings/movie/700", ['value' => 7])->assertOk();
        as_($max)->putJson("/api/v1/profiles/{$max->id}/tmdb/ratings/movie/701", ['value' => 9, 'comment' => 'Une claque.'])->assertOk();
        $liked = TmdbRating::query()->where('profile_id', $max->id)->firstOrFail();
        DB::table('review_likes')->insert([
            ['profile_id' => $lea->id, 'tmdb_rating_id' => $liked->id, 'created_at' => now()],
            ['profile_id' => $zoe->id, 'tmdb_rating_id' => $liked->id, 'created_at' => now()],
        ]);
        as_($zoe)->postJson('/api/v1/social/lists', ['title' => 'Cultes', 'kind' => 'list', 'items' => ['movie:550']])->assertCreated();
        // Neither a private account I don't follow, nor mine, nor a watched title.
        as_($private)->putJson("/api/v1/profiles/{$private->id}/tmdb/ratings/movie/702", ['value' => 8, 'comment' => 'Privé'])->assertOk();
        as_($me)->putJson("/api/v1/profiles/{$me->id}/tmdb/ratings/movie/703", ['value' => 8, 'comment' => 'Le mien'])->assertOk();
        watch($lea, 600);

        $feed = as_($me)->getJson('/api/v1/social/feed/for-you')->assertOk()->json();
        // The liked, written review first; then the list, then the stars alone.
        expect(array_map(fn ($item) => $item['review']['author']['username'] ?? $item['list']['author']['username'], $feed['data']))
            ->toBe(['max', 'zoe', 'lea'])
            ->and(array_column($feed['data'], 'kind'))->toBe(['review', 'list', 'review'])
            ->and($feed['next'])->toBeNull();

        // Muted or blocked: left out.
        as_($me)->postJson('/api/v1/social/profiles/max/mute')->assertOk();
        as_($me)->postJson('/api/v1/social/profiles/zoe/block')->assertOk();
        expect(array_column(as_($me)->getJson('/api/v1/social/feed/for-you')->json('data'), 'kind'))->toBe(['review']);
    });

    it('« Pour toi »: pages of 20, several voices on a page', function () {
        $me = socialMember('me');
        $busy = socialMember('busy');
        $other = socialMember('other');
        foreach (range(1, 22) as $i) {
            as_($busy)->putJson("/api/v1/profiles/{$busy->id}/tmdb/ratings/movie/".(800 + $i), ['value' => 8, 'comment' => "Avis {$i}"])->assertOk();
        }
        as_($other)->putJson("/api/v1/profiles/{$other->id}/tmdb/ratings/movie/900", ['value' => 6])->assertOk();

        $first = as_($me)->getJson('/api/v1/social/feed/for-you')->assertOk()->json();
        expect($first['data'])->toHaveCount(20)
            ->and($first['next'])->toBe(2)
            // The other voice comes high, despite the stars alone.
            ->and(array_search('other', array_map(fn ($item) => $item['review']['author']['username'], $first['data']), true))
            ->toBeLessThan(5);
        $second = as_($me)->getJson('/api/v1/social/feed/for-you?page=2')->assertOk()->json();
        expect($second['data'])->toHaveCount(3)->and($second['next'])->toBeNull();
    });
});

describe('the title page', function () {
    it('shows the followed profiles who watched it, their average, the popular reviews and the lists', function () {
        [$me, $lea, $max, $zoe] = [socialMember('me'), socialMember('lea'), socialMember('max'), socialMember('zoe')];
        follows($me, $lea);
        follows($me, $max);
        watch($lea, 550);
        socialReview($max, 550, 6);
        $popular = socialReview($zoe, 550, 9, 'Chef-d’œuvre');
        DB::table('review_likes')->insert(['profile_id' => $lea->id, 'tmdb_rating_id' => $popular->id, 'created_at' => now()]);
        socialReview(socialMember('ana', ['is_private' => true]), 550, 2, 'Privé');
        as_($lea)->postJson('/api/v1/social/lists', ['title' => 'Cultes', 'kind' => 'list', 'items' => ['movie:550']])->assertCreated();

        as_($me)->getJson('/api/v1/social/titles/movie/550')->assertOk()
            ->assertJsonPath('data.friends.*.username', ['max', 'lea'])
            ->assertJsonPath('data.friends.0.rating', 6)
            ->assertJsonPath('data.friends.1.rating', null)
            ->assertJsonPath('data.friends_average', 6)
            ->assertJsonPath('data.reviews.*.author.username', ['zoe'])
            ->assertJsonPath('data.reviews.0.likes_count', 1)
            ->assertJsonPath('data.reviews_count', 1)
            ->assertJsonPath('data.lists.0.title', 'Cultes');
    });
});

describe('tastes, blocks and the member page', function () {
    it('computes the tastes in common and suggests accounts', function () {
        [$me, $lea, $max] = [socialMember('me'), socialMember('lea'), socialMember('max')];
        foreach ([550, 551, 552, 553, 554] as $i => $movie) {
            socialReview($me, $movie, 8);
            socialReview($lea, $movie, $i === 0 ? 3 : 8);
        }
        follows($me, $max);
        follows($max, $lea);

        as_($me)->getJson('/api/v1/social/profiles/lea')->assertOk()
            ->assertJsonPath('data.compatibility', ['score' => 80, 'common' => 5]);
        as_($me)->getJson('/api/v1/social/suggestions')->assertOk()
            ->assertJsonPath('data.0.username', 'lea')
            ->assertJsonPath('data.0.reason', 'Suivi par un de vos abonnements')
            ->assertJsonPath('data.0.compatibility', 80);
    });

    it('blocks everywhere', function () {
        [$me, $lea] = [socialMember('me'), socialMember('lea')];
        follows($me, $lea);
        follows($lea, $me);
        $review = socialReview($lea, 550, 7, 'Bof');

        as_($me)->postJson('/api/v1/social/profiles/lea/block')->assertOk();
        expect(DB::table('profile_follows')->count())->toBe(0);
        as_($me)->getJson('/api/v1/social/profiles/lea')->assertOk()->assertJsonPath('data.is_blocked', true)->assertJsonPath('data.can_view', false);
        as_($lea)->getJson('/api/v1/social/profiles/me')->assertNotFound();
        as_($lea)->postJson('/api/v1/social/profiles/me/follow')->assertForbidden();
        as_($me)->getJson("/api/v1/social/reviews/{$review->id}")->assertNotFound();
        as_($me)->getJson('/api/v1/social/search?query=le')->assertOk()->assertJsonCount(0, 'data');
        as_($me)->getJson('/api/v1/social/hidden')->assertOk()->assertJsonPath('blocked.0.username', 'lea');

        as_($me)->deleteJson('/api/v1/social/profiles/lea/block')->assertOk();
        as_($me)->getJson("/api/v1/social/reviews/{$review->id}")->assertOk();
    });

    it('shows the 4 favorites, the rating prompt setting and the first launch', function () {
        $lea = socialMember('lea');
        as_($lea)->putJson("/api/v1/profiles/{$lea->id}", [
            'pinned_titles' => ['tv:1399', 'movie:550'],
            'rate_after_watching' => false,
            'onboarded' => true,
        ])->assertOk()
            ->assertJsonPath('data.pinned_titles.*.item_id', ['tv:1399', 'movie:550'])
            ->assertJsonPath('data.rate_after_watching', false)
            ->assertJsonPath('data.onboarded', true);
        as_($lea)->putJson("/api/v1/profiles/{$lea->id}", ['pinned_titles' => ['movie:1', 'movie:2', 'movie:3', 'movie:4', 'movie:5']])
            ->assertUnprocessable();

        as_(socialMember('max'))->getJson('/api/v1/social/profiles/lea')->assertOk()
            ->assertJsonPath('data.pinned.1.title', 'Fight Club')
            ->assertJsonPath('data.pinned.1.poster_url', 'https://image.tmdb.org/t/p/w342/p550.jpg');
        // A new profile has its first launch to do.
        expect(socialMember('new')->onboarded_at)->toBeNull();
    });
});

describe('reports and moderation', function () {
    it('reports what one sees, hides it and tells who is concerned', function () {
        [$me, $lea] = [socialMember('me'), socialMember('lea')];
        $review = socialReview($lea, 550, 1, 'Insultes');

        as_($me)->postJson('/api/v1/social/reports', ['type' => 'review', 'id' => $review->id, 'reason' => 'harassment'])->assertCreated();
        as_($me)->postJson('/api/v1/social/reports', ['type' => 'review', 'id' => $review->id, 'reason' => 'hate', 'details' => 'Encore'])->assertCreated();
        expect(Report::query()->sole()->reason)->toBe('hate');
        as_($lea)->postJson('/api/v1/social/reports', ['type' => 'review', 'id' => $review->id, 'reason' => 'spam'])->assertUnprocessable();
        as_($me)->postJson('/api/v1/social/reports', ['type' => 'list', 'id' => 999, 'reason' => 'spam'])->assertNotFound();

        $this->artisan('vixeo:reports')->assertSuccessful()->expectsOutputToContain('Insultes');
        $this->artisan('vixeo:moderate', ['report' => Report::query()->value('id'), 'action' => 'hide', '--reason' => 'Propos insultants'])->assertSuccessful();

        as_($me)->getJson("/api/v1/social/reviews/{$review->id}")->assertNotFound();
        as_($me)->getJson('/api/v1/social/titles/movie/550')->assertOk()->assertJsonPath('data.reviews_count', 0);
        expect(SocialNotification::query()->where('profile_id', $lea->id)->value('data'))
            ->toBe(['text' => 'Votre avis sur Film 550 a été masqué par la modération : Propos insultants'])
            ->and(SocialNotification::query()->where('profile_id', $me->id)->value('type'))->toBe('moderation');

        $this->artisan('vixeo:moderate', ['report' => Report::query()->value('id'), 'action' => 'restore', '--reason' => 'Après appel'])->assertSuccessful();
        as_($me)->getJson("/api/v1/social/reviews/{$review->id}")->assertOk();
    });
});

describe('sent in a message', function () {
    it('sends a title, a review or a list as a card', function () {
        [$me, $lea] = [socialMember('me'), socialMember('lea')];
        $conversation = as_($me)->postJson('/api/v1/messages/with/lea')->assertSuccessful()->json('conversation_id');
        $review = socialReview($lea, 550, 9, 'À voir');

        as_($me)->postJson("/api/v1/messages/{$conversation}", ['share' => ['type' => 'title', 'id' => 'movie:550']])->assertCreated()
            ->assertJsonPath('data.shared.type', 'title')
            ->assertJsonPath('data.shared.title', 'Fight Club')
            ->assertJsonPath('data.body', '');
        as_($me)->postJson("/api/v1/messages/{$conversation}", ['body' => 'Lis ça', 'share' => ['type' => 'review', 'id' => (string) $review->id]])->assertCreated()
            ->assertJsonPath('data.shared.author.username', 'lea')
            ->assertJsonPath('data.shared.excerpt', 'À voir');
        as_($me)->postJson("/api/v1/messages/{$conversation}", ['share' => ['type' => 'list', 'id' => '999']])->assertNotFound();
    });
});
