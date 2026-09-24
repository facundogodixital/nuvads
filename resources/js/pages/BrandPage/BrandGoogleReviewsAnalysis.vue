<template>
  <div class="mt-8 space-y-10">
    <section
      v-if="updatedProfileFields.length"
      class="flex gap-3 rounded-sm bg-success-soft p-4"
      aria-labelledby="google-reviews-profile-heading"
    >
      <svg
        class="mt-0.5 h-5 w-5 shrink-0 text-success"
        viewBox="0 0 24 24"
        fill="none"
        aria-hidden="true"
      >
        <path
          d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM8 12.5l2.5 2.5L16 9.5"
          stroke="currentColor"
          stroke-width="1.5"
          stroke-linecap="round"
          stroke-linejoin="round"
        />
      </svg>
      <div class="min-w-0 flex-1">
        <h3
          id="google-reviews-profile-heading"
          class="text-sm font-medium text-success"
        >
          Actualizamos tu perfil de marca
        </h3>
        <p class="mt-1 text-sm leading-6">
          Sumamos lo que dicen tus clientes a estas partes. Revísalas y corrige lo que quieras.
        </p>
        <ul class="mt-3 flex flex-wrap gap-2">
          <li
            v-for="field in updatedProfileFields"
            :key="field"
            class="rounded-sm bg-surface-raised px-2 py-1 text-xs"
          >
            {{ field }}
          </li>
        </ul>
        <RouterLink
          to="/brand/profile"
          class="mt-2 inline-flex min-h-11 items-center gap-1 text-sm font-medium text-success underline underline-offset-4 hover:text-text"
        >
          Ir a Perfil de marca
          <svg
            class="h-4 w-4"
            viewBox="0 0 20 20"
            fill="none"
            aria-hidden="true"
          ><path
            d="M4 10h12m-5-5 5 5-5 5"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          /></svg>
        </RouterLink>
      </div>
    </section>

    <section aria-labelledby="google-reviews-rating-heading">
      <h3
        id="google-reviews-rating-heading"
        class="spec-label mb-3"
      >
        Tu ficha en Google
      </h3>
      <div class="grid gap-6 border-y border-border py-6 sm:grid-cols-[auto_minmax(0,1fr)] sm:gap-10">
        <div>
          <p class="text-5xl leading-none font-medium tracking-tight tabular-nums">
            {{ formatRating(ratingScore) }}
          </p>
          <BrandStarRating
            class="mt-3"
            :rating="ratingScore"
            size="lg"
          />
          <p class="mt-2 text-sm text-text-muted">
            {{ googleReviewsLabel }}
          </p>
        </div>

        <!-- Como la ficha de Google: una fila por puntaje, con la barra relativa al total leído. -->
        <ol
          class="space-y-1.5 self-center"
          aria-label="Reseñas leídas por estrellas"
        >
          <li
            v-for="starRow in starRows"
            :key="starRow.stars"
            class="grid grid-cols-[2.25rem_minmax(0,1fr)_2.75rem] items-center gap-3 text-sm"
          >
            <span class="flex items-center gap-1 tabular-nums">
              {{ starRow.stars }}
              <span class="sr-only">{{ starRow.stars === 1 ? 'estrella' : 'estrellas' }}</span>
              <svg
                class="h-3.5 w-3.5"
                viewBox="0 0 24 24"
                aria-hidden="true"
              >
                <path
                  :d="STAR_PATH"
                  fill="currentColor"
                />
              </svg>
            </span>
            <span
              class="h-2 rounded-sm bg-surface-selected"
              aria-hidden="true"
            >
              <span
                v-if="starRow.count"
                class="block h-2 rounded-sm bg-text"
                :style="{ width: `max(4px, ${starRow.percentage}%)` }"
              />
            </span>
            <span class="text-right tabular-nums text-text-muted">{{ starRow.count.toLocaleString('es') }}</span>
          </li>
        </ol>
      </div>

      <dl class="grid border-b border-border sm:grid-cols-3">
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

      <div
        v-if="aspectRows.length || hasRecentMonthlyReviews"
        class="mt-6 grid gap-8 sm:grid-cols-2"
      >
        <div v-if="aspectRows.length">
          <h4 class="text-sm font-medium">
            Puntaje por aspecto
          </h4>
          <ul class="mt-3 space-y-2">
            <li
              v-for="aspectRow in aspectRows"
              :key="aspectRow.aspect"
              class="flex items-center justify-between gap-3 text-sm"
            >
              <span>{{ aspectRow.aspect }}</span>
              <span class="flex items-center gap-2 tabular-nums">
                {{ formatRating(aspectRow.average) }}
                <BrandStarRating
                  :rating="aspectRow.average"
                  size="sm"
                />
              </span>
            </li>
          </ul>
        </div>

        <div v-if="hasRecentMonthlyReviews">
          <h4 class="text-sm font-medium">
            Reseñas por mes, último año
          </h4>
          <!-- Una barra por mes; el título de cada una da el número exacto. -->
          <ol
            class="mt-3 flex h-20 items-end gap-1"
            aria-hidden="true"
          >
            <li
              v-for="monthBar in monthBars"
              :key="monthBar.month"
              class="flex h-full min-w-0 flex-1 flex-col items-center justify-end gap-1"
              :title="monthBar.title"
            >
              <span
                class="w-full rounded-t-sm"
                :class="monthBar.count ? 'bg-text' : 'bg-border'"
                :style="{ height: monthBar.height }"
              />
              <span class="text-xs text-text-muted uppercase">{{ monthBar.initial }}</span>
            </li>
          </ol>
          <p class="sr-only">
            {{ monthlyReviewsDescription }}
          </p>
        </div>
      </div>
    </section>

    <section
      v-if="timeRanges.length > 1"
      aria-labelledby="google-reviews-time-heading"
    >
      <h3
        id="google-reviews-time-heading"
        class="spec-label mb-1"
      >
        Cómo cambió en el tiempo
      </h3>
      <p class="mb-3 text-sm text-text-muted">
        Partimos tus reseñas con texto en {{ timeRanges.length }} tramos con la misma cantidad cada uno.
      </p>
      <!-- El espacio de 1px sobre el color del hairline dibuja las líneas entre tramos, en dos o en cuatro columnas. -->
      <ol class="grid grid-cols-2 gap-px border-y border-border bg-border sm:grid-cols-4">
        <li
          v-for="(timeRange, index) in timeRanges"
          :key="index"
          class="bg-surface-raised px-3 py-4 sm:px-4"
        >
          <p class="text-xs text-text-muted">
            {{ formatTimeRange(timeRange) }}
          </p>
          <p class="mt-2 flex items-center gap-2">
            <span class="text-lg font-medium tabular-nums">{{ formatRating(timeRange.average_stars) }}</span>
            <BrandStarRating
              :rating="timeRange.average_stars ?? 0"
              size="sm"
            />
          </p>
          <p class="mt-1 text-xs text-text-muted tabular-nums">
            {{ formatCount(timeRange.reviews_count, 'reseña', 'reseñas') }}
          </p>
        </li>
      </ol>
    </section>

    <template v-if="hasReviewsWithText">
      <section aria-labelledby="google-reviews-strengths-heading">
        <h3
          id="google-reviews-strengths-heading"
          class="spec-label mb-1"
        >
          Lo que más valoran
        </h3>
        <p class="mb-3 text-sm text-text-muted">
          Ordenado por cuántas reseñas lo mencionan. Toca uno para leer lo que dicen.
        </p>
        <ul
          v-if="strengths.length"
          class="divide-y divide-border border-y border-border"
        >
          <BrandGoogleReviewsTopic
            v-for="strength in strengths"
            :key="strength.id"
            :topic="strength"
            kind="strength"
            :reviews-by-id="reviewsById"
            :time-ranges="timeRanges"
            :with-text-count="metrics.with_text_count"
            :max-mentions-count="maxStrengthMentionsCount"
          />
        </ul>
        <p
          v-else
          class="rounded-sm border border-dashed border-border p-6 text-center text-sm text-text-muted"
        >
          No encontramos elogios que se repitan en tus reseñas.
        </p>
      </section>

      <section aria-labelledby="google-reviews-pains-heading">
        <h3
          id="google-reviews-pains-heading"
          class="spec-label mb-1"
        >
          De qué se quejan
        </h3>
        <p
          v-if="pains.length"
          class="mb-3 text-sm text-text-muted"
        >
          Solo lo que se repite en al menos dos reseñas. El porcentaje muestra cuánto pesa cada queja.
        </p>
        <ul
          v-if="pains.length"
          class="divide-y divide-border border-y border-border"
        >
          <BrandGoogleReviewsTopic
            v-for="pain in pains"
            :key="pain.id"
            :topic="pain"
            kind="pain"
            :reviews-by-id="reviewsById"
            :time-ranges="timeRanges"
            :with-text-count="metrics.with_text_count"
            :max-mentions-count="maxPainMentionsCount"
          />
        </ul>
        <!-- Que no haya quejas repetidas es una buena noticia, no un vacío. -->
        <p
          v-else
          class="mt-3 flex gap-3 rounded-sm bg-success-soft p-4 text-sm leading-6"
        >
          <svg
            class="mt-0.5 h-5 w-5 shrink-0 text-success"
            viewBox="0 0 24 24"
            fill="none"
            aria-hidden="true"
          >
            <path
              d="M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0ZM8 12.5l2.5 2.5L16 9.5"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          Ninguna queja se repite en tus {{ metrics.with_text_count.toLocaleString('es') }} reseñas con texto.
        </p>
      </section>

      <section
        v-if="insights.length"
        aria-labelledby="google-reviews-insights-heading"
      >
        <h3
          id="google-reviews-insights-heading"
          class="spec-label mb-3"
        >
          Conclusiones
        </h3>
        <ul class="divide-y divide-border border-y border-border">
          <li
            v-for="insight in insightsWithReviews"
            :key="insight.id"
            class="py-4"
          >
            <p class="max-w-prose text-sm leading-6">
              {{ insight.text }}
            </p>
            <details
              v-if="insight.reviews.length"
              class="group mt-2"
            >
              <summary class="inline-flex min-h-8 cursor-pointer list-none items-center gap-1 text-xs font-medium text-text-muted hover:text-text">
                <svg
                  class="h-3.5 w-3.5 transition-transform duration-200 group-open:rotate-90"
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
                {{ formatCount(insight.reviews.length, 'reseña que lo respalda', 'reseñas que lo respaldan') }}
              </summary>
              <ul class="mt-1 divide-y divide-border pl-5">
                <li
                  v-for="review in insight.reviews"
                  :key="review.id"
                >
                  <BrandGoogleReviewQuote :review="review" />
                </li>
              </ul>
            </details>
          </li>
        </ul>
      </section>

      <section
        v-if="supportingGroups.length"
        aria-labelledby="google-reviews-supporting-heading"
      >
        <h3
          id="google-reviews-supporting-heading"
          class="spec-label mb-3"
        >
          Qué más cuentan tus clientes
        </h3>
        <div class="grid gap-6 sm:grid-cols-2">
          <div
            v-for="supportingGroup in supportingGroups"
            :key="supportingGroup.key"
          >
            <h4 class="text-sm font-medium">
              {{ supportingGroup.title }}
            </h4>
            <ul class="mt-2 flex flex-wrap gap-2">
              <li
                v-for="supportingTopic in supportingGroup.topics"
                :key="supportingTopic.topic"
                class="rounded-sm border border-border px-2 py-1 text-sm"
                :title="formatCount(supportingTopic.mentions_count, 'reseña lo menciona', 'reseñas lo mencionan')"
              >
                {{ supportingTopic.topic }}
                <span class="text-text-muted tabular-nums">· {{ supportingTopic.mentions_count.toLocaleString('es') }}</span>
              </li>
            </ul>
          </div>
        </div>
      </section>
    </template>
  </div>
