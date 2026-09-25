<?php

namespace App\DTO;

class WhatsAppConversationsMetricsDto
{


    /**
     * Métricas de las conversaciones, calculadas en PHP. conversationsCount es cuántas trajo el zip y
     * analyzedConversationsCount cuántas se leyeron: las que tienen mensajes del contacto, hasta el tope de la
     * configuración. El resto se calcula solo sobre las conversaciones de clientes. Las fechas van como Y-m-d H:i, en
     * la hora del teléfono.
     *
     * @param  array<string, int>  $contactKinds  conversaciones leídas por tipo de contacto: customer, supplier,
     *                                            personal y other
     * @param  list<int>  $customerMessagesByHour  mensajes de los clientes por hora del día, de 0 a 23
     * @param  list<int>  $customerMessagesByWeekday  mensajes de los clientes por día de la semana, de lunes a domingo
     * @param  list<string>  $ownerTopEmojis  los emojis que más usa el negocio con sus clientes, de más a menos usado
     */
    public function __construct(
        public readonly int $conversationsCount,
        public readonly int $analyzedConversationsCount,
        public readonly array $contactKinds,
        public readonly int $customerMessagesCount,
        public readonly array $customerMessagesByHour,
        public readonly array $customerMessagesByWeekday,
        public readonly ?float $medianOwnerResponseMinutes,
        public readonly ?float $answeredWithinFiveMinutesShare,
        public readonly ?float $voiceNotesShare,
        public readonly array $ownerTopEmojis,
        public readonly ?string $oldestMessageAt,
        public readonly ?string $newestMessageAt,
    ) {}


    // La forma en que se guarda en el payload y se le manda al modelo.
    public function toArray(): array
    {
        return [
            'conversations_count' => $this->conversationsCount,
            'analyzed_conversations_count' => $this->analyzedConversationsCount,
            'contact_kinds' => $this->contactKinds,
            'customer_messages_count' => $this->customerMessagesCount,
            'customer_messages_by_hour' => $this->customerMessagesByHour,
            'customer_messages_by_weekday' => $this->customerMessagesByWeekday,
            'median_owner_response_minutes' => $this->medianOwnerResponseMinutes,
            'answered_within_5_minutes_share' => $this->answeredWithinFiveMinutesShare,
            'voice_notes_share' => $this->voiceNotesShare,
            'owner_top_emojis' => $this->ownerTopEmojis,
            'oldest_message_at' => $this->oldestMessageAt,
            'newest_message_at' => $this->newestMessageAt,
        ];
    }

}
