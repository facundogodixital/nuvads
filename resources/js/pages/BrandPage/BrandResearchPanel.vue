<template>
  <section
    class="flex min-w-0 flex-col rounded-sm border border-border bg-surface-raised"
    :aria-labelledby="`${source.id}-heading`"
  >
    <header class="flex items-center gap-3 border-b border-border p-5">
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
      <div class="min-w-0 flex-1">
        <h3
          :id="`${source.id}-heading`"
          class="font-medium"
        >
          {{ source.title }}
        </h3>
        <p class="mt-0.5 text-xs text-text-muted">
          {{ source.description }}
        </p>
      </div>
    </header>

    <form
      class="flex-1 p-5"
      @submit.prevent="saveSource"
    >
      <label
        :for="`${source.id}-url`"
        class="spec-label mb-2 block"
      >{{ source.label }}</label>
      <input
        :id="`${source.id}-url`"
        v-model="sourceUrl"
        :type="source.inputType"
        :disabled="!isAvailable || isSaving"
        :aria-invalid="Boolean(saveError)"
        :aria-describedby="`${source.id}-save-feedback`"
        :placeholder="source.placeholder"
        autocomplete="off"
        autocapitalize="none"
        :spellcheck="false"
        class="min-h-11 w-full rounded-sm border border-border bg-surface px-3 text-sm placeholder:text-text-muted"
        @input="clearFeedback"
      >
      <div class="mt-3 flex items-center justify-between gap-3">
        <span
          :id="`${source.id}-save-feedback`"
          role="status"
          class="text-xs"
          :class="saveError ? 'text-danger' : 'text-text-muted'"
        >
          {{ saveError || saveMessage || (isAvailable ? 'Puedes cambiarlo o quitarlo cuando quieras.' : 'Enlaces no disponibles todavía.') }}
        </span>
        <button
          type="submit"
          :disabled="!isAvailable || isSaving || !hasChanges"
          class="min-h-11 shrink-0 rounded-sm border border-border px-3 text-sm font-medium enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
        >
          {{ isSaving ? 'Guardando…' : 'Guardar' }}
        </button>
      </div>
      <button
        type="button"
        :disabled="!canAnalyze"
        :aria-describedby="`${source.id}-analysis-feedback`"
        class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-border bg-surface-selected px-3 text-sm font-medium text-text-muted enabled:cursor-pointer enabled:border-accent enabled:bg-accent enabled:text-text-on-accent enabled:hover:bg-accent-hover disabled:cursor-not-allowed"
        @click="startAnalysis"
      >
        {{ analyzeButtonLabel }}
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
      </button>
      <p
        :id="`${source.id}-analysis-feedback`"
        role="status"
        class="mt-2 text-xs"
        :class="analysisError ? 'text-danger' : 'text-text-muted'"
      >
        {{ analysisError || analysisMessage }}
      </p>
    </form>

    <footer class="flex justify-between gap-3 border-t border-border bg-surface px-5 py-3 text-xs text-text-muted">
      <span>{{ footerStatus }}</span>
      <span>{{ source.resultLabel }}</span>
    </footer>
  </section>
</template>


<script setup>
import BrandService from '@/services/BrandService';
import ResearchRunService from '@/services/ResearchRunService';
import { ref, watch, computed, onMounted, onBeforeUnmount } from 'vue';

const props = defineProps({
  source: { type: Object, required: true },
  savedValue: { type: String, default: '' },
  isAvailable: { type: Boolean, default: false },
});

const emit = defineEmits(['saved', 'analyzed']);

const POLLING_INTERVAL_MS = 10000;
// Textos de cada fuente que se puede analizar: la etapa de la ejecución activa y qué hace el análisis.
const stageLabels = {
  website: {
    pending: 'En cola, empezamos enseguida…',
    scraping: 'Leyendo las páginas de tu sitio…',
    analyzing: 'Analizando lo que cuenta tu sitio…',
  },
  instagram: {
    pending: 'En cola, empezamos enseguida…',
    scraping: 'Leyendo tus últimos posteos…',
    analyzing: 'Analizando lo que muestran tus posteos…',
  },
  'meta-ads': {
    pending: 'En cola, empezamos enseguida…',
    scraping: 'Buscando tus anuncios en Meta…',
    analyzing: 'Analizando lo que muestran tus anuncios…',
  },
};
const analysisHints = {
  website: 'Leemos tu sitio y completamos la información de tu marca.',
  instagram: 'Leemos tus últimos posteos y completamos la información de tu marca. Puede tardar unos minutos.',
  'meta-ads': 'Leemos tus anuncios de Instagram y Facebook y completamos la información de tu marca. Puede tardar unos minutos.',
};
const researchStatusLoaders = {
  website: ResearchRunService.getWebsiteResearchStatus,
  instagram: ResearchRunService.getInstagramResearchStatus,
  'meta-ads': ResearchRunService.getMetaAdsResearchStatus,
};
// Solo el sitio web pisa la marca; Instagram y los anuncios mezclan lo que encuentran con lo que ya tiene.
const researchAttributes = {
  website: { type: 'website', overwrite: true },
  instagram: { type: 'instagram' },
  'meta-ads': { type: 'meta_ads' },
};

