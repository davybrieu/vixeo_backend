<?php

namespace Database\Factories;

use App\Models\Profile;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Profile> */
class ProfileFactory extends Factory
{
    public function definition(): array
    {
        $name = fake()->unique()->firstName();

        return [
            'name' => $name,
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'username' => fake()->unique()->userName(),
            'avatar' => null,
            'position' => 0,
            'registration_completed_at' => now(),
            'onboarded_at' => now(),
        ];
    }
}
