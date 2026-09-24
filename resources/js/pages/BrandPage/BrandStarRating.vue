<template>
  <span
    role="img"
    :aria-label="ratingLabel"
    class="relative inline-flex shrink-0 align-middle"
  >
    <!-- Las estrellas vacías hacen de fondo; las llenas se recortan al puntaje, así un 4,8 muestra casi cinco. -->
    <span
      class="flex text-border"
      aria-hidden="true"
    >
      <svg
        v-for="star in 5"
        :key="star"
        :class="sizeClasses[size]"
        viewBox="0 0 24 24"
      >
        <path
          :d="STAR_PATH"
          fill="currentColor"
          stroke="currentColor"
          stroke-linejoin="round"
        />
      </svg>
    </span>
    <span
      class="absolute inset-y-0 left-0 flex overflow-hidden text-text"
      :style="{ width: `${filledPercentage}%` }"
      aria-hidden="true"
    >
      <svg
        v-for="star in 5"
        :key="star"
        class="shrink-0"
        :class="sizeClasses[size]"
        viewBox="0 0 24 24"
      >
        <path
          :d="STAR_PATH"
          fill="currentColor"
          stroke="currentColor"
          stroke-linejoin="round"
        />
      </svg>
    </span>
  </span>
</template>


<script setup>
import { computed } from 'vue';

const props = defineProps({
  rating: { type: Number, required: true },
  size: { type: String, default: 'md' },
});

// Estrella de cinco puntas con 2px de aire alrededor: el aire hace de separación y el recorte da exacto.
const STAR_PATH = 'M12 2L14.35 8.76L21.51 8.91L15.8 13.24L17.88 20.09L12 16L6.12 20.09L8.2 13.24L2.49 8.91L9.65 8.76Z';
const sizeClasses = {
  sm: 'h-3.5 w-3.5',
  md: 'h-4 w-4',
  lg: 'h-6 w-6',
};

const filledPercentage = computed(() => Math.min(Math.max(props.rating, 0), 5) / 5 * 100);
const ratingLabel = computed(() => {
  const rating = props.rating.toLocaleString('es', { maximumFractionDigits: 1 });
  return `${rating} de 5 estrellas`;
});
</script>
