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
      class="p-5"
      @submit.prevent="uploadFiles"
    >
      <label
        :for="`${source.id}-files`"
        class="spec-label mb-2 block"
      >{{ source.label }}</label>
      <input
        :id="`${source.id}-files`"
        ref="fileInput"
        type="file"
        multiple
        :accept="ACCEPTED_FILE_TYPES"
        :disabled="isAnalysisActive || isUploading"
        :aria-describedby="`${source.id}-files-hint ${source.id}-analysis-feedback`"
        class="block min-h-11 w-full cursor-pointer rounded-sm border border-border bg-surface text-sm text-text-muted file:mr-3 file:min-h-11 file:cursor-pointer file:border-0 file:border-r file:border-border file:bg-surface-selected file:px-3 file:text-sm file:font-medium file:text-text disabled:cursor-not-allowed"
        @change="selectFiles"
      >
      <p
        :id="`${source.id}-files-hint`"
        class="mt-3 text-xs text-text-muted"
      >
        Fotos (jpg, png, webp o gif) y documentos (PDF, Word, Excel, PowerPoint o texto). Hasta 20 MB entre todos.
      </p>
      <button
        type="submit"
        :disabled="!canUpload"
        :aria-describedby="`${source.id}-analysis-feedback`"
        class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-border bg-surface-selected px-3 text-sm font-medium text-text-muted enabled:cursor-pointer enabled:border-accent enabled:bg-accent enabled:text-text-on-accent enabled:hover:bg-accent-hover disabled:cursor-not-allowed"
      >
        {{ uploadButtonLabel }}
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

    <section
      class="border-t border-border p-5"
      aria-labelledby="uploaded-files-heading"
    >
      <h4
        id="uploaded-files-heading"
        class="spec-label mb-3"
      >
        Tus archivos{{ files.length ? ` (${files.length})` : '' }}
      </h4>
      <p
        v-if="!files.length"
        class="rounded-sm border border-dashed border-border p-4 text-center text-sm text-text-muted"
      >
        Todavía no subiste fotos ni documentos.
      </p>
      <ul
        v-else
        class="grid grid-cols-3 gap-2 sm:grid-cols-4 lg:grid-cols-3"
      >
        <li
          v-for="fileTile in fileTiles"
          :key="fileTile.file.id"
        >
          <button
            type="button"
            :aria-label="`Ver ${fileTile.file.title}`"
            class="relative block aspect-square w-full cursor-pointer overflow-hidden rounded-sm border border-border bg-surface text-left hover:border-text-muted"
            @click="openFile(fileTile)"
          >
            <img
              v-if="fileTile.file.type === 'image'"
              :src="fileTile.file.url"
              alt=""
              loading="lazy"
              class="h-full w-full object-cover"
            >
            <span
              v-else
              class="flex h-full w-full flex-col items-center justify-center gap-1 p-2 text-center"
            >
              <svg
                class="h-6 w-6 text-text-muted"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
              ><path
                d="M14 3H7a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h10a2 2 0 0 0 2-2V8l-5-5ZM14 3v5h5M9 13h6M9 17h6"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              /></svg>
              <span class="spec-label">{{ fileTile.extension }}</span>
              <span class="line-clamp-2 text-xs break-all">{{ fileTile.file.title }}</span>
            </span>
            <span
              v-if="fileTile.state !== 'ready'"
              class="absolute inset-x-0 bottom-0 bg-surface-raised/90 px-1 py-1 text-center text-xs"
              :class="fileTile.state === 'failed' ? 'text-warning' : 'text-text-muted'"
            >
              {{ fileTile.state === 'failed' ? 'No pudimos leerlo' : 'Analizando…' }}
            </span>
          </button>
        </li>
      </ul>
    </section>

    <footer class="flex justify-between gap-3 border-t border-border bg-surface px-5 py-3 text-xs text-text-muted">
      <span>{{ footerStatus }}</span>
      <span>{{ source.resultLabel }}</span>
    </footer>

    <BrandUploadedFileModal @deleted="handleFileDeleted" />
  </section>
</template>


<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import BrandUploadedFileModal from './BrandUploadedFileModal.vue';
import ResearchRunService from '@/services/ResearchRunService';
import { useBrandUploadedFileModalStore } from '@/stores/brandUploadedFileModalStore';

const props = defineProps({
  source: { type: Object, required: true },
  files: { type: Array, required: true },
});

const emit = defineEmits(['files-changed', 'analyzed']);

const POLLING_INTERVAL_MS = 10000;
// El mismo límite de subida que PHP y nginx, para todos los archivos juntos; si lo pasan, ni se envían.
const MAX_UPLOAD_SIZE_BYTES = 20 * 1024 * 1024;
// Los formatos que acepta el backend: los mismos que puede leer OpenAI.
const ACCEPTED_FILE_TYPES = '.jpg,.jpeg,.png,.webp,.gif,.pdf,.doc,.docx,.odt,.rtf,.txt,.md,.csv,.xls,.xlsx,.ppt,.pptx';
const stageLabels = {
  pending: 'En cola, empezamos enseguida…',
  scraping: 'Mirando tus fotos y leyendo tus documentos…',
  analyzing: 'Juntando lo que aprendimos de tus archivos…',
};

