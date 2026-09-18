<?php

namespace App\Repositories;

use App\Models\Administrator;
use Illuminate\Database\Eloquent\Collection;


class AdministratorRepository
{


    public function create(array $attributes): Administrator
    {
        return Administrator::query()->create($attributes);
    }


    public function update(int $administratorId, array $attributes): Administrator
    {
        $administrator = Administrator::query()->findOrFail($administratorId);
        $administrator->fill($attributes);
        $administrator->save();

        return $administrator;
    }


    public function find(int $administratorId): ?Administrator
    {
        return Administrator::query()->find($administratorId);
    }


    public function list(): Collection
    {
        return Administrator::query()->orderBy('id')->get();
    }

}
