<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\ProfileStream;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ProfileStream>
 */
class ProfileStreamFactory extends Factory
{
    public function definition(): array
    {
        $id = fake()->unique()->numberBetween(1, 1_000_000);

        return [
            'profile_id' => Profile::factory(),
            'source' => 'tmdb',
            'item_id' => "movie:{$id}",
            'stream_id' => "https://voe.sx/e/{$id}",
        ];
    }

    public function favorite(string $at = '2026-01-01 10:00:00'): static
    {
        return $this->state(fn () => ['is_favorite' => true, 'favorited_at' => $at]);
    }

    public function worked(string $at = '2026-01-01 10:00:00'): static
    {
        return $this->state(fn () => ['last_worked_at' => $at]);
    }
}
