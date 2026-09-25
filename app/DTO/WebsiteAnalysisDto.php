<?php

namespace App\DTO;

class WebsiteAnalysisDto
{


    /**
     * matchesBrand: false si el modelo ve que la fuente es de otro negocio; entonces no se guardan los campos.
     * brandFields: los campos de texto de la marca, cada uno string o null.
     * inferredFields: los nombres de los campos de brandFields que son deducciones y no datos explícitos.
     *
     * @param  array<string, ?string>  $brandFields
     * @param  list<string>  $inferredFields
     * @param  list<string>  $insights
     */
    public function __construct(
        public readonly bool $matchesBrand,
        public readonly array $brandFields,
        public readonly array $inferredFields,
        public readonly string $summary,
        public readonly array $insights,
    ) {}

}
