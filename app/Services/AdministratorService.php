<?php

namespace App\Services;

use App\Models\Administrator;
use Illuminate\Database\Eloquent\Collection;
use App\Repositories\AdministratorRepository;


class AdministratorService
{

    private AdministratorRepository $administratorRepository;


    public function __construct(AdministratorRepository $administratorRepository)
    {
        $this->administratorRepository = $administratorRepository;
    }


    public function create(array $attributes): Administrator
    {
        return $this->administratorRepository->create($attributes);
    }


    public function update(int $administratorId, array $attributes): Administrator
    {
        return $this->administratorRepository->update($administratorId, $attributes);
    }


    public function find(int $administratorId): ?Administrator
    {
        return $this->administratorRepository->find($administratorId);
    }


    public function list(): Collection
    {
        return $this->administratorRepository->list();
    }

}
