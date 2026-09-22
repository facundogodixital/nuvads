<?php

namespace App\Repositories;

use App\Models\Brand;
use App\Models\Client;


class BrandRepository
{


    public function create(Client $client, array $attributes): Brand
    {
        $attributes['client_id'] = $client->id;

        return Brand::query()->create($attributes);
    }


    public function update(Brand $brand, array $attributes): Brand
    {
        $brand->fill($attributes);
        $brand->save();

        return $brand;
    }


    public function find(int $brandId): Brand
    {
        return Brand::query()->findOrFail($brandId);
    }

}
