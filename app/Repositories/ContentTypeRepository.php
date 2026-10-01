<?php

namespace App\Repositories;

use App\Models\ContentType;
use Illuminate\Database\Eloquent\Collection;


class ContentTypeRepository
{


    public function create(array $attributes): ContentType
    {
        return ContentType::query()->create($attributes);
    }


    public function list(): Collection
    {
        return ContentType::query()->orderBy('id')->get();
    }


    // Los tipos rotados conservan su key, pero el soft delete los deja afuera: queda solo el activo.
    public function findOneByKey(string $key): ?ContentType
    {
        return ContentType::query()->where('key', $key)->first();
    }

}
