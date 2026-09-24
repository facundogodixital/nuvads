<?php

namespace App\DTO;

class GoogleReviewsTopicDto
{


    /**
     * Un tema que mencionan las reseñas, ya contado. knowledgeSourceIds son las reseñas que lo mencionan, y
     * highlightIds, hasta tres para mostrar como referencia. Los cuatro últimos campos solo se completan en las quejas
     * y los elogios; trend queda en null también si hay un solo tramo de tiempo y no hay con qué comparar.
     *
     * @param  list<int>  $knowledgeSourceIds
     * @param  list<int>  $highlightIds
     * @param  list<float>|null  $rangeShares  qué parte de cada tramo de tiempo lo menciona, del más viejo al más nuevo
     */
    public function __construct(
        public readonly string $topic,
        public readonly array $knowledgeSourceIds,
        public readonly array $highlightIds,
        public readonly int $mentionsCount,
        public readonly ?float $mentionsShare = null,
        public readonly ?array $rangeShares = null,
        public readonly ?string $lastMentionedAt = null,
        public readonly ?string $trend = null,
    ) {}

}
