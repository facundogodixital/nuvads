<template>
  <div
    v-if="competitorModalStore.isOpen"
    class="modal-scrim fixed inset-0 z-50 flex items-center justify-center p-4"
    @click.self="competitorModalStore.close()"
  >
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="competitor-modal-heading"
      class="flex max-h-full w-full max-w-[432px] flex-col rounded-sm border border-border bg-surface-raised"
      @keydown.esc="competitorModalStore.close()"
    >
      <header class="border-b border-border px-5 py-4">
        <h2
          id="competitor-modal-heading"
          class="font-medium"
        >
          {{ isNewCompetitor ? 'Sumar competidor' : 'Editar competidor' }}
        </h2>
        <p class="mt-1 text-sm text-text-muted">
          Carga los enlaces que tengas. Puedes sumar el resto cuando quieras.
        </p>
      </header>

      <!-- Los enlaces se validan en el servidor, que también acepta formas cortas como facebook.com/sumarca. -->
      <form
        ref="form"
        novalidate
        class="min-h-0 flex-1 space-y-4 overflow-y-auto p-5"
        @submit.prevent="save"
      >
        <div
          v-for="field in fields"
          :key="field.key"
        >
          <label
            :for="`competitor-${field.key}`"
            class="spec-label mb-2 block"
          >{{ field.label }}</label>
          <input
            :id="`competitor-${field.key}`"
            v-model="competitorModalStore.form[field.key]"
            :type="field.type"
            :name="field.key"
            :placeholder="field.placeholder"
            :aria-invalid="Boolean(competitorModalStore.fieldErrors[field.key])"
            autocomplete="off"
            autocapitalize="none"
            :spellcheck="false"
            class="min-h-11 w-full rounded-sm border border-border bg-surface-raised px-3 text-sm placeholder:text-text-muted"
          >
          <p
            v-if="competitorModalStore.fieldErrors[field.key]"
            class="mt-1 text-xs text-danger"
          >
            {{ competitorModalStore.fieldErrors[field.key][0] }}
          </p>
        </div>

        <p
          v-if="competitorModalStore.saveError"
          role="alert"
          class="text-sm text-danger"
        >
          {{ competitorModalStore.saveError }}
        </p>

        <div class="flex flex-wrap items-center gap-3 pt-1">
          <button
            type="submit"
            :disabled="competitorModalStore.isSaving"
            class="inline-flex min-h-11 items-center rounded-sm bg-accent px-5 text-sm font-medium text-text-on-accent enabled:cursor-pointer enabled:hover:bg-accent-hover disabled:cursor-not-allowed disabled:bg-surface-selected disabled:text-text-muted"
          >
            {{ competitorModalStore.isSaving ? 'Guardando…' : 'Guardar' }}
          </button>
          <button
            type="button"
            :disabled="competitorModalStore.isSaving"
            class="inline-flex min-h-11 items-center rounded-sm border border-border px-4 text-sm enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
            @click="competitorModalStore.close()"
          >
            Cancelar
          </button>
        </div>
      </form>
    </div>
  </div>
</template>


<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { useCompetitorModalStore } from '@/stores/competitorModalStore';

const emit = defineEmits(['saved']);

const competitorModalStore = useCompetitorModalStore();

const form = ref(null);
let openerElement = null;

const fields = [
  { key: 'name', label: 'Nombre', type: 'text', placeholder: 'Cómo se llama el negocio' },
  { key: 'website_url', label: 'Sitio web', type: 'url', placeholder: 'https://sumarca.com' },
  { key: 'instagram_username', label: 'Instagram', type: 'text', placeholder: '@sumarca' },
  { key: 'meta_ads_url', label: 'Página de Facebook, para sus anuncios', type: 'url', placeholder: 'https://www.facebook.com/sumarca' },
  { key: 'google_maps_url', label: 'Google Maps, para sus reseñas', type: 'url', placeholder: 'https://maps.google.com/…' },
];

const isNewCompetitor = computed(() => competitorModalStore.competitorId === null);

// Al abrir, el foco va al campo pedido, por ejemplo el enlace que se eligió sumar; al cerrar, vuelve a lo que se
// clickeó.
watch(() => competitorModalStore.isOpen, async (isOpen) => {
  if (!isOpen) {
    openerElement?.focus();
    openerElement = null;
    return;
  }
  openerElement = document.activeElement;
  await nextTick();
  form.value?.querySelector(`[name="${competitorModalStore.focusedField}"]`)?.focus();
});

async function save() {
  const competitor = await competitorModalStore.save();
  if (competitor) {
    emit('saved', competitor);
  }
}
</script>


<style scoped>
.modal-scrim {
  background-color: var(--scrim);
}
</style>
