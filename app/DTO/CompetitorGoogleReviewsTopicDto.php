<?php

namespace App\DTO;

class CompetitorGoogleReviewsTopicDto
{


    /**
     * Una queja o un elogio que mencionan las reseñas de un competidor, ya contado. competitorSourceIds son las
     * reseñas que lo mencionan, y highlightIds, hasta tres para mostrar como referencia. mentionsShare es qué parte de
     * las reseñas con texto lo menciona.
     *
     * @param  list<int>  $competitorSourceIds
     * @param  list<int>  $highlightIds
     */
    public function __construct(
        public readonly string $topic,
        public readonly array $competitorSourceIds,
        public readonly array $highlightIds,
        public readonly int $mentionsCount,
        public readonly float $mentionsShare,
    ) {}

}
