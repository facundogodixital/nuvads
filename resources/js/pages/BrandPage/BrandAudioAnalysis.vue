<template>
  <div class="mt-8 space-y-8">
    <BrandUpdatedProfileFields
      :analysis="analysis"
      description="Sumamos lo que contaste en tu audio a estas partes."
    />

    <section aria-labelledby="audio-transcript-heading">
      <h3
        id="audio-transcript-heading"
        class="spec-label mb-3"
      >
        Lo que contaste
      </h3>
      <details class="group">
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
          Ver la transcripción
        </summary>
        <p class="mt-2 max-w-prose whitespace-pre-line text-sm leading-6">
          {{ audio.payload.transcript }}
        </p>
      </details>
    </section>
  </div>
</template>


<script setup>
import BrandUpdatedProfileFields from './BrandUpdatedProfileFields.vue';

defineProps({
  analysis: { type: Object, required: true },
  audio: { type: Object, required: true },
});
</script>
