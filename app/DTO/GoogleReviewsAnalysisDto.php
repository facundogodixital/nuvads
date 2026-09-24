<?php

namespace App\DTO;

class GoogleReviewsAnalysisDto
{


    /**
     * mergedBrandFields: los ocho campos de la marca mezclados con su texto actual, cada uno string o null.
     * insights: cada conclusión con body, knowledge_source_ids (las reseñas en que se apoya) y highlight_ids (las
     * que se muestran como referencia).
     *
     * @param  array<string, ?string>  $mergedBrandFields
     * @param  list<array{body: string, knowledge_source_ids: list<int>, highlight_ids: list<int>}>  $insights
     */
    public function __construct(
        public readonly array $mergedBrandFields,
        public readonly string $summary,
        public readonly array $insights,
    ) {}

}
