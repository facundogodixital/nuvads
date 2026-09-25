<template>
  <figure class="py-4">
    <figcaption class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
      <span class="font-medium text-text">{{ conversation.payload.contact_name }}</span>
      <span aria-hidden="true">·</span>
      <span>{{ formatCount(messages.length, 'mensaje', 'mensajes') }}</span>
      <span aria-hidden="true">·</span>
      <time :datetime="lastMessage.sent_at">{{ formatDate(lastMessage.sent_at) }}</time>
    </figcaption>

    <!-- Las conversaciones llegan a 200 mensajes: se leen dentro de un recuadro con scroll. -->
    <ol
      class="mt-2 max-h-72 space-y-1.5 overflow-y-auto rounded-sm border border-border bg-surface p-3"
      tabindex="0"
      :aria-label="`Conversación con ${conversation.payload.contact_name}`"
    >
      <li
        v-for="(message, index) in messages"
        :key="index"
        class="flex"
        :class="message.is_from_owner ? 'justify-end' : 'justify-start'"
      >
        <p
          class="max-w-[85%] rounded-sm px-3 py-1.5 text-sm leading-6 whitespace-pre-line"
          :class="message.is_from_owner ? 'bg-accent-soft' : 'bg-surface-raised'"
        >
          <span class="sr-only">{{ message.is_from_owner ? 'Tú' : conversation.payload.contact_name }}: </span>
          {{ message.text }}
          <time
            :datetime="message.sent_at"
            class="ml-2 text-xs text-text-muted tabular-nums"
          >{{ formatDateTime(message.sent_at) }}</time>
        </p>
      </li>
    </ol>

    <a
      :href="conversation.source_ref"
      target="_blank"
      rel="noopener noreferrer"
      class="mt-1 inline-flex min-h-8 items-center gap-1 text-xs text-text-muted hover:text-text hover:underline hover:underline-offset-4"
    >
      Abrir en WhatsApp<span class="sr-only"> (se abre en otra pestaña)</span>
      <svg
        class="h-3 w-3"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="M14 4h6v6M20 4l-9 9M18 14v5a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1V7a1 1 0 0 1 1-1h5"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
    </a>
  </figure>
</template>


<script setup>
import { computed } from 'vue';

const props = defineProps({
  conversation: { type: Object, required: true },
});

const messages = computed(() => props.conversation.payload.messages);
const lastMessage = computed(() => messages.value.at(-1));

function formatCount(count, singular, plural) {
  return `${count.toLocaleString('es')} ${count === 1 ? singular : plural}`;
}

// sent_at llega como 'Y-m-d H:i', en la hora del teléfono.
function formatDate(sentAt) {
  return new Date(sentAt.replace(' ', 'T')).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}

function formatDateTime(sentAt) {
  return new Date(sentAt.replace(' ', 'T')).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
</script>
