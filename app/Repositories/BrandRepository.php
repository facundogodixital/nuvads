<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\Client;


class BrandRepository
{


    public function create(Client $client, string $name): Brand
    {
        return Brand::query()->create([
            'name' => $name,
            'client_id' => $client->id,
        ]);
    }

}
