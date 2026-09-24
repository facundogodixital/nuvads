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
            <span class="text-sm tabular-nums text-text-muted">
              {{ mentionsLabel }}
              <span
                v-if="isRare"
                class="ml-1 rounded-sm border border-border px-1.5 py-0.5 text-xs"
              >poco frecuente</span>
            </span>
          </div>

          <!-- La barra compara con el tema más mencionado de la sección; el texto de arriba da el número. -->
          <div
            class="mt-2 h-1.5 rounded-sm bg-surface-selected"
            aria-hidden="true"
          >
            <div
              class="h-1.5 rounded-sm"
              :class="kind === 'pain' ? 'bg-danger' : 'bg-success'"
              :style="{ width: `max(4px, ${mentionsBarPercentage}%)` }"
            />
          </div>

          <div class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-2 text-xs text-text-muted">
            <span
              v-if="trendBadge"
              class="rounded-sm px-2 py-0.5"
              :class="trendBadge.classes"
              :title="trendTooltip"
            >{{ trendBadge.label }}</span>
            <!-- Una barra por tramo de tiempo, del más viejo al más nuevo: muestra la forma, el título da el dato. -->
            <span
              v-if="rangeBars.length > 1"
              class="flex h-5 items-end gap-0.5"
              aria-hidden="true"
            >
              <span
                v-for="rangeBar in rangeBars"
                :key="rangeBar.key"
                class="w-1.5 rounded-t-sm"
                :class="rangeBar.isEmpty ? 'bg-border' : (kind === 'pain' ? 'bg-danger' : 'bg-success')"
                :style="{ height: rangeBar.height }"
                :title="rangeBar.title"
              />
            </span>
            <span
              v-if="rangeBars.length > 1"
              class="sr-only"
            >{{ rangeSharesDescription }}</span>
            <span v-if="topic.payload.last_mentioned_at">
              Última vez: {{ formatDate(topic.payload.last_mentioned_at) }}
            </span>
          </div>
        </div>
      </summary>

      <div class="pb-4 pl-7">
        <ul class="divide-y divide-border border-t border-border">
          <li
            v-for="review in highlightedReviews"
            :key="review.id"
          >
            <BrandGoogleReviewQuote :review="review" />
          </li>
        </ul>
        <!-- Pendiente: el listado completo necesita un endpoint paginado. -->
        <button
          v-if="topic.payload.mentions_count > highlightedReviews.length"
          type="button"
          aria-disabled="true"
          title="Resta implementar"
          class="mt-3 inline-flex min-h-11 cursor-not-allowed items-center rounded-sm border border-border px-3 text-sm text-text-muted"
          @click.prevent
        >
          Ver las {{ topic.payload.mentions_count.toLocaleString('es') }} reseñas
        </button>
      </div>
    </details>
  </li>
</template>


<script setup>
import { computed } from 'vue';
import BrandGoogleReviewQuote from './BrandGoogleReviewQuote.vue';

const props = defineProps({
  topic: { type: Object, required: true },
  kind: { type: String, required: true },
  reviewsById: { type: Object, required: true },
  timeRanges: { type: Array, required: true },
  withTextCount: { type: Number, required: true },
  maxMentionsCount: { type: Number, required: true },
});

// Menos del 1% de las reseñas con texto: el tema existe, pero es un caso aislado.
const RARE_MENTIONS_SHARE = 0.01;
// Una misma tendencia no significa lo mismo en un elogio que en una queja.
const trendBadges = {
  strength: {
    ongoing: { label: 'Se mantiene', classes: 'border border-border' },
    emerging: { label: 'Nueva', classes: 'bg-success-soft text-success' },
    resolved: { label: 'Ya no la mencionan', classes: 'bg-warning-soft text-warning' },
  },
  pain: {
    ongoing: { label: 'Sigue apareciendo', classes: 'border border-border text-danger' },
    emerging: { label: 'Nueva', classes: 'border border-danger text-danger' },
    resolved: { label: 'Ya no aparece', classes: 'bg-success-soft text-success' },
  },
};

// La corrección del usuario, cuando existe, reemplaza al texto original de la IA.
const topicText = computed(() => props.topic.user_body ?? props.topic.body);
// Las corridas anteriores a mentions_share no lo traen: se calcula con los mismos números.
const mentionsShare = computed(() => props.topic.payload.mentions_share
  ?? props.topic.payload.mentions_count / props.withTextCount);
const isRare = computed(() => props.kind === 'pain' && mentionsShare.value < RARE_MENTIONS_SHARE);
const mentionsLabel = computed(() => {
  const mentionsCount = props.topic.payload.mentions_count.toLocaleString('es');
  const withTextCount = props.withTextCount.toLocaleString('es');
  return `${mentionsCount} de ${withTextCount} reseñas · ${formatShare(mentionsShare.value)}`;
});
const mentionsBarPercentage = computed(() => props.topic.payload.mentions_count / props.maxMentionsCount * 100);
const trendBadge = computed(() => trendBadges[props.kind][props.topic.payload.trend] ?? null);
const trendTooltip = computed(() => {
  const lastTimeRange = props.timeRanges.at(-1);
  if (!lastTimeRange) {
    return '';
  }
  return `Compara el último tramo (${formatTimeRange(lastTimeRange)}) con los anteriores.`;
});
// Las alturas son relativas al tramo donde más se menciona; un tramo sin menciones queda como una base fina.
const rangeBars = computed(() => {
  const rangeShares = props.topic.payload.range_shares ?? [];
  const maxRangeShare = Math.max(0, ...rangeShares);

  return rangeShares.map((rangeShare, index) => {
    const timeRange = props.timeRanges[index];
    const rangeLabel = timeRange ? formatTimeRange(timeRange) : `Tramo ${index + 1}`;
    return {
      key: index,
      isEmpty: rangeShare === 0,
      height: rangeShare === 0 ? '2px' : `max(4px, ${rangeShare / maxRangeShare * 100}%)`,
      title: `${rangeLabel}: ${formatShare(rangeShare)} de las reseñas`,
    };
  });
});
const rangeSharesDescription = computed(() => {
  const rangeShares = (props.topic.payload.range_shares ?? []).map(formatShare);
  return `Parte de las reseñas que lo mencionan en cada tramo, del más viejo al más nuevo: ${rangeShares.join(', ')}.`;
});
const highlightedReviews = computed(() => props.topic.payload.highlight_ids
  .map((knowledgeSourceId) => props.reviewsById[knowledgeSourceId])
  .filter(Boolean));

// Con menos del 1% se muestra un decimal, para que no quede en 0 %.
function formatShare(share) {
  const maximumFractionDigits = share > 0 && share < 0.01 ? 1 : 0;
  return share.toLocaleString('es', { style: 'percent', maximumFractionDigits });
}

function formatTimeRange(timeRange) {
  return `${formatMonth(timeRange.from)} – ${formatMonth(timeRange.to)}`;
}

function formatMonth(date) {
  return new Date(`${date}T00:00:00`).toLocaleDateString('es', { month: 'short', year: 'numeric' });
}

function formatDate(date) {
  return new Date(`${date}T00:00:00`).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>


<style scoped>
/* Safari dibuja su propio triángulo en summary; la flecha ya la pone el componente. */
summary::-webkit-details-marker {
  display: none;
}
</style>
