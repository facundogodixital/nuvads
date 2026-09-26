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

    <div class="flex-1 p-5">
      <p class="spec-label mb-2">
        Enlace
      </p>
      <a
        :href="sourceUrl"
        target="_blank"
        rel="noopener noreferrer"
        class="block break-all text-sm underline underline-offset-4 hover:text-accent"
      >
        {{ sourceLinkLabel }}<span class="sr-only"> (abre en otra pestaña)</span>
      </a>
      <p class="mt-2 text-xs text-text-muted">
        Para cambiarlo, usa Editar arriba.
      </p>

      <button
        type="button"
        :disabled="!canAnalyze"
        :aria-describedby="`${source.id}-analysis-feedback`"
        class="mt-5 flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-border bg-surface-selected px-3 text-sm font-medium text-text-muted enabled:cursor-pointer enabled:border-accent enabled:bg-accent enabled:text-text-on-accent enabled:hover:bg-accent-hover disabled:cursor-not-allowed"
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
    </div>

    <footer class="border-t border-border bg-surface px-5 py-3 text-xs text-text-muted">
      {{ footerStatus }}
    </footer>
  </section>
</template>


<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import CompetitorResearchRunService from '@/services/CompetitorResearchRunService';

const props = defineProps({
  source: { type: Object, required: true },
  competitor: { type: Object, required: true },
});

const emit = defineEmits(['analyzed']);

const POLLING_INTERVAL_MS = 10000;
// Textos de cada fuente: la etapa de la investigación activa y qué hace el análisis.
const stageLabels = {
  website: {
    pending: 'En cola, empezamos enseguida…',
    scraping: 'Leyendo las páginas de su sitio…',
    analyzing: 'Analizando lo que cuenta su sitio…',
  },
  instagram: {
    pending: 'En cola, empezamos enseguida…',
    scraping: 'Leyendo sus últimos posteos…',
    analyzing: 'Analizando lo que muestran sus posteos…',
  },
  'meta-ads': {
    pending: 'En cola, empezamos enseguida…',
    scraping: 'Buscando sus anuncios en Meta…',
    analyzing: 'Analizando lo que muestran sus anuncios…',
  },
  'google-maps': {
    pending: 'En cola, empezamos enseguida…',
    scraping: 'Leyendo sus reseñas en Google…',
    analyzing: 'Analizando lo que dicen sus clientes…',
  },
};
const analysisHints = {
  website: 'Leemos su sitio y sumamos lo que aprendemos del competidor.',
  instagram: 'Leemos sus últimos posteos y sumamos lo que aprendemos del competidor. Puede tardar unos minutos.',
  'meta-ads': 'Leemos sus anuncios de Instagram y Facebook y sumamos lo que aprendemos del competidor. Puede tardar unos minutos.',
  'google-maps': 'Leemos hasta mil reseñas en Google y sumamos lo que aprendemos del competidor. Puede tardar unos minutos.',
};
const researchStatusLoaders = {
  website: CompetitorResearchRunService.getWebsiteResearchStatus,
  instagram: CompetitorResearchRunService.getInstagramResearchStatus,
  'meta-ads': CompetitorResearchRunService.getMetaAdsResearchStatus,
  'google-maps': CompetitorResearchRunService.getGoogleReviewsResearchStatus,
};

const analysisError = ref('');
const researchStatus = ref(null);
const isStartingAnalysis = ref(false);
let pollingTimer = null;

const activeRun = computed(() => researchStatus.value?.active ?? null);
const latestRun = computed(() => researchStatus.value?.latest ?? null);
// Instagram guarda el usuario; el resto de las fuentes, el enlace completo.
const isInstagram = computed(() => props.source.id === 'instagram');
const savedLink = computed(() => props.competitor[props.source.field]);
const sourceUrl = computed(() => (isInstagram.value ? `https://www.instagram.com/${savedLink.value}/` : savedLink.value));
const sourceLinkLabel = computed(() => (isInstagram.value ? `@${savedLink.value}` : savedLink.value));
const canAnalyze = computed(() => activeRun.value === null && !isStartingAnalysis.value);
const analyzeButtonLabel = computed(() => {
  if (activeRun.value) {
    return 'Analizando…';
  }
  if (isStartingAnalysis.value) {
    return 'Iniciando…';
  }
  return latestRun.value ? 'Volver a analizar' : 'Analizar';
});
const analysisMessage = computed(() => {
  if (activeRun.value) {
    return stageLabels[props.source.id][activeRun.value.status];
  }
  // Una investigación que falló o que no encontró nada para analizar cuenta por qué.
  const latestRunHasStatusMessage = ['failed', 'empty'].includes(latestRun.value?.status);
  if (latestRunHasStatusMessage) {
    return latestRun.value.status_message;
  }
  return analysisHints[props.source.id];
});
const footerStatus = computed(() => {
  const lastCompletedRun = researchStatus.value?.last_completed;
  if (activeRun.value) {
    return 'Analizando…';
  }
  return lastCompletedRun ? `Analizado el ${formatDate(lastCompletedRun.finished_at)}` : 'Sin analizar';
});

onMounted(async () => {
  await loadResearchStatus();
  schedulePolling();
});

onBeforeUnmount(stopPolling);

async function loadResearchStatus() {
  try {
    researchStatus.value = await researchStatusLoaders[props.source.id](props.competitor.id);
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
    await CompetitorResearchRunService.create(props.competitor.id, props.source.researchType);
    await loadResearchStatus();
    schedulePolling();
  } catch (error) {
    analysisError.value = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
  } finally {
    isStartingAnalysis.value = false;
  }
}

// Mientras hay una investigación activa se consulta el estado cada diez segundos. Cuando termina bien, la página
// vuelve a cargar el competidor con lo que el análisis completó.
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
