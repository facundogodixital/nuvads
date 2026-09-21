<?php

namespace App\Http\Controllers\API;

use App\Services\BrandService;
use Illuminate\Http\JsonResponse;
use App\Http\Requests\AuthenticatedRequest;
use App\Http\Requests\Brands\UpdateBrandRequest;


class BrandController extends ApiController
{


    public function find(AuthenticatedRequest $request): JsonResponse
    {
        return $this->respond($request->brand);
    }


    public function update(UpdateBrandRequest $request): JsonResponse
    {
        $attributes = $request->validated();
        $brand = resolve(BrandService::class)->update($request->brand, $attributes);
        return $this->respond($brand);
    }

}
