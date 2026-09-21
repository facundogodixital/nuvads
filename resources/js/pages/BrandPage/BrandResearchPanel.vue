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
      @submit.prevent="saveSource"
    >
      <label
        :for="`${source.id}-url`"
        class="spec-label mb-2 block"
      >{{ source.label }}</label>
      <input
        :id="`${source.id}-url`"
        v-model="sourceUrl"
        :type="source.inputType"
        :disabled="!isAvailable || isSaving"
        :aria-invalid="Boolean(saveError)"
        :aria-describedby="`${source.id}-save-feedback`"
        :placeholder="source.placeholder"
        autocomplete="off"
        autocapitalize="none"
        :spellcheck="false"
        class="min-h-11 w-full rounded-sm border border-border bg-surface px-3 text-sm placeholder:text-text-muted"
        @input="clearFeedback"
      >
      <div class="mt-3 flex items-center justify-between gap-3">
        <span
          :id="`${source.id}-save-feedback`"
          role="status"
          class="text-xs"
          :class="saveError ? 'text-danger' : 'text-text-muted'"
        >
          {{ saveError || saveMessage || (isAvailable ? 'Puedes cambiarlo o quitarlo cuando quieras.' : 'Enlaces no disponibles todavía.') }}
        </span>
        <button
          type="submit"
          :disabled="!isAvailable || isSaving || !hasChanges"
          class="min-h-11 shrink-0 rounded-sm border border-border px-3 text-sm font-medium enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:cursor-not-allowed disabled:text-text-muted"
        >
          {{ isSaving ? 'Guardando…' : 'Guardar' }}
        </button>
      </div>
      <button
        type="button"
        disabled
        :aria-describedby="`${source.id}-availability`"
        class="mt-3 flex min-h-11 w-full items-center justify-center gap-2 rounded-sm border border-border bg-surface-selected px-3 text-sm font-medium text-text-muted disabled:cursor-not-allowed"
      >
        Analizar {{ source.title }}
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
        :id="`${source.id}-availability`"
        class="mt-2 text-xs text-text-muted"
      >
        Análisis pendiente de conexión.
      </p>
    </form>

    <div
      class="mx-5 flex border-b border-border"
      role="tablist"
      :aria-label="`Resultados de ${source.title}`"
    >
      <button
        v-for="tab in resultTabs"
        :id="`${source.id}-${tab.id}-tab`"
        :key="tab.id"
        type="button"
        role="tab"
        :aria-selected="activeTab === tab.id"
        :aria-controls="`${source.id}-results`"
        :tabindex="activeTab === tab.id ? 0 : -1"
        class="min-h-11 cursor-pointer border-b px-3 text-sm"
        :class="activeTab === tab.id ? 'border-text font-medium text-text' : 'border-transparent text-text-muted hover:text-text'"
        @click="activeTab = tab.id"
        @keydown.left.prevent="switchTab"
        @keydown.right.prevent="switchTab"
        @keydown.home.prevent="selectTab('data')"
        @keydown.end.prevent="selectTab('insights')"
      >
        {{ tab.label }}
      </button>
    </div>
    <div
      :id="`${source.id}-results`"
      role="tabpanel"
      :aria-labelledby="`${source.id}-${activeTab}-tab`"
      tabindex="0"
      class="flex min-h-44 flex-1 flex-col justify-center px-5 py-6"
    >
      <p class="text-sm font-medium">
        {{ activeTab === 'data' ? source.emptyTitle : 'Todavía no hay conclusiones' }}
      </p>
      <p class="mt-2 text-sm leading-6 text-text-muted">
        {{ activeTab === 'data' ? source.emptyDescription : source.insightDescription }}
      </p>
    </div>
    <footer class="flex justify-between gap-3 border-t border-border bg-surface px-5 py-3 text-xs text-text-muted">
      <span>Sin analizar</span>
      <span>{{ source.resultLabel }}</span>
    </footer>
  </section>
</template>


<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import BrandService from '@/services/BrandService';

const props = defineProps({
  source: { type: Object, required: true },
  savedValue: { type: String, default: '' },
  isAvailable: { type: Boolean, default: false },
});

const emit = defineEmits(['saved']);

const saveError = ref('');
const isSaving = ref(false);
const saveMessage = ref('');
const activeTab = ref('data');
const sourceUrl = ref(props.savedValue);
const resultTabs = [
  { id: 'data', label: props.source.dataLabel },
  { id: 'insights', label: 'Conclusiones' },
];

const hasChanges = computed(() => sourceUrl.value.trim() !== props.savedValue);

watch(() => props.savedValue, (value) => {
  sourceUrl.value = value;
});

function clearFeedback() {
  saveError.value = '';
  saveMessage.value = '';
}

async function saveSource() {
  const cannotSave = !props.isAvailable || isSaving.value || !hasChanges.value;
  if (cannotSave) {
    return;
  }

  clearFeedback();
  isSaving.value = true;

  try {
    const brand = await BrandService.update({ [props.source.field]: sourceUrl.value.trim() || null });
    const savedValue = brand[props.source.field] ?? '';

    sourceUrl.value = savedValue;
    emit('saved', savedValue);
    saveMessage.value = savedValue ? 'Guardado.' : 'Enlace eliminado.';
  } catch (error) {
    saveError.value = error.errors?.[props.source.field]?.[0] ?? error.message;
  } finally {
    isSaving.value = false;
  }
}

async function selectTab(tabId) {
  activeTab.value = tabId;
  await nextTick();
  document.getElementById(`${props.source.id}-${tabId}-tab`)?.focus();
}

async function switchTab() {
  await selectTab(activeTab.value === 'data' ? 'insights' : 'data');
}
</script>
