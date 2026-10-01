<?php

namespace App\Services\ContentTypeInputs;

use App\Models\Brand;
use App\DTO\IdeaReviewDto;
use Illuminate\Support\Str;
use App\Services\IdeaService;
use App\Models\KnowledgeSource;
use App\DTO\ContentTypeInputMaterialDto;
use App\Services\KnowledgeSourceService;
use App\Services\KnowledgeInsightService;


// google_review_staff: las personas del equipo que nombran las reseñas de Google, cada una con sus reseñas.
class GoogleReviewStaffInput implements ContentTypeInput
{

    const int MAX_GROUPS = 10;
    const int MAX_REVIEWS_PER_GROUP = 8;
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


    // Manda hasta 10 personas del equipo del análisis vigente de las reseñas, las de más menciones, cada una con su
    // nombre, sus menciones y hasta 8 de sus reseñas que sirven, sorteadas al azar. Sin personas que mandar, no hay
    // nada que mandar.
    public function getMaterial(Brand $brand): ?ContentTypeInputMaterialDto
    {
        $knowledgeSourceService = resolve(KnowledgeSourceService::class);
        $usedKnowledgeSourceIds = resolve(IdeaService::class)->getUsedKnowledgeSourceIds($brand);
        // El análisis vigente más nuevo, el de la última investigación de las reseñas.
        $analysisKnowledgeInsight = resolve(KnowledgeInsightService::class)
            ->findCurrentByTypes($brand, ['google_reviews_brand_analysis'])
            ->last();
        // Cada persona es un tema del análisis, con topic, knowledge_source_ids y mentions_count.
        $staffTopics = collect($analysisKnowledgeInsight?->payload['staff'] ?? [])
            ->sortByDesc('mentions_count');

        $ideaReviews = [];
        $staffForModel = [];
        foreach ($staffTopics as $staffTopic) {
            $hasMaxGroups = count($staffForModel) === self::MAX_GROUPS;
            if ($hasMaxGroups) {
                break;
            }

            // Las reseñas de la persona que sirven: de Google, de 4 o 5 estrellas y con texto.
            $usableKnowledgeSources = $knowledgeSourceService
                ->findByIds($brand, $staffTopic['knowledge_source_ids'])
                ->where('type', 'google_review')
                ->filter(function (KnowledgeSource $knowledgeSource): bool {
                    $hasText = $knowledgeSource->payload['text'] !== null;
                    $hasFourOrFiveStars = $knowledgeSource->payload['stars'] >= 4;
                    return $hasText && $hasFourOrFiveStars;
                });
            // Una persona sin ninguna reseña que sirva no se manda.
            if ($usableKnowledgeSources->isEmpty()) {
                continue;
            }

            // Se sortea entre las que no están en una idea de la marca; si ya están todas, entre todas las que sirven.
            $unusedKnowledgeSources = $usableKnowledgeSources->whereNotIn('id', $usedKnowledgeSourceIds);
            $hasUnusedKnowledgeSources = $unusedKnowledgeSources->isNotEmpty();
            $candidateKnowledgeSources = $hasUnusedKnowledgeSources ? $unusedKnowledgeSources : $usableKnowledgeSources;
            $knowledgeSources = $candidateKnowledgeSources->shuffle()->take(self::MAX_REVIEWS_PER_GROUP);

            $staffIdeaReviews = [];
            foreach ($knowledgeSources as $knowledgeSource) {
                $authorName = $knowledgeSource->payload['author']['name'];
                $ideaReview = new IdeaReviewDto(
                    id: $knowledgeSource->id,
                    // Solo el nombre de pila: la pieza nunca muestra el apellido.
                    name: $authorName === null ? null : Str::before(trim($authorName), ' '),
                    stars: $knowledgeSource->payload['stars'],
                    date: $knowledgeSource->payload['published_at'],
                    text: Str::limit($knowledgeSource->payload['text'], self::MAX_REVIEW_TEXT_LENGTH),
                );
                $staffIdeaReviews[] = $ideaReview;
                $ideaReviews[$knowledgeSource->id] = $ideaReview;
            }
            $staffForModel[] = [
                'insight_id' => $analysisKnowledgeInsight->id,
                'topic' => $staffTopic['topic'],
                'mentions_count' => $staffTopic['mentions_count'],
                'reviews' => $staffIdeaReviews,
            ];
        }
        if ($staffForModel === []) {
            return null;
        }

        $promptText = 'Personas del equipo que nombran las reseñas de Google, de las más mencionadas a las menos. Cada '
            .'una es un grupo con insight_id (el id del análisis de las reseñas, del que salen todas las personas), '
            .'topic (la persona), mentions_count (cuántas reseñas la nombran) y reviews (algunas de esas reseñas, cada '
            ."una con su id, name, el nombre de pila de quien la escribió, stars, date y text, cortado si es largo).\n"
            .json_encode(['staff' => $staffForModel], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return new ContentTypeInputMaterialDto(
            promptText: $promptText,
            knowledgeInsightIds: [$analysisKnowledgeInsight->id],
            knowledgeSourceIds: array_keys($ideaReviews),
            ideaReviews: $ideaReviews,
        );
    }

}
