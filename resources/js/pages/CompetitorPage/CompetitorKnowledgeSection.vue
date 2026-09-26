<template>
  <section
    class="rounded-sm border border-border bg-surface-raised p-5 sm:p-6"
    aria-labelledby="competitor-knowledge-heading"
  >
    <header class="mb-5 flex items-start justify-between gap-4 border-b border-border pb-5">
      <div>
        <h2
          id="competitor-knowledge-heading"
          class="text-lg font-medium"
        >
          Lo que sabemos
        </h2>
        <p class="mt-1 max-w-xl text-sm leading-6 text-text-muted">
          Cada fuente que analizamos suma lo suyo acá. Puedes corregirlo cuando quieras.
        </p>
      </div>
      <button
        v-if="!isEditing"
        ref="editButton"
        type="button"
        class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-sm border border-border px-3 py-2 text-sm hover:bg-surface-selected"
        aria-label="Editar lo que sabemos del competidor"
        @click="startEditing"
      >
        <svg
          class="h-4 w-4"
          viewBox="0 0 20 20"
          fill="none"
          aria-hidden="true"
        >
          <path
            d="m12.5 4.5 3 3M4 16l3.5-.5L16 7a2.12 2.12 0 0 0-3-3l-8.5 8.5L4 16Z"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linejoin="round"
          />
        </svg>
        Editar
      </button>
    </header>

    <form
      v-if="isEditing"
      ref="editor"
      class="space-y-4"
      @submit.prevent="save"
      @keydown.esc.prevent="cancelEditing"
    >
      <div
        v-for="field in knowledgeFields"
        :key="field.key"
      >
        <label
          :for="`competitor-knowledge-${field.key}`"
          class="spec-label mb-2 block"
        >{{ field.label }}</label>
        <textarea
          :id="`competitor-knowledge-${field.key}`"
          v-model="draft[field.key]"
          rows="4"
          :placeholder="field.hint"
          :aria-invalid="Boolean(fieldErrors[field.key])"
          class="block w-full resize-y rounded-sm border border-border bg-surface-raised px-3.5 py-2.5 text-sm leading-6 text-text placeholder:text-text-muted"
        />
        <p
          v-if="fieldErrors[field.key]"
          class="mt-1 text-xs text-danger"
        >
          {{ fieldErrors[field.key][0] }}
        </p>
      </div>
      <div class="flex flex-wrap items-center gap-3 pt-1">
        <button
          type="submit"
          :disabled="isSaving"
          class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-sm border border-border bg-surface-selected px-3 py-2 text-sm enabled:cursor-pointer enabled:hover:bg-surface disabled:cursor-not-allowed disabled:text-text-muted"
        >
          {{ isSaving ? 'Guardando…' : 'Guardar' }}
        </button>
        <button
          type="button"
          :disabled="isSaving"
          class="inline-flex min-h-11 shrink-0 items-center gap-2 rounded-sm border border-border px-3 py-2 text-sm enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
          @click="cancelEditing"
        >
          Cancelar
        </button>
        <span
          v-if="saveError"
          role="alert"
          class="text-xs text-danger"
        >{{ saveError }}</span>
      </div>
    </form>

    <dl
      v-else
      class="grid gap-4 sm:grid-cols-2"
    >
      <div
        v-for="field in knowledgeFields"
        :key="field.key"
        class="min-w-0 rounded-sm bg-surface p-3"
      >
        <dt class="spec-label mb-2">
          {{ field.label }}
        </dt>
        <dd
          v-if="competitor[field.key]"
          class="whitespace-pre-wrap break-words text-sm leading-6"
        >
          {{ competitor[field.key] }}
        </dd>
        <dd
          v-else
          class="text-sm leading-6 text-text-muted"
        >
          Sin información todavía
        </dd>
      </div>
    </dl>
    <p
      class="sr-only"
      role="status"
    >
      {{ announcement }}
    </p>
  </section>
</template>


<script setup>
import { ref, nextTick } from 'vue';
import CompetitorService from '@/services/CompetitorService';

const props = defineProps({
  competitor: { type: Object, required: true },
});

const emit = defineEmits(['saved']);

const knowledgeFields = [
  { key: 'competitor_offer_description', label: 'Qué vende', hint: 'Productos, servicios, precios y promociones' },
  { key: 'competitor_differentiators_description', label: 'Qué lo diferencia', hint: 'Lo que dice que lo hace distinto' },
  { key: 'competitor_customers_description', label: 'A quién le habla', hint: 'Qué tipo de cliente busca' },
  { key: 'competitor_communication_description', label: 'Cómo comunica', hint: 'De qué habla, con qué tono y en qué formatos' },
  { key: 'competitor_strengths_description', label: 'Qué le funciona', hint: 'Lo que le da resultado, con la evidencia' },
  { key: 'competitor_weaknesses_description', label: 'Dónde falla', hint: 'Quejas, huecos y lo que no cumple' },
];

const draft = ref({});
const editor = ref(null);
const saveError = ref('');
const isSaving = ref(false);
const isEditing = ref(false);
const editButton = ref(null);
const fieldErrors = ref({});
const announcement = ref('');

async function startEditing() {
  for (const field of knowledgeFields) {
    draft.value[field.key] = props.competitor[field.key] ?? '';
  }
  fieldErrors.value = {};
  saveError.value = '';
  isEditing.value = true;

  await nextTick();
  editor.value.querySelector('textarea')?.focus();
}

async function cancelEditing() {
  isEditing.value = false;

  await nextTick();
  editButton.value?.focus();
}

async function save() {
  const attributes = {};
  for (const field of knowledgeFields) {
    attributes[field.key] = draft.value[field.key].trim() || null;
  }

  isSaving.value = true;
  saveError.value = '';
  fieldErrors.value = {};

  try {
    const competitor = await CompetitorService.update(props.competitor.id, attributes);

    emit('saved', competitor);
    announcement.value = 'Cambios guardados.';
    await cancelEditing();
  } catch (error) {
    saveError.value = error.message;
    fieldErrors.value = error.errors ?? {};
  } finally {
    isSaving.value = false;
  }
}
</script>
