<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;


class DatabaseSeeder extends Seeder
{

    use WithoutModelEvents;


    /**
     * Carga los datos iniciales de la aplicación.
     */
    public function run(): void
    {
        $this->call(ContentTypeSeeder::class);
    }

}
