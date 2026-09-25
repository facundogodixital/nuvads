<template>
  <div
    v-if="brandUploadedFileModalStore.isOpen"
    class="modal-scrim fixed inset-0 z-50 flex items-center justify-center p-4"
    @click.self="brandUploadedFileModalStore.close()"
  >
    <div
      ref="dialog"
      role="dialog"
      aria-modal="true"
      aria-labelledby="uploaded-file-heading"
      tabindex="-1"
      class="flex max-h-full w-full max-w-2xl flex-col rounded-sm border border-border bg-surface-raised outline-none"
      @keydown.esc="brandUploadedFileModalStore.close()"
    >
      <header class="border-b border-border px-4 py-3">
        <h2
          id="uploaded-file-heading"
          class="truncate font-medium"
        >
          {{ uploadedFile.title }}
        </h2>
        <p class="mt-0.5 text-xs text-text-muted">
          {{ fileKindLabel }} · {{ formatFileSize(uploadedFile.payload.size) }}
        </p>
      </header>

      <div class="min-h-0 flex-1 overflow-y-auto">
        <div
          v-if="isImage"
          class="flex items-center justify-center bg-surface p-3"
        >
          <img
            :src="uploadedFile.url"
            :alt="uploadedFile.payload.description ?? uploadedFile.title"
            class="max-h-[50dvh] max-w-full rounded-sm object-contain"
          >
        </div>

        <div class="space-y-5 p-4">
          <p
            v-if="brandUploadedFileModalStore.fileState === 'analyzing'"
            role="status"
            class="rounded-sm bg-surface p-3 text-sm text-text-muted"
          >
            Todavía lo estamos analizando.
          </p>
          <p
            v-else-if="brandUploadedFileModalStore.fileState === 'failed'"
            role="status"
            class="rounded-sm bg-warning-soft p-3 text-sm leading-6 text-warning"
          >
            No pudimos leer este archivo. Puedes borrarlo y subir otra versión, por ejemplo en PDF o en jpg.
          </p>
          <template v-else>
            <section aria-labelledby="uploaded-file-description-heading">
              <h3
                id="uploaded-file-description-heading"
                class="spec-label mb-1"
              >
                {{ isImage ? 'Lo que vemos' : 'Qué es' }}
              </h3>
              <p class="text-sm leading-6">
                {{ uploadedFile.payload.description }}
              </p>
            </section>
            <section
              v-if="extractedText"
              aria-labelledby="uploaded-file-text-heading"
            >
              <h3
                id="uploaded-file-text-heading"
                class="spec-label mb-1"
              >
                {{ isImage ? 'Texto en la imagen' : 'Lo que sacamos del documento' }}
              </h3>
              <p class="whitespace-pre-line text-sm leading-6">
                {{ extractedText }}
              </p>
            </section>
          </template>
        </div>
      </div>

      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-4 py-2">
        <div
          v-if="brandUploadedFileModalStore.isConfirmingDelete"
          class="flex flex-wrap items-center gap-2"
        >
          <span class="text-sm">Se borran el archivo y lo que aprendimos de él.</span>
          <button
            type="button"
            :disabled="brandUploadedFileModalStore.isDeleting"
            class="min-h-11 shrink-0 rounded-sm border border-danger px-3 text-sm font-medium text-danger enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:opacity-60"
            @click="deleteUploadedFile"
          >
            {{ brandUploadedFileModalStore.isDeleting ? 'Borrando…' : 'Sí, borrar' }}
          </button>
          <button
            type="button"
            :disabled="brandUploadedFileModalStore.isDeleting"
            class="min-h-11 shrink-0 rounded-sm px-3 text-sm enabled:cursor-pointer enabled:hover:bg-surface-selected"
            @click="brandUploadedFileModalStore.cancelDelete()"
          >
            Cancelar
          </button>
        </div>
        <div
          v-else
          class="flex flex-wrap items-center gap-2"
        >
          <button
            type="button"
            :disabled="!brandUploadedFileModalStore.canDeleteFile"
            aria-describedby="uploaded-file-delete-feedback"
            class="min-h-11 shrink-0 rounded-sm px-3 text-sm text-danger enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
            @click="brandUploadedFileModalStore.askToConfirmDelete()"
          >
            Borrar archivo
          </button>
          <span
            id="uploaded-file-delete-feedback"
            role="status"
            class="text-xs"
            :class="brandUploadedFileModalStore.deleteError ? 'text-danger' : 'text-text-muted'"
          >
            {{ deleteFeedback }}
          </span>
        </div>

        <div class="ml-auto flex items-center gap-3">
          <a
            :href="uploadedFile.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-11 items-center text-sm underline underline-offset-4 hover:text-accent"
          >
            Abrir archivo
          </a>
          <button
            type="button"
            class="min-h-11 shrink-0 cursor-pointer rounded-sm border border-border px-3 text-sm font-medium hover:bg-surface-selected"
            @click="brandUploadedFileModalStore.close()"
          >
            Cerrar
          </button>
        </div>
      </footer>
    </div>
  </div>
</template>


<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { useBrandUploadedFileModalStore } from '@/stores/brandUploadedFileModalStore';

const emit = defineEmits(['deleted']);

const brandUploadedFileModalStore = useBrandUploadedFileModalStore();

const dialog = ref(null);
let openerElement = null;

const uploadedFile = computed(() => brandUploadedFileModalStore.uploadedFile);
const isImage = computed(() => uploadedFile.value.type === 'image');
// Las fotos dicen Foto; los documentos, su formato, como PDF o DOCX.
const fileKindLabel = computed(() => {
  if (isImage.value) {
    return 'Foto';
  }
  return uploadedFile.value.title.split('.').pop().toUpperCase();
});
// El texto que tiene la foto, o lo que se sacó del documento.
const extractedText = computed(() => {
  const payload = uploadedFile.value.payload;
  return isImage.value ? payload.transcription : payload.content;
});
const deleteFeedback = computed(() => {
  if (brandUploadedFileModalStore.deleteError) {
    return brandUploadedFileModalStore.deleteError;
  }
  return brandUploadedFileModalStore.canDeleteFile ? '' : 'Podrás borrarlo cuando termine el análisis.';
});

// Al abrir, el foco entra al diálogo para que funcione Escape; al cerrar, vuelve a lo que se clickeó.
watch(() => brandUploadedFileModalStore.isOpen, async (isOpen) => {
  if (!isOpen) {
    openerElement?.focus();
    openerElement = null;
    return;
  }
  openerElement = document.activeElement;
  await nextTick();
  dialog.value?.focus();
});

async function deleteUploadedFile() {
  const researchRun = await brandUploadedFileModalStore.deleteUploadedFile();
  if (researchRun) {
    emit('deleted', researchRun);
  }
}

function formatFileSize(sizeInBytes) {
  const sizeInKilobytes = sizeInBytes / 1024;
  if (sizeInKilobytes < 1024) {
    return `${Math.max(1, Math.round(sizeInKilobytes))} KB`;
  }
  return `${(sizeInKilobytes / 1024).toLocaleString('es', { maximumFractionDigits: 1 })} MB`;
}
</script>


<style scoped>
.modal-scrim {
  background-color: var(--scrim);
}
</style>
