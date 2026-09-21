<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Client;
use App\Repositories\BrandRepository;


class BrandService
{

    private BrandRepository $brandRepository;


    public function __construct(BrandRepository $brandRepository)
    {
        $this->brandRepository = $brandRepository;
    }


    public function create(Client $client, array $attributes): Brand
    {
        return $this->brandRepository->create($client, $attributes);
    }


    public function update(Brand $brand, array $attributes): Brand
    {
        return $this->brandRepository->update($brand, $attributes);
    }

}
