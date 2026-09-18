<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;


class UserFactory extends Factory
{

    /** @var class-string<User> */
    protected $model = User::class;


    public function definition(): array
    {
        return [
            'is_owner' => false,
            'is_enabled' => true,
            'name' => fake()->name(),
            'client_id' => ClientFactory::new(),
            'email' => fake()->unique()->safeEmail(),
        ];
    }


    public function owner(): static
    {
        return $this->state([
            'is_owner' => true,
            'google_id' => fake()->unique()->numerify('#####################'),
        ]);
    }


    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }

}
