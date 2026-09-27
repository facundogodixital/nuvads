import { ref } from 'vue';
import { defineStore } from 'pinia';
import KnowledgeSourceService from '@/services/KnowledgeSourceService';

export const useBrandUploadedFileModalStore = defineStore('brandUploadedFileModal', () => {
  const isOpen = ref(false);
  const knowledgeSource = ref(null);
  // analyzing, failed o ready: lo decide quien abre el modal, que sabe si hay un análisis en curso.
  const fileState = ref('ready');
  const canDeleteFile = ref(false);
  const deleteError = ref('');
  const isDeleting = ref(false);
  const isConfirmingDelete = ref(false);

  function open(selectedKnowledgeSource, selectedFileState, canDeleteSelectedFile) {
    reset();
    knowledgeSource.value = selectedKnowledgeSource;
    fileState.value = selectedFileState;
    canDeleteFile.value = canDeleteSelectedFile;
    isOpen.value = true;
  }

  function askToConfirmDelete() {
    isConfirmingDelete.value = true;
  }

  function cancelDelete() {
    isConfirmingDelete.value = false;
  }

  // Borra el archivo y cierra el modal. Devuelve si se pudo borrar.
  async function deleteKnowledgeSource() {
    deleteError.value = '';
    isDeleting.value = true;

    try {
      await KnowledgeSourceService.delete(knowledgeSource.value.id);
      close();
      return true;
    } catch (error) {
      deleteError.value = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
      return false;
    } finally {
      isDeleting.value = false;
    }
  }

  function close() {
    isOpen.value = false;
    reset();
  }

  function reset() {
    knowledgeSource.value = null;
    fileState.value = 'ready';
    canDeleteFile.value = false;
    deleteError.value = '';
    isDeleting.value = false;
    isConfirmingDelete.value = false;
  }

  return {
    isOpen,
    knowledgeSource,
    fileState,
    canDeleteFile,
    deleteError,
    isDeleting,
    isConfirmingDelete,
    open,
    askToConfirmDelete,
    cancelDelete,
    deleteKnowledgeSource,
    close,
  };
});
