<?php

namespace App\Repositories;

use App\Models\Idea;
use App\Models\Brand;
use Illuminate\Database\Eloquent\Collection;


class IdeaRepository
{


    public function create(Brand $brand, array $attributes): Idea
    {
        $attributes['brand_id'] = $brand->id;
        $attributes['client_id'] = $brand->client_id;

        return Idea::query()->create($attributes);
    }


    public function list(Brand $brand): Collection
    {
        return Idea::query()
            ->where('brand_id', $brand->id)
            ->where('client_id', $brand->client_id)
            ->orderBy('id')
            ->get();
    }

}
