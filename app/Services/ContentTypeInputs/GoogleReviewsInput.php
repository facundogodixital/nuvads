<?php

namespace App\Services\ContentTypeInputs;

use App\Models\Brand;
use App\DTO\IdeaReviewDto;
use Illuminate\Support\Str;
use App\Services\IdeaService;
use App\Models\KnowledgeSource;
use App\DTO\ContentTypeInputMaterialDto;
use App\Services\KnowledgeSourceService;


// google_reviews: las reseñas de Google de la marca, sueltas, sin agrupar.
class GoogleReviewsInput implements ContentTypeInput
{

    const int MAX_REVIEWS = 20;
    const int MAX_REVIEW_TEXT_LENGTH = 300;


    public function canSupportIdeas(): bool
    {
        return true;
    }


    public function isEmpty(Brand $brand): bool
    {
        return $this->getMaterial($brand) === null;
    }


    // Si la marca no tiene reseñas de Google, falta analizarlas; si las tiene, ninguna sirve todavía.
    public function getMissingMaterialMessage(Brand $brand): string
    {
        $knowledgeSources = resolve(KnowledgeSourceService::class)->findByTypes($brand, ['google_review']);
        if ($knowledgeSources->isEmpty()) {
            return 'Analiza tus reseñas de Google para usar este tipo.';
        }

        return 'Tus reseñas de Google todavía no alcanzan: hacen falta reseñas de 4 o 5 estrellas con texto.';
    }


    // Manda hasta 20 de las reseñas que sirven, sorteadas al azar y no por fecha, para que cada generación vea reseñas
    // distintas. Sin ninguna que sirva, no hay nada que mandar.
    public function getMaterial(Brand $brand): ?ContentTypeInputMaterialDto
    {
        $usedKnowledgeSourceIds = resolve(IdeaService::class)->getUsedKnowledgeSourceIds($brand);
        // Las reseñas que sirven: de 4 o 5 estrellas y con texto.
        $usableKnowledgeSources = resolve(KnowledgeSourceService::class)
            ->findByTypes($brand, ['google_review'])
            ->filter(function (KnowledgeSource $knowledgeSource): bool {
                $hasText = $knowledgeSource->payload['text'] !== null;
                $hasFourOrFiveStars = $knowledgeSource->payload['stars'] >= 4;
                return $hasText && $hasFourOrFiveStars;
            });
        if ($usableKnowledgeSources->isEmpty()) {
            return null;
        }

        // Se sortea entre las que no están en una idea de la marca; si ya están todas, entre todas las que sirven.
        $unusedKnowledgeSources = $usableKnowledgeSources->whereNotIn('id', $usedKnowledgeSourceIds);
        $hasUnusedKnowledgeSources = $unusedKnowledgeSources->isNotEmpty();
        $candidateKnowledgeSources = $hasUnusedKnowledgeSources ? $unusedKnowledgeSources : $usableKnowledgeSources;
        $knowledgeSources = $candidateKnowledgeSources->shuffle()->take(self::MAX_REVIEWS);

        $ideaReviews = [];
        foreach ($knowledgeSources as $knowledgeSource) {
            $authorName = $knowledgeSource->payload['author']['name'];
            $ideaReviews[$knowledgeSource->id] = new IdeaReviewDto(
                id: $knowledgeSource->id,
                // Solo el nombre de pila: la pieza nunca muestra el apellido.
                name: $authorName === null ? null : Str::before(trim($authorName), ' '),
                stars: $knowledgeSource->payload['stars'],
                date: $knowledgeSource->payload['published_at'],
                text: Str::limit($knowledgeSource->payload['text'], self::MAX_REVIEW_TEXT_LENGTH),
            );
        }
        // Lo que lee el modelo: reviews, la lista de reseñas.
        $reviewsForModel = ['reviews' => array_values($ideaReviews)];
        $promptText = 'Reseñas sueltas de Google, de 4 o 5 estrellas. Cada una trae su id, name (el nombre de pila de '
            .'quien la escribió), stars (sus estrellas), date (cuándo se publicó) y text (lo que escribió, cortado si '
            ."es largo).\n".json_encode($reviewsForModel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new ContentTypeInputMaterialDto(
            promptText: $promptText,
            knowledgeInsightIds: [],
            knowledgeSourceIds: array_keys($ideaReviews),
            ideaReviews: $ideaReviews,
        );
    }

}
