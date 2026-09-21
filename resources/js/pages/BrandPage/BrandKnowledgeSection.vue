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
      @submit.prevent="applyChanges"
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
          class="block w-full rounded-sm border border-border bg-surface-raised px-3.5 py-2.5 text-sm leading-6 text-text placeholder:text-text-muted"
          :autocomplete="field.key === 'name' ? 'organization' : 'off'"
        >
        <textarea
          v-else
          :id="`${section.id}-${field.key}`"
          v-model="draft[field.key]"
          rows="3"
          :placeholder="field.hint"
          class="block w-full rounded-sm border border-border bg-surface-raised px-3.5 py-2.5 text-sm leading-6 text-text placeholder:text-text-muted resize-y"
        />
      </div>
      <div class="flex flex-wrap items-center gap-3 pt-1">
        <button
          type="submit"
          class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-sm px-3 py-2 text-sm hover:bg-surface-selected border border-border bg-surface-selected"
        >
          Aplicar cambios
        </button>
        <button
          type="button"
          class="inline-flex min-h-11 shrink-0 cursor-pointer items-center gap-2 rounded-sm border border-border px-3 py-2 text-sm hover:bg-surface-selected"
          @click="cancelEditing"
        >
          Cancelar
        </button>
        <span class="text-xs text-text-muted">Solo en esta vista previa.</span>
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
          v-if="values[field.key]"
          class="whitespace-pre-wrap break-words text-sm leading-6"
        >
          {{ values[field.key] }}
          <span
            v-if="editedFields.includes(field.key)"
            class="mt-1 block text-xs text-text-muted"
          >Aportado por ti · vista previa</span>
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

const props = defineProps({
  section: { type: Object, required: true },
  values: { type: Object, required: true },
});

const emit = defineEmits(['update']);

const draft = ref({});
const editor = ref(null);
const isEditing = ref(false);
const editButton = ref(null);
const announcement = ref('');
const editedFields = ref([]);

async function startEditing() {
  draft.value = { ...props.values };
  isEditing.value = true;

  await nextTick();
  editor.value.querySelector('input, textarea')?.focus();
}

async function cancelEditing() {
  isEditing.value = false;

  await nextTick();
  editButton.value?.focus();
}

async function applyChanges() {
  const updatedValues = {};
  for (const field of props.section.fields) {
    updatedValues[field.key] = (draft.value[field.key] ?? '').trim();

    const fieldHasChanged = updatedValues[field.key] !== (props.values[field.key] ?? '');
    const fieldHasNotBeenEdited = !editedFields.value.includes(field.key);

    if (fieldHasChanged && fieldHasNotBeenEdited) {
      editedFields.value.push(field.key);
    }
  }

  emit('update', updatedValues);
  announcement.value = 'Cambios aplicados en la vista previa. No se guardan al salir.';

  await cancelEditing();
}
</script>
