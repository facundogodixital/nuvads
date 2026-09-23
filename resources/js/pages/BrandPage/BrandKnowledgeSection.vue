<template>
  <section
    :id="section.id"
    class="min-w-0 scroll-mt-6 rounded-sm border border-border bg-surface-raised p-5 sm:p-6"
    :aria-labelledby="`${section.id}-heading`"
  >
    <header class="mb-5 flex items-start justify-between gap-4 border-b border-border pb-5">
      <div>
        <h2
          :id="`${section.id}-heading`"
          class="text-lg font-medium"
        >
          {{ section.title }}
        </h2>
        <p class="mt-1 max-w-xl text-sm leading-6 text-text-muted">
          {{ section.description }}
        </p>
      </div>
      <button
        v-if="!isEditing"
        ref="editButton"
        type="button"
        class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-sm border border-border px-3 py-2 text-sm hover:bg-surface-selected"
        :aria-label="`Editar ${section.title}`"
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
        v-for="field in section.fields"
        :key="field.key"
      >
        <label
          :for="`${section.id}-${field.key}`"
          class="spec-label mb-2 block"
        >{{ field.label }}</label>
        <input
          v-if="field.type"
          :id="`${section.id}-${field.key}`"
          v-model="draft[field.key]"
          :type="field.type"
          :placeholder="field.hint"
          :aria-invalid="Boolean(fieldErrors[field.key])"
          class="block w-full rounded-sm border border-border bg-surface-raised px-3.5 py-2.5 text-sm leading-6 text-text placeholder:text-text-muted"
          :autocomplete="field.key === 'name' ? 'organization' : 'off'"
        >
        <textarea
          v-else
          :id="`${section.id}-${field.key}`"
          v-model="draft[field.key]"
          rows="4"
          :placeholder="field.hint"
          :aria-invalid="Boolean(fieldErrors[field.key])"
          class="block w-full rounded-sm border border-border bg-surface-raised px-3.5 py-2.5 text-sm leading-6 text-text placeholder:text-text-muted resize-y"
        />
        <p
          v-if="fieldErrors[field.key]"
          class="mt-1 text-xs text-danger"
        >
          {{ fieldErrors[field.key] }}
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
        v-for="field in section.fields"
        :key="field.key"
        class="min-w-0 rounded-sm bg-surface p-3"
      >
        <dt class="spec-label mb-2">
          {{ field.label }}
        </dt>
        <dd
          v-if="readValue(field)"
          class="whitespace-pre-wrap break-words text-sm leading-6"
        >
          {{ readValue(field) }}
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
import BrandService from '@/services/BrandService';

const props = defineProps({
  section: { type: Object, required: true },
  brand: { type: Object, required: true },
});

const emit = defineEmits(['saved']);

const draft = ref({});
const editor = ref(null);
const isSaving = ref(false);
const saveError = ref('');
const isEditing = ref(false);
const editButton = ref(null);
const fieldErrors = ref({});
const announcement = ref('');

// Los campos con group, como las tipografías, viven dentro de un JSON de la marca.
function readValue(field) {
  const source = field.group ? props.brand[field.group] ?? {} : props.brand;
  return source[field.key] ?? '';
}

async function startEditing() {
  for (const field of props.section.fields) {
    draft.value[field.key] = readValue(field);
  }
  fieldErrors.value = {};
  saveError.value = '';
  isEditing.value = true;

  await nextTick();
  editor.value.querySelector('input, textarea')?.focus();
}

async function cancelEditing() {
  isEditing.value = false;

  await nextTick();
  editButton.value?.focus();
}

async function save() {
  const attributes = {};
  for (const field of props.section.fields) {
    const value = draft.value[field.key].trim() || null;
    if (field.group) {
      attributes[field.group] = { ...attributes[field.group], [field.key]: value };
    } else {
      attributes[field.key] = value;
    }
  }

  isSaving.value = true;
  saveError.value = '';
  fieldErrors.value = {};

  try {
    const brand = await BrandService.update(attributes);

    emit('saved', brand);
    announcement.value = 'Cambios guardados.';
    await cancelEditing();
  } catch (error) {
    saveError.value = error.message;
    fieldErrors.value = readFieldErrors(error.errors ?? {});
  } finally {
    isSaving.value = false;
  }
}

// La API informa los errores por columna, como brand_fonts.heading; acá se guardan por clave del campo.
function readFieldErrors(errors) {
  const fieldErrors = {};
  for (const field of props.section.fields) {
    const errorKey = field.group ? `${field.group}.${field.key}` : field.key;
    if (errors[errorKey]) {
      fieldErrors[field.key] = errors[errorKey][0];
    }
  }
  return fieldErrors;
}
</script>
