<template>
  <div class="mt-8 space-y-10">
    <BrandUpdatedProfileFields
      :analysis="analysis"
      description="Sumamos lo que muestran tus fotos y documentos a estas partes."
    />

    <dl class="grid grid-cols-2 gap-3 sm:grid-cols-3">
      <div
        v-for="tile in numberTiles"
        :key="tile.label"
        class="rounded-sm bg-surface p-3"
      >
        <dt class="text-xs text-text-muted">
          {{ tile.label }}
        </dt>
        <dd class="mt-1 text-lg font-medium tabular-nums">
          {{ tile.value }}
        </dd>
      </div>
    </dl>
  </div>
</template>


<script setup>
import { computed } from 'vue';
import BrandUpdatedProfileFields from './BrandUpdatedProfileFields.vue';

const props = defineProps({
  analysis: { type: Object, required: true },
  files: { type: Array, required: true },
});

// Solo cuentan los archivos que entraron en el análisis: los que se pudieron leer.
const numberTiles = computed(() => {
  const analyzedFiles = props.files.filter((file) => props.analysis.knowledge_source_ids.includes(file.id));
  const photosCount = analyzedFiles.filter((file) => file.type === 'image').length;
  return [
    { label: 'Fotos analizadas', value: photosCount },
    { label: 'Documentos analizados', value: analyzedFiles.length - photosCount },
  ];
});
</script>
