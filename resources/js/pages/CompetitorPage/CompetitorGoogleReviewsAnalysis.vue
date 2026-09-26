<template>
  <div class="mt-8 space-y-8">
    <section aria-labelledby="competitor-reviews-numbers-heading">
      <h3
        id="competitor-reviews-numbers-heading"
        class="spec-label mb-3"
      >
        En números
      </h3>
      <dl class="grid border-y border-border sm:grid-cols-3">
        <div
          v-for="(tile, index) in numberTiles"
          :key="tile.label"
          class="py-3 sm:px-4 sm:first:pl-0"
          :class="index > 0 ? 'border-t border-border sm:border-t-0 sm:border-l' : ''"
        >
          <dt class="text-xs text-text-muted">
            {{ tile.label }}
          </dt>
          <dd class="mt-1 text-lg font-medium tabular-nums">
            {{ tile.value }}
          </dd>
          <dd
            v-if="tile.detail"
            class="text-xs text-text-muted"
          >
            {{ tile.detail }}
          </dd>
        </div>
      </dl>
    </section>

    <section
      v-for="topicSection in topicSections"
      :key="topicSection.id"
      :aria-labelledby="`competitor-reviews-${topicSection.id}-heading`"
    >
      <h3
        :id="`competitor-reviews-${topicSection.id}-heading`"
        class="spec-label mb-3"
      >
        {{ topicSection.title }}
      </h3>
      <p
        v-if="!topicSection.topics.length"
        class="text-sm text-text-muted"
      >
        {{ topicSection.emptyMessage }}
      </p>
      <ul
        v-else
        class="divide-y divide-border border-y border-border"
      >
        <li
          v-for="topic in topicSection.topics"
          :key="topic.id"
        >
          <details class="group">
            <summary class="flex cursor-pointer list-none items-baseline justify-between gap-3 py-3">
              <span class="font-medium">{{ getInsightText(topic) }}</span>
              <span class="shrink-0 text-sm tabular-nums text-text-muted">
                {{ getMentionsLabel(topic) }}
              </span>
            </summary>
            <ul class="space-y-3 pb-4">
              <li
                v-for="review in getHighlightedReviews(topic)"
                :key="review.id"
                class="rounded-sm bg-surface p-3 text-sm leading-6"
              >
                <p>“{{ review.payload.text }}”</p>
                <p class="mt-1 text-xs text-text-muted">
                  {{ formatCount(review.payload.stars, 'estrella', 'estrellas') }} · {{ formatDate(review.payload.published_at) }}
                </p>
              </li>
            </ul>
          </details>
        </li>
      </ul>
    </section>

    <section
      v-if="insights.length"
      aria-labelledby="competitor-reviews-insights-heading"
    >
      <h3
        id="competitor-reviews-insights-heading"
        class="spec-label mb-3"
      >
        Conclusiones
      </h3>
      <ul class="divide-y divide-border border-y border-border">
        <li
          v-for="insight in insights"
          :key="insight.id"
          class="py-3 text-sm leading-6"
        >
          {{ getInsightText(insight) }}
        </li>
      </ul>
    </section>
  </div>
</template>


<script setup>
import { computed } from 'vue';

const props = defineProps({
  analysis: { type: Object, required: true },
  pains: { type: Array, required: true },
  strengths: { type: Array, required: true },
  insights: { type: Array, required: true },
  // Solo las reseñas destacadas de las quejas y los elogios.
  reviews: { type: Array, required: true },
});

const metrics = computed(() => props.analysis.payload.metrics);
// Cada número lleva debajo su detail, el dato que lo acompaña, o nada si es null.
const numberTiles = computed(() => {
  const googleScore = metrics.value.google_total_score;
  const googleReviewsCount = metrics.value.google_reviews_count;
  const ownerResponsePercentage = Math.round(metrics.value.owner_response_rate * 100);
  const medianOwnerResponseDays = metrics.value.median_owner_response_days;

  return [
    {
      label: 'Puntaje en Google',
      value: googleScore === null ? 'Sin datos' : `${googleScore.toLocaleString('es')} ★`,
      detail: googleReviewsCount === null ? null : `${formatCount(googleReviewsCount, 'reseña', 'reseñas')} en total`,
    },
    {
      label: 'Reseñas leídas',
      value: metrics.value.reviews_count.toLocaleString('es'),
      detail: `${metrics.value.with_text_count.toLocaleString('es')} con texto`,
    },
    {
      label: 'Responde el dueño',
      value: `${ownerResponsePercentage}%`,
      // Mediana, porque un dueño que responde de golpe reseñas viejas dispara el promedio.
      detail: medianOwnerResponseDays === null
        ? null
        : `Suele tardar ${formatCount(Math.round(medianOwnerResponseDays), 'día', 'días')}`,
    },
  ];
});
// Primero dónde falla, que es lo que la marca puede aprovechar, y después qué le funciona.
const topicSections = computed(() => [
  {
    id: 'pains',
    title: 'Dónde falla, según sus clientes',
    topics: props.pains,
    emptyMessage: 'Sus clientes no mencionan quejas.',
  },
  {
    id: 'strengths',
    title: 'Qué le funciona, según sus clientes',
    topics: props.strengths,
    emptyMessage: 'Sus clientes no mencionan elogios concretos.',
  },
]);

// Cuántas de las reseñas con texto mencionan el tema, por ejemplo "3 reseñas de 462".
function getMentionsLabel(topic) {
  const mentionsLabel = formatCount(topic.payload.mentions_count, 'reseña', 'reseñas');
  return `${mentionsLabel} de ${metrics.value.with_text_count.toLocaleString('es')}`;
}

function getHighlightedReviews(topic) {
  return props.reviews.filter((review) => topic.payload.highlight_ids.includes(review.id));
}

// La corrección del usuario, cuando existe, reemplaza al texto original de la IA.
function getInsightText(insight) {
  return insight.user_body ?? insight.body;
}

function formatCount(count, singular, plural) {
  return `${count.toLocaleString('es')} ${count === 1 ? singular : plural}`;
}

function formatDate(isoDate) {
  return new Date(`${isoDate}T12:00:00`).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
