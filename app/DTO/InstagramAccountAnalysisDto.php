<?php

namespace App\DTO;

class InstagramAccountAnalysisDto
{


    /**
     * mergedBrandFields: los cinco campos de la marca mezclados con su texto actual, cada uno string o null.
     *
     * @param  array<string, ?string>  $mergedBrandFields
     * @param  list<string>  $insights
     */
    public function __construct(
        public readonly array $mergedBrandFields,
        public readonly string $summary,
        public readonly array $insights,
    ) {}

}
