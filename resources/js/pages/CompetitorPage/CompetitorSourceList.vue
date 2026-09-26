<template>
  <section aria-labelledby="competitor-sources-heading">
    <h2
      id="competitor-sources-heading"
      class="spec-label mb-2"
    >
      Fuentes
    </h2>
    <ul
      v-if="linkedSources.length"
      class="divide-y divide-border rounded-sm border border-border bg-surface-raised"
    >
      <li
        v-for="source in linkedSources"
        :key="source.id"
      >
        <RouterLink
          :to="`/competitors/${competitor.id}/sources/${source.id}`"
          class="flex min-h-16 items-center gap-4 px-4 py-3 hover:bg-surface-selected"
        >
          <span class="flex h-10 w-10 shrink-0 items-center justify-center rounded-sm bg-surface-selected">
            <svg
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            >
              <path
                :d="source.icon"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
          </span>
          <span class="min-w-0 flex-1">
            <span class="block font-medium">{{ source.title }}</span>
            <span class="block text-xs text-text-muted">{{ source.description }}</span>
          </span>
          <CompetitorSourceStateChip
            class="shrink-0"
            :research-status="researchStatuses[source.researchType]"
          />
        </RouterLink>
      </li>
    </ul>
    <p
      v-else
      class="rounded-sm border border-dashed border-border p-6 text-center text-sm text-text-muted"
    >
      Todavía no cargaste ningún enlace de este competidor.
    </p>

    <div
      v-if="sourcesWithoutLink.length"
      class="mt-3 flex flex-wrap gap-2"
    >
      <button
        v-for="source in sourcesWithoutLink"
        :key="source.id"
        type="button"
        class="inline-flex min-h-11 cursor-pointer items-center gap-2 rounded-sm border border-border px-3 text-sm hover:bg-surface-selected"
        @click="emit('add-link', source)"
      >
        + {{ source.addLabel }}
      </button>
    </div>
  </section>
</template>


<script setup>
import { computed } from 'vue';
import { RouterLink } from 'vue-router';
import CompetitorSourceStateChip from '@/components/CompetitorSourceStateChip.vue';

const props = defineProps({
  competitor: { type: Object, required: true },
  sources: { type: Array, required: true },
  // El estado de cada fuente por tipo de investigación: active, latest y last_completed.
  researchStatuses: { type: Object, required: true },
});

const emit = defineEmits(['add-link']);

// Cada competidor tiene las fuentes que el usuario cargó; las demás se ofrecen para sumar.
const linkedSources = computed(() => props.sources.filter((source) => props.competitor[source.field]));
const sourcesWithoutLink = computed(() => props.sources.filter((source) => !props.competitor[source.field]));
</script>
