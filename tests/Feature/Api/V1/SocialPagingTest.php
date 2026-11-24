<?php

use App\Models\Profile;
use App\Models\ProfileList;
use App\Models\User;
use App\Services\Social\Follows;
use Laravel\Sanctum\Sanctum;

// A member's lists come 20 at a time: the apps load the next page as the page scrolls (developer's
// request, 24/11/2026: no big requests).

function pagingMember(string $username): Profile
{
    return Profile::factory()->for(User::factory()->create())->create(['username' => $username]);
}

it('gives a member\'s lists 20 at a time', function () {
    $lea = pagingMember('lea');
    foreach (range(1, 25) as $i) {
        app(Follows::class)->follow(pagingMember("fan_{$i}"), $lea);
    }
    $max = pagingMember('max');
    Sanctum::actingAs($max->user);

    $first = $this->withHeader('X-Profile-Id', (string) $max->id)
        ->getJson('/api/v1/social/profiles/lea/followers')
        ->assertOk()
        ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 2]);
    expect($first->json('data'))->toHaveCount(20);

    $second = $this->withHeader('X-Profile-Id', (string) $max->id)
        ->getJson('/api/v1/social/profiles/lea/followers?page=2')
        ->assertOk()
        ->assertJsonPath('meta.current_page', 2);
    expect($second->json('data'))->toHaveCount(5)
        ->and(array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')))->toBe([]);
});

it('gives my lists 20 at a time, with the ones that have a title', function () {
    $lea = pagingMember('lea');
    foreach (range(1, 23) as $i) {
        ProfileList::query()->create(['profile_id' => $lea->id, 'title' => "Liste {$i}", 'items_count' => 0]);
    }
    Sanctum::actingAs($lea->user);

    $first = $this->withHeader('X-Profile-Id', (string) $lea->id)
        ->getJson('/api/v1/social/lists?item=movie:550')
        ->assertOk()
        ->assertJsonPath('meta', ['current_page' => 1, 'last_page' => 2])
        ->assertJsonPath('data.0.contains', false);
    expect($first->json('data'))->toHaveCount(20);

    $second = $this->withHeader('X-Profile-Id', (string) $lea->id)->getJson('/api/v1/social/lists?page=2')->assertOk();
    expect($second->json('data'))->toHaveCount(3);
});
