<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->userName(),
            'name' => fake()->name(),
            'phone' => fake()->unique()->numerify('05########'),
            'email' => fake()->unique()->safeEmail(),
            'avatar_url' => null,
            'residence' => 'Baghdad',
            'province' => 'Baghdad',
            'birth_date' => '1990-01-01',
            'role' => UserRole::Rep,
            'status' => UserStatus::Active,
            'password' => 'password',
        ];
    }
}
