<?php

namespace Database\Factories;

use App\Enums\ItemType;
use App\Models\Profile;
use App\Models\ProfileItem;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileItem>
 */
class ProfileItemFactory extends Factory
{
    public function definition(): array
    {
        $id = fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'profile_id' => Profile::factory(),
            'source' => 'tmdb',
            'item_id' => "movie:{$id}",
            'type' => ItemType::Movie,
            'title' => fake()->sentence(3),
            'year' => fake()->numberBetween(1950, 2026),
            'revision' => fn (array $attributes) => Profile::whereKey($attributes['profile_id'])->value('sync_revision') + 1,
        ];
    }

    public function configure(): static
    {
        // Keep the profile's sync cursor consistent with the items created in tests.
        return $this->afterCreating(function (ProfileItem $item) {
            Profile::withoutTimestamps(fn () => $item->profile->forceFill(['sync_revision' => max($item->profile->sync_revision, $item->revision)])->save());
        });
    }

    public function favorite(string $at = '2026-01-01 10:00:00'): static
    {
        return $this->state(fn () => ['is_favorite' => true, 'favorited_at' => $at, 'favorite_changed_at' => $at]);
    }

    public function inProgress(int $positionMs = 600_000, int $durationMs = 6_000_000, string $at = '2026-01-01 10:00:00'): static
    {
        return $this->state(fn () => [
            'position_ms' => $positionMs, 'duration_ms' => $durationMs,
            'last_engaged_at' => $at, 'progress_changed_at' => $at,
        ]);
    }

    public function watched(string $at = '2026-01-01 10:00:00'): static
    {
        return $this->state(fn () => ['is_watched' => true, 'watched_at' => $at, 'watched_changed_at' => $at]);
    }
}
