<template>
  <li>
    <details class="group">
      <summary class="flex cursor-pointer list-none gap-3 py-4">
        <svg
          class="mt-1 h-4 w-4 shrink-0 text-text-muted transition-transform duration-200 group-open:rotate-90"
          viewBox="0 0 24 24"
          fill="none"
          aria-hidden="true"
        >
          <path
            d="m9 6 6 6-6 6"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
        <div class="min-w-0 flex-1">
          <div class="flex flex-wrap items-baseline justify-between gap-x-3 gap-y-1">
            <span class="font-medium">{{ topicText }}</span>
            <span class="text-sm tabular-nums text-text-muted">{{ mentionsLabel }}</span>
          </div>

          <!-- La barra compara con el tema más mencionado de la sección; el texto de arriba da el número. -->
          <div
            class="mt-2 h-1.5 rounded-sm bg-surface-selected"
            aria-hidden="true"
          >
            <div
              class="h-1.5 rounded-sm"
              :class="kind === 'objection' ? 'bg-warning' : 'bg-accent'"
              :style="{ width: `max(4px, ${mentionsBarPercentage}%)` }"
            />
          </div>
        </div>
      </summary>

      <div class="pb-4 pl-7">
        <!-- Lo que ya responde el dueño es el guion del contenido que puede publicar. -->
        <p
          v-if="topic.payload.owner_answer"
          class="max-w-prose border-l border-border pl-3 text-sm leading-6"
        >
          <span class="font-medium">Lo que respondes:</span>
          {{ topic.payload.owner_answer }}
        </p>
        <p
          v-else
          class="text-sm text-text-muted"
        >
          No lo respondiste por escrito en estos chats.
        </p>

        <ul
          v-if="highlightedConversations.length"
          class="mt-3 divide-y divide-border border-t border-border"
        >
          <li
            v-for="conversation in highlightedConversations"
            :key="conversation.id"
          >
            <BrandWhatsAppConversationQuote :conversation="conversation" />
          </li>
        </ul>
      </div>
    </details>
  </li>
</template>


<script setup>
import { computed } from 'vue';
import BrandWhatsAppConversationQuote from './BrandWhatsAppConversationQuote.vue';

const props = defineProps({
  topic: { type: Object, required: true },
  kind: { type: String, required: true },
  conversationsById: { type: Object, required: true },
  customerConversationsCount: { type: Number, required: true },
  maxMentionsCount: { type: Number, required: true },
});

// La corrección del usuario, cuando existe, reemplaza al texto original de la IA.
const topicText = computed(() => props.topic.user_body ?? props.topic.body);
const mentionsLabel = computed(() => {
  const mentionsCount = props.topic.payload.mentions_count.toLocaleString('es');
  const customerConversationsCount = props.customerConversationsCount.toLocaleString('es');
  return `${mentionsCount} de ${customerConversationsCount} chats · ${formatShare(props.topic.payload.mentions_share)}`;
});
const mentionsBarPercentage = computed(() => props.topic.payload.mentions_count / props.maxMentionsCount * 100);
const highlightedConversations = computed(() => props.topic.payload.highlight_ids
  .map((knowledgeSourceId) => props.conversationsById[knowledgeSourceId])
  .filter(Boolean));

// Con menos del 1% se muestra un decimal, para que no quede en 0 %.
function formatShare(share) {
  const maximumFractionDigits = share > 0 && share < 0.01 ? 1 : 0;
  return share.toLocaleString('es', { style: 'percent', maximumFractionDigits });
}
</script>


<style scoped>
/* Safari dibuja su propio triángulo en summary; la flecha ya la pone el componente. */
summary::-webkit-details-marker {
  display: none;
}
</style>