const saveError = ref('');
const isSaving = ref(false);
const saveMessage = ref('');
const analysisError = ref('');
const researchStatus = ref(null);
const sourceUrl = ref(props.savedValue);
const isStartingAnalysis = ref(false);
let pollingTimer = null;

const hasChanges = computed(() => sourceUrl.value.trim() !== props.savedValue);
const activeRun = computed(() => researchStatus.value?.active ?? null);
const latestRun = computed(() => researchStatus.value?.latest ?? null);
const canAnalyze = computed(() => {
  const sourceIsSaved = props.isAvailable && props.savedValue !== '';
  const analysisIsIdle = activeRun.value === null && !isStartingAnalysis.value;
  return props.source.isAnalyzable && sourceIsSaved && analysisIsIdle;
});
const analyzeButtonLabel = computed(() => {
  if (activeRun.value) {
    return 'Analizando…';
  }
  if (isStartingAnalysis.value) {
    return 'Iniciando…';
  }
  return latestRun.value ? 'Volver a analizar' : `Analizar ${props.source.title}`;
});
const analysisMessage = computed(() => {
  if (!props.source.isAnalyzable) {
    return 'Análisis disponible próximamente.';
  }
  if (activeRun.value) {
    return stageLabels[props.source.id][activeRun.value.status];
  }
  if (latestRun.value?.status === 'failed') {
    return latestRun.value.error_message;
  }
  return props.savedValue ? analysisHints[props.source.id] : 'Guarda el enlace para poder analizarlo.';
});
const footerStatus = computed(() => {
  const lastCompletedRun = researchStatus.value?.last_completed;
  if (activeRun.value) {
    return 'Analizando…';
  }
  return lastCompletedRun ? `Analizado el ${formatDate(lastCompletedRun.finished_at)}` : 'Sin analizar';
});

watch(() => props.savedValue, (value) => {
  sourceUrl.value = value;
});

onMounted(async () => {
  if (!props.source.isAnalyzable) {
    return;
  }
  await loadResearchStatus();
  schedulePolling();
});

onBeforeUnmount(stopPolling);

function clearFeedback() {
  saveError.value = '';
  saveMessage.value = '';
}

async function saveSource() {
  const cannotSave = !props.isAvailable || isSaving.value || !hasChanges.value;
  if (cannotSave) {
    return;
  }

  clearFeedback();
  isSaving.value = true;

  try {
    const brand = await BrandService.update({ [props.source.field]: sourceUrl.value.trim() || null });
    const savedValue = brand[props.source.field] ?? '';

    sourceUrl.value = savedValue;
    emit('saved', brand);
    saveMessage.value = savedValue ? 'Guardado.' : 'Enlace eliminado.';
  } catch (error) {
    saveError.value = error.errors?.[props.source.field]?.[0] ?? error.message;
  } finally {
    isSaving.value = false;
  }
}

async function loadResearchStatus() {
  try {
    researchStatus.value = await researchStatusLoaders[props.source.id]();
    analysisError.value = '';
  } catch (error) {
    analysisError.value = error.message;
  }
}

async function startAnalysis() {
  if (!canAnalyze.value) {
    return;
  }

  analysisError.value = '';
  isStartingAnalysis.value = true;

  try {
    await ResearchRunService.create(researchAttributes[props.source.id]);
    await loadResearchStatus();
    schedulePolling();
  } catch (error) {
    analysisError.value = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
  } finally {
    isStartingAnalysis.value = false;
  }
}

// Mientras hay una ejecución activa se consulta el estado cada diez segundos. Cuando termina bien,
// la página vuelve a cargar la marca con lo que el análisis completó.
function schedulePolling() {
  stopPolling();
  if (activeRun.value === null) {
    return;
  }
  pollingTimer = setTimeout(pollResearchStatus, POLLING_INTERVAL_MS);
}

async function pollResearchStatus() {
  await loadResearchStatus();

  const runHasCompleted = activeRun.value === null && latestRun.value?.status === 'completed';
  if (runHasCompleted) {
    emit('analyzed');
  }
  schedulePolling();
}

function stopPolling() {
  clearTimeout(pollingTimer);
  pollingTimer = null;
}

function formatDate(isoDate) {
  return new Date(isoDate).toLocaleString('es', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' });
}
</script>
