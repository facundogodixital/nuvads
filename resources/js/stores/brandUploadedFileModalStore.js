import { ref } from 'vue';
import { defineStore } from 'pinia';
import UploadedFileService from '@/services/UploadedFileService';

export const useBrandUploadedFileModalStore = defineStore('brandUploadedFileModal', () => {
  const isOpen = ref(false);
  const uploadedFile = ref(null);
  // analyzing, failed o ready: lo decide quien abre el modal, que sabe si hay un análisis en curso.
  const fileState = ref('ready');
  const canDeleteFile = ref(false);
  const deleteError = ref('');
  const isDeleting = ref(false);
  const isConfirmingDelete = ref(false);

  function open(selectedFile, selectedFileState, canDeleteSelectedFile) {
    reset();
    uploadedFile.value = selectedFile;
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

  // Borra el archivo y cierra el modal. Devuelve el nuevo análisis de los archivos que quedan, o null si no se pudo.
  async function deleteUploadedFile() {
    deleteError.value = '';
    isDeleting.value = true;

    try {
      const researchRun = await UploadedFileService.delete(uploadedFile.value.id);
      close();
      return researchRun;
    } catch (error) {
      deleteError.value = Object.values(error.errors ?? {})[0]?.[0] ?? error.message;
      return null;
    } finally {
      isDeleting.value = false;
    }
  }

  function close() {
    isOpen.value = false;
    reset();
  }

  function reset() {
    uploadedFile.value = null;
    fileState.value = 'ready';
    canDeleteFile.value = false;
    deleteError.value = '';
    isDeleting.value = false;
    isConfirmingDelete.value = false;
  }

  return {
    isOpen,
    uploadedFile,
    fileState,
    canDeleteFile,
    deleteError,
    isDeleting,
    isConfirmingDelete,
    open,
    askToConfirmDelete,
    cancelDelete,
    deleteUploadedFile,
    close,
  };
});
