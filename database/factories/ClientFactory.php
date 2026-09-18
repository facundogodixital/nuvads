<?php

namespace Database\Factories;

use App\Models\Client;
use Illuminate\Database\Eloquent\Factories\Factory;


class ClientFactory extends Factory
{

    /** @var class-string<Client> */
    protected $model = Client::class;


    public function definition(): array
    {
        return [
            'country_code' => 'AR',
            'is_enabled' => true,
            'name' => fake()->company(),
            'timezone' => 'America/Argentina/Buenos_Aires',
            'login_identifier' => fake()->unique()->uuid(),
        ];
    }


    public function disabled(): static
    {
        return $this->state(['is_enabled' => false]);
    }

}