const brandUploadedFileModalStore = useBrandUploadedFileModalStore();

const fileInput = ref(null);
const isUploading = ref(false);
const selectedFiles = ref([]);
const analysisError = ref('');
const researchStatus = ref(null);
let pollingTimer = null;

const activeRun = computed(() => researchStatus.value?.active ?? null);
const latestRun = computed(() => researchStatus.value?.latest ?? null);
const isAnalysisActive = computed(() => activeRun.value !== null);
const canUpload = computed(() => selectedFiles.value.length > 0 && !isAnalysisActive.value && !isUploading.value);
const uploadButtonLabel = computed(() => {
  if (isUploading.value) {
    return 'Subiendo…';
  }
  if (isAnalysisActive.value) {
    return 'Analizando…';
  }
  return 'Subir y analizar';
});
const analysisMessage = computed(() => {
  if (activeRun.value) {
    return stageLabels[activeRun.value.status];
  }
  // Una corrida que falló o que no encontró nada para analizar cuenta por qué.
  const latestRunHasStatusMessage = ['failed', 'empty'].includes(latestRun.value?.status);
  if (latestRunHasStatusMessage) {
    return latestRun.value.status_message;
  }
  if (selectedFiles.value.length) {
    const selectedFilesCount = selectedFiles.value.length;
    return selectedFilesCount === 1 ? 'Elegiste 1 archivo.' : `Elegiste ${selectedFilesCount} archivos.`;
  }
  return 'Sube fotos de tus productos, tu local o tu equipo, y documentos como tu menú o tu catálogo. Los analizamos al subirlos y completamos la información de tu marca.';
});
const footerStatus = computed(() => {
  const lastCompletedRun = researchStatus.value?.last_completed;
  if (activeRun.value) {
    return 'Analizando…';
  }
  return lastCompletedRun ? `Analizado el ${formatDate(lastCompletedRun.finished_at)}` : 'Sin analizar';
});
// Un archivo pendiente solo se está analizando mientras hay un análisis en curso; si no, quedó sin leer.
const fileTiles = computed(() => props.files.map((file) => {
  const isPending = file.status === 'pending';
  const isFailed = file.status === 'failed' || (isPending && !isAnalysisActive.value);
  let state = 'ready';
  if (isFailed) {
    state = 'failed';
  } else if (isPending) {
    state = 'analyzing';
  }
  return { file, state, extension: file.title.split('.').pop().toUpperCase() };
}));

onMounted(async () => {
  await loadResearchStatus();
  schedulePolling();
});

onBeforeUnmount(stopPolling);

async function loadResearchStatus() {
  try {
    researchStatus.value = await ResearchRunService.getUploadedFilesResearchStatus();
    analysisError.value = '';
  } catch (error) {
    analysisError.value = error.message;
  }
}

function selectFiles(event) {
  analysisError.value = '';
  selectedFiles.value = Array.from(event.target.files);

  const selectedSize = selectedFiles.value.reduce((totalSize, file) => totalSize + file.size, 0);
  const isTooLarge = selectedSize > MAX_UPLOAD_SIZE_BYTES;
  if (isTooLarge) {
    analysisError.value = 'Entre todos pesan más de 20 MB. Elige menos archivos o súbelos en varias tandas.';
    clearSelectedFiles();
  }
}

function clearSelectedFiles() {
  selectedFiles.value = [];
  fileInput.value.value = '';
}

async function uploadFiles() {
  if (!canUpload.value) {
    return;
  }

  analysisError.value = '';
  isUploading.value = true;

  try {
    await ResearchRunService.createUploadedFilesResearch(selectedFiles.value);
    clearSelectedFiles();
    emit('files-changed');
    await loadResearchStatus();
    schedulePolling();
  } catch (error) {
    analysisError.value = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
  } finally {
    isUploading.value = false;
  }
}

function openFile(fileTile) {
  brandUploadedFileModalStore.open(fileTile.file, fileTile.state, !isAnalysisActive.value);
}

// El borrado arranca un nuevo análisis de los archivos que quedan: se sigue su estado como el de una subida.
async function handleFileDeleted() {
  emit('files-changed');
  await loadResearchStatus();
  schedulePolling();
}

// Mientras hay una ejecución activa se consulta el estado cada diez segundos. Al terminar cambia el estado de los
// archivos, y si terminó bien, la página vuelve a cargar la marca con lo que completó el análisis.
function schedulePolling() {
  stopPolling();
  if (activeRun.value === null) {
    return;
  }
  pollingTimer = setTimeout(pollResearchStatus, POLLING_INTERVAL_MS);
}

async function pollResearchStatus() {
  await loadResearchStatus();

  const runHasFinished = activeRun.value === null;
  if (runHasFinished) {
    const runHasCompleted = latestRun.value?.status === 'completed';
    emit(runHasCompleted ? 'analyzed' : 'files-changed');
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
