<?php

namespace App\DTO;

class UploadedFilesAnalysisDto
{


    /**
     * matchesBrand: false si el modelo ve que los archivos son de otro negocio; entonces no se guardan los campos.
     * mergedBrandFields: los campos de la marca sobre los que los archivos dicen algo nuevo, mezclados con su texto
     * actual; los demás vuelven en null.
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