</template>


<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import BrandStarRating from './BrandStarRating.vue';
import BrandGoogleReviewQuote from './BrandGoogleReviewQuote.vue';
import BrandGoogleReviewsTopic from './BrandGoogleReviewsTopic.vue';

const props = defineProps({
  analysis: { type: Object, required: true },
  metricsInsight: { type: Object, required: true },
  pains: { type: Array, required: true },
  strengths: { type: Array, required: true },
  insights: { type: Array, required: true },
  reviews: { type: Array, required: true },
});

// La misma estrella que BrandStarRating, para el rótulo de cada fila de puntajes.
const STAR_PATH = 'M12 2L14.35 8.76L21.51 8.91L15.8 13.24L17.88 20.09L12 16L6.12 20.09L8.2 13.24L2.49 8.91L9.65 8.76Z';
// Mismos nombres que en Perfil de marca, para que el usuario los reconozca.
const profileFieldNames = {
  brand_offer_description: 'Productos y servicios',
  brand_differentiators_description: 'Qué te hace diferente',
  brand_customers_description: 'Quiénes te compran',
  brand_customers_needs_description: 'Qué necesitan',
  brand_tone_of_voice_description: 'Tu manera de hablar',
  brand_customers_valued_aspects_description: 'Lo que más valoran',
  brand_customers_faq_description: 'Preguntas y dudas frecuentes',
  brand_content_opportunities_description: 'Oportunidades de contenido',
};
const supportingGroupTitles = {
  facts: 'Datos útiles que mencionan',
  profiles: 'Quiénes te eligen',
  products: 'Productos que nombran',
  staff: 'Personas del equipo que nombran',
};

