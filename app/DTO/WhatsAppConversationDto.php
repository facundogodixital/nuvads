<?php

namespace App\DTO;

class WhatsAppConversationDto
{


    /**
     * Una conversación del zip de WhatsApp, todavía sin guardar. contactName es el contacto como lo tiene agendado el
     * dueño del teléfono, o su teléfono si no está agendado. Los mensajes van en orden, con sent_at como Y-m-d H:i en
     * la hora del teléfono; is_from_owner es true en los que escribió el dueño ("Yo" en el export).
     *
     * @param  list<array{sent_at: string, is_from_owner: bool, text: string}>  $messages
     */
    public function __construct(
        public readonly string $phone,
        public readonly string $contactName,
        public readonly array $messages,
    ) {}

}
