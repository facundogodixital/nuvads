<?php

namespace App\Services;

use App\Models\ContentType;
use App\Repositories\ContentTypeRepository;
use Illuminate\Database\Eloquent\Collection;


class ContentTypeService
{

    private ContentTypeRepository $contentTypeRepository;


    public function __construct(ContentTypeRepository $contentTypeRepository)
    {
        $this->contentTypeRepository = $contentTypeRepository;
    }


    public function create(array $attributes): ContentType
    {
        return $this->contentTypeRepository->create($attributes);
    }


    public function list(): Collection
    {
        return $this->contentTypeRepository->list();
    }


    public function findOneByKey(string $key): ?ContentType
    {
        return $this->contentTypeRepository->findOneByKey($key);
    }

}
