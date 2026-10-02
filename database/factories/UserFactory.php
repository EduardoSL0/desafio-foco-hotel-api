<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Hotel;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'password' => 'password',
            'role' => UserRole::Receptionist,
            'hotel_id' => null,
        ];
    }

    public function admin(): static
    {
        return $this->state(['role' => UserRole::Admin, 'hotel_id' => null]);
    }

    public function manager(Hotel $hotel): static
    {
        return $this->state(['role' => UserRole::Manager, 'hotel_id' => $hotel->id]);
    }

    public function receptionist(Hotel $hotel): static
    {
        return $this->state(['role' => UserRole::Receptionist, 'hotel_id' => $hotel->id]);
    }
}
