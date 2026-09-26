<?php

namespace App\DTO;

class CompetitorSourceAnalysisDto
{


    /**
     * El análisis final de una fuente de un competidor: su sitio, su Instagram o sus anuncios.
     * matchesCompetitor: false si el modelo ve que la fuente es de otro negocio; entonces no se guardan los campos.
     * mergedCompetitorFields: los campos del competidor mezclados con su texto actual, cada uno string o null.
     *
     * @param  array<string, ?string>  $mergedCompetitorFields
     * @param  list<string>  $insights
     */
    public function __construct(
        public readonly bool $matchesCompetitor,
        public readonly array $mergedCompetitorFields,
        public readonly string $summary,
        public readonly array $insights,
    ) {}

}
