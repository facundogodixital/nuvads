<?php

namespace App\DTO;

class MetaAdsAnalysisDto
{


    /**
     * matchesBrand: false si el modelo ve que la fuente es de otro negocio; entonces no se guardan los campos.
     * mergedBrandFields: los ocho campos de la marca mezclados con su texto actual, cada uno string o null.
     *
     * @param  array<string, ?string>  $mergedBrandFields
     * @param  list<string>  $insights
     */
    public function __construct(
        public readonly bool $matchesBrand,
        public readonly array $mergedBrandFields,
        public readonly string $summary,
        public readonly array $insights,
    ) {}

}
