<?php

namespace Database\Factories;

use App\Models\Profile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Profile>
 */
class ProfileFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->unique()->firstName(),
            'avatar' => null,
            'is_kid' => false,
            'max_age_rating' => null,
            'pin' => null,
            'position' => 0,
        ];
    }

    public function kid(int $maxAge = 12): static
    {
        return $this->state(fn () => ['is_kid' => true, 'max_age_rating' => $maxAge]);
    }

    public function withPin(string $pin = '1234'): static
    {
        return $this->state(fn () => ['pin' => $pin]);
    }
}
