<template>
  <figure class="py-4">
    <figcaption class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
      <BrandStarRating
        :rating="review.payload.stars"
        size="sm"
      />
      <span class="font-medium text-text">{{ review.payload.author.name ?? 'Cliente de Google' }}</span>
      <span
        v-if="review.payload.author.is_local_guide"
        class="rounded-sm border border-border px-1.5 py-0.5"
      >Local Guide</span>
      <span aria-hidden="true">·</span>
      <time :datetime="review.payload.published_at">{{ formatDate(review.payload.published_at) }}</time>
    </figcaption>

    <blockquote
      class="mt-2 max-w-prose text-sm leading-6 whitespace-pre-line"
      :class="{ 'line-clamp-5': !isExpanded }"
    >
      {{ review.payload.text }}
    </blockquote>
    <button
      v-if="isLongText"
      type="button"
      class="mt-1 inline-flex min-h-8 cursor-pointer items-center text-xs font-medium text-text-muted underline underline-offset-4 hover:text-text"
      :aria-expanded="isExpanded"
      @click="isExpanded = !isExpanded"
    >
      {{ isExpanded ? 'Ver menos' : 'Ver completa' }}
    </button>

    <!-- La respuesta del dueño muestra el tono con el que la marca le habla a sus clientes. -->
    <p
      v-if="review.payload.owner_response"
      class="mt-2 max-w-prose border-l border-border pl-3 text-xs leading-5 text-text-muted"
    >
      <span class="font-medium text-text">Respuesta del negocio:</span>
      {{ review.payload.owner_response.text }}
    </p>

    <a
      v-if="review.payload.url"
      :href="review.payload.url"
      target="_blank"
      rel="noopener noreferrer"
      class="mt-1 inline-flex min-h-8 items-center gap-1 text-xs text-text-muted hover:text-text hover:underline hover:underline-offset-4"
    >
      Ver en Google<span class="sr-only"> (se abre en otra pestaña)</span>
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
import { ref, computed } from 'vue';
import BrandStarRating from './BrandStarRating.vue';

const props = defineProps({
  review: { type: Object, required: true },
});

// Cinco líneas del ancho de lectura alcanzan para unos 400 caracteres.
const LONG_TEXT_LENGTH = 400;

const isExpanded = ref(false);

const isLongText = computed(() => (props.review.payload.text?.length ?? 0) > LONG_TEXT_LENGTH);

function formatDate(date) {
  return new Date(`${date}T00:00:00`).toLocaleDateString('es', { day: 'numeric', month: 'short', year: 'numeric' });
}
</script>
