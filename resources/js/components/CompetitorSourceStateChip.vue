<template>
  <span
    class="rounded-sm border px-2 py-1 text-xs"
    :class="sourceState.classes"
  >
    {{ sourceTitle ? `${sourceTitle} · ${sourceState.label}` : sourceState.label }}
  </span>
</template>


<script setup>
import { computed } from 'vue';

const props = defineProps({
  // El estado de la fuente en la API: active, latest y last_completed, cada uno una investigación o null.
  researchStatus: { type: Object, required: true },
  // El nombre de la fuente, cuando el chip tiene que decirlo, como en las tarjetas de Competencia.
  sourceTitle: { type: String, default: '' },
});

// Si se está analizando, si alguna vez terminó bien, o cómo terminó la última.
const sourceState = computed(() => {
  const researchStatus = props.researchStatus;
  if (researchStatus.active) {
    return { label: 'Analizando…', classes: 'border-border bg-surface-selected text-text-muted' };
  }
  if (researchStatus.last_completed) {
    return { label: 'Analizada', classes: 'border-transparent bg-success-soft text-success' };
  }
  if (researchStatus.latest?.status === 'failed') {
    return { label: 'Falló', classes: 'border-danger text-danger' };
  }
  if (researchStatus.latest?.status === 'empty') {
    return { label: 'Sin datos', classes: 'border-transparent bg-warning-soft text-warning' };
  }
  return { label: 'Sin analizar', classes: 'border-border text-text-muted' };
});
</script>
