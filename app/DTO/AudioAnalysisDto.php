<?php

namespace App\DTO;

class AudioAnalysisDto
{


    /**
     * matchesBrand: false si el modelo ve que el audio es de otro negocio; entonces no se guardan los campos.
     * mergedBrandFields: los seis campos de la marca; con texto solo los que el audio cambió, el resto null.
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
