<?php

namespace App\DTO;

class WhatsAppConversationsTopicDto
{


    /**
     * Un tema de las conversaciones de clientes, ya contado. knowledgeSourceIds son las conversaciones que lo
     * mencionan, y highlightIds, hasta tres, las más recientes, para mostrar como referencia. mentionsShare es qué
     * parte de las conversaciones de clientes lo menciona. ownerAnswer es lo que suele responder el negocio; solo lo
     * tienen las preguntas y los frenos, y queda en null si el negocio no respondió por escrito.
     *
     * @param  list<int>  $knowledgeSourceIds
     * @param  list<int>  $highlightIds
     */
    public function __construct(
        public readonly string $topic,
        public readonly array $knowledgeSourceIds,
        public readonly array $highlightIds,
        public readonly int $mentionsCount,
        public readonly float $mentionsShare,
        public readonly ?string $ownerAnswer,
    ) {}

}