const metrics = computed(() => props.metricsInsight.payload);
const timeRanges = computed(() => metrics.value.time_ranges ?? []);
const hasReviewsWithText = computed(() => metrics.value.with_text_count > 0);
// El puntaje de la ficha es el que ve la gente; si no llegó, el promedio de las reseñas leídas.
const ratingScore = computed(() => metrics.value.google_total_score ?? metrics.value.average_stars ?? 0);
const googleReviewsLabel = computed(() => {
  const googleReviewsCount = metrics.value.google_reviews_count ?? metrics.value.reviews_count;
  return `${formatCount(googleReviewsCount, 'reseña', 'reseñas')} en Google`;
});
const reviewsById = computed(() => Object.fromEntries(props.reviews.map((review) => [review.id, review])));
// Los campos que el modelo devolvió con texto ya quedaron guardados en la marca.
const updatedProfileFields = computed(() => {
  const mergedBrandFields = props.analysis.payload.brand ?? {};
  return Object.keys(profileFieldNames)
    .filter((field) => mergedBrandFields[field]?.trim())
    .map((field) => profileFieldNames[field]);
});
const starRows = computed(() => [5, 4, 3, 2, 1].map((stars) => {
  const count = metrics.value.stars_distribution[stars] ?? 0;
  return { stars, count, percentage: count / metrics.value.reviews_count * 100 };
}));
const numberTiles = computed(() => {
  const tiles = [
    {
      label: 'Reseñas leídas',
      value: metrics.value.reviews_count.toLocaleString('es'),
      detail: `${metrics.value.with_text_count.toLocaleString('es')} con texto`,
    },
    {
      label: 'Responde el negocio',
      value: formatShare(metrics.value.owner_response_rate ?? 0),
      detail: 'de las reseñas leídas',
    },
  ];
  const medianOwnerResponseDays = metrics.value.median_owner_response_days;
  if (medianOwnerResponseDays !== null && medianOwnerResponseDays !== undefined) {
    tiles.push({
      label: 'Tarda en responder',
      value: formatCount(Math.round(medianOwnerResponseDays), 'día', 'días'),
      detail: 'en una respuesta típica',
    });
  }
  return tiles;
});
// Sin aspectos, PHP manda una lista vacía en lugar de un objeto.
const aspectRows = computed(() => Object.entries(metrics.value.detailed_rating_averages ?? {})
  .map(([aspect, average]) => ({ aspect, average })));
