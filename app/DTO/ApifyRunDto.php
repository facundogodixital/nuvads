<?php

namespace App\DTO;

class ApifyRunDto
{


    public function __construct(
        public readonly string $id,
        public readonly string $status,
        public readonly ?string $datasetId,
    ) {}

}
