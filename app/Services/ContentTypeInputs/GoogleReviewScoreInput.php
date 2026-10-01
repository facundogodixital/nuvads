<?php

namespace App\Services\ContentTypeInputs;

use App\Models\Brand;
use App\DTO\ContentTypeInputMaterialDto;
use App\Services\KnowledgeInsightService;


// google_review_score: el puntaje y la cantidad de reseñas de la ficha de Google, un dato de apoyo para la placa.
class GoogleReviewScoreInput implements ContentTypeInput
{


    // El puntaje acompaña a las reseñas, pero solo no sostiene una idea.
    public function canSupportIdeas(): bool
    {
        return false;
    }


    public function isEmpty(Brand $brand): bool
    {
        return $this->getMaterial($brand) === null;
    }


    // Nunca da el texto de un tipo, porque el puntaje no alcanza para que se pueda usar.
    public function getMissingMaterialMessage(Brand $brand): string
    {
        return 'Analiza tus reseñas de Google para usar este tipo.';
    }


    // Manda el puntaje de la ficha de Google y la cantidad de reseñas que muestra, de las métricas vigentes. No trae
    // reseñas ni IDs. Sin puntaje, no hay nada que mandar.
    public function getMaterial(Brand $brand): ?ContentTypeInputMaterialDto
    {
        // Las métricas vigentes más nuevas, las de la última investigación de las reseñas.
        $metricsKnowledgeInsight = resolve(KnowledgeInsightService::class)
            ->findCurrentByTypes($brand, ['google_reviews_metrics'])
            ->last();
        $googleTotalScore = $metricsKnowledgeInsight?->payload['google_total_score'];
        if ($googleTotalScore === null) {
            return null;
        }

        // Lo que lee el modelo: el puntaje de la ficha y la cantidad de reseñas que muestra.
        $scoreForModel = [
            'google_total_score' => $googleTotalScore,
            'google_reviews_count' => $metricsKnowledgeInsight->payload['google_reviews_count'],
        ];
        $promptText = 'El puntaje de la ficha de Google del negocio (google_total_score, de 1 a 5) y la cantidad de '
            ."reseñas que muestra (google_reviews_count). Es un dato de apoyo: no es una reseña.\n"
            .json_encode($scoreForModel, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new ContentTypeInputMaterialDto(
            promptText: $promptText,
            knowledgeInsightIds: [],
            knowledgeSourceIds: [],
            ideaReviews: [],
        );
    }

}
