<?php

namespace App\DTO;

use App\Models\KnowledgeSource;
use App\Models\KnowledgeInsight;
use Illuminate\Database\Eloquent\Collection;


class WhatsAppConversationsInsightsDto
{


    /**
     * Lo que muestra la pantalla de las conversaciones de WhatsApp. metrics y analysis son las métricas y el análisis
     * vigentes, o null si todavía no hay; questions, objections e insights, las preguntas, los frenos y las
     * conclusiones vigentes; y conversations, solo las conversaciones destacadas de esas filas (highlight_ids en su
     * payload). Las propiedades se llaman como las claves del JSON que recibe la pantalla.
     *
     * @param  Collection<int, KnowledgeInsight>  $questions
     * @param  Collection<int, KnowledgeInsight>  $objections
     * @param  Collection<int, KnowledgeInsight>  $insights
     * @param  Collection<int, KnowledgeSource>  $conversations
     */
    public function __construct(
        public readonly ?KnowledgeInsight $metrics,
        public readonly ?KnowledgeInsight $analysis,
        public readonly Collection $questions,
        public readonly Collection $objections,
        public readonly Collection $insights,
        public readonly Collection $conversations,
    ) {}

}