const monthBars = computed(() => {
  const reviewsPerMonth = Object.entries(metrics.value.reviews_per_month ?? {});
  const maxMonthlyCount = Math.max(0, ...reviewsPerMonth.map(([, count]) => count));

  return reviewsPerMonth.map(([month, count]) => ({
    month,
    count,
    initial: formatMonthInitial(month),
    height: count ? `max(4px, ${count / maxMonthlyCount * 100}%)` : '2px',
    title: `${formatMonthName(month)}: ${formatCount(count, 'reseña', 'reseñas')}`,
  }));
});
const hasRecentMonthlyReviews = computed(() => monthBars.value.some((monthBar) => monthBar.count > 0));
const monthlyReviewsDescription = computed(() => monthBars.value.map((monthBar) => monthBar.title).join('; '));
const maxStrengthMentionsCount = computed(() => getMaxMentionsCount(props.strengths));
const maxPainMentionsCount = computed(() => getMaxMentionsCount(props.pains));
const insightsWithReviews = computed(() => props.insights.map((insight) => ({
  id: insight.id,
  // La corrección del usuario, cuando existe, reemplaza al texto original de la IA.
  text: insight.user_body ?? insight.body,
  reviews: (insight.payload?.highlight_ids ?? [])
    .map((knowledgeSourceId) => reviewsById.value[knowledgeSourceId])
    .filter(Boolean),
})));
const supportingGroups = computed(() => Object.entries(supportingGroupTitles)
  .map(([key, title]) => ({ key, title, topics: props.analysis.payload[key] ?? [] }))
  .filter((supportingGroup) => supportingGroup.topics.length));

function getMaxMentionsCount(topics) {
  return Math.max(1, ...topics.map((topic) => topic.payload.mentions_count));
}

function formatRating(rating) {
  if (rating === null || rating === undefined) {
    return '–';
  }
  return rating.toLocaleString('es', { minimumFractionDigits: 1, maximumFractionDigits: 1 });
}

function formatShare(share) {
  return share.toLocaleString('es', { style: 'percent', maximumFractionDigits: 0 });
}

function formatCount(count, singular, plural) {
  return `${count.toLocaleString('es')} ${count === 1 ? singular : plural}`;
}

function formatTimeRange(timeRange) {
  return `${formatMonthName(timeRange.from.slice(0, 7))} – ${formatMonthName(timeRange.to.slice(0, 7))}`;
}

// month llega como 'Y-m'.
function formatMonthName(month) {
  return new Date(`${month}-01T00:00:00`).toLocaleDateString('es', { month: 'short', year: 'numeric' });
}

function formatMonthInitial(month) {
  return new Date(`${month}-01T00:00:00`).toLocaleDateString('es', { month: 'narrow' });
}
</script>


<style scoped>
/* Safari dibuja su propio triángulo en summary; la flecha ya la pone el componente. */
summary::-webkit-details-marker {
  display: none;
}
</style>
