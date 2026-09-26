import { ref } from 'vue';
import { defineStore } from 'pinia';
import CompetitorService from '@/services/CompetitorService';

// El modal da de alta o edita el nombre y los enlaces de un competidor.
export const useCompetitorModalStore = defineStore('competitorModal', () => {
  const isOpen = ref(false);
  // null al dar de alta; el ID del competidor al editarlo.
  const competitorId = ref(null);
  const form = ref(getEmptyForm());
  // El campo que recibe el foco al abrir, por ejemplo el enlace que el usuario eligió sumar.
  const focusedField = ref('name');
  const saveError = ref('');
  const isSaving = ref(false);
  const fieldErrors = ref({});

  function openToCreate() {
    reset();
    isOpen.value = true;
  }

  function openToEdit(competitor, fieldToFocus = 'name') {
    reset();
    competitorId.value = competitor.id;
    focusedField.value = fieldToFocus;
    for (const field of Object.keys(form.value)) {
      form.value[field] = competitor[field] ?? '';
    }
    isOpen.value = true;
  }

  // Guarda y cierra el modal. Devuelve el competidor guardado, o null si no se pudo.
  async function save() {
    const attributes = {};
    for (const [field, value] of Object.entries(form.value)) {
      attributes[field] = value.trim() || null;
    }

    isSaving.value = true;
    saveError.value = '';
    fieldErrors.value = {};

    try {
      const isNewCompetitor = competitorId.value === null;
      const competitor = isNewCompetitor
        ? await CompetitorService.create(attributes)
        : await CompetitorService.update(competitorId.value, attributes);
      close();
      return competitor;
    } catch (error) {
      saveError.value = error.message;
      fieldErrors.value = error.errors ?? {};
      return null;
    } finally {
      isSaving.value = false;
    }
  }

  function close() {
    isOpen.value = false;
    reset();
  }

  function reset() {
    saveError.value = '';
    fieldErrors.value = {};
    isSaving.value = false;
    competitorId.value = null;
    focusedField.value = 'name';
    form.value = getEmptyForm();
  }

  function getEmptyForm() {
    return { name: '', website_url: '', instagram_username: '', meta_ads_url: '', google_maps_url: '' };
  }

  return {
    isOpen,
    competitorId,
    form,
    focusedField,
    isSaving,
    saveError,
    fieldErrors,
    openToCreate,
    openToEdit,
    save,
    close,
  };
});
