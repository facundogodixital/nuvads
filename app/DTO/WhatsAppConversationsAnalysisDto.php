<?php

namespace App\DTO;

class WhatsAppConversationsAnalysisDto
{


    /**
     * matchesBrand: false si el modelo ve que la fuente es de otro negocio; entonces no se guardan los campos.
     * mergedBrandFields: los cuatro campos de la marca mezclados con su texto actual, cada uno string o null.
     * ownerVoice: cómo les escribe el negocio a sus clientes, o null si no hay mensajes suyos. No se mezcla con la
     * marca. insights: cada conclusión con body, knowledge_source_ids (las conversaciones en que se apoya) y
     * highlight_ids (las que se muestran como referencia).
     *
     * @param  array<string, ?string>  $mergedBrandFields
     * @param  list<array{body: string, knowledge_source_ids: list<int>, highlight_ids: list<int>}>  $insights
     */
    public function __construct(
        public readonly bool $matchesBrand,
        public readonly array $mergedBrandFields,
        public readonly string $summary,
        public readonly ?string $ownerVoice,
        public readonly array $insights,
    ) {}

}
