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

    <div class="p-5">
      <label
        :for="`${source.id}-url`"
        class="spec-label mb-2 block"
      >{{ source.label }}</label>
      <input
        :id="`${source.id}-url`"
        v-model="sourceUrl"
        :type="source.inputType"
        :placeholder="source.placeholder"
        autocomplete="off"
        autocapitalize="none"
        :spellcheck="false"
        class="min-h-11 w-full rounded-sm border border-border bg-surface px-3 text-sm placeholder:text-text-muted"
      >
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
    </div>

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
import { ref, nextTick } from 'vue';

const props = defineProps({ source: { type: Object, required: true } });

const sourceUrl = ref('');
const activeTab = ref('data');
const resultTabs = [
  { id: 'data', label: props.source.dataLabel },
  { id: 'insights', label: 'Conclusiones' },
];

async function selectTab(tabId) {
  activeTab.value = tabId;
  await nextTick();
  document.getElementById(`${props.source.id}-${tabId}-tab`)?.focus();
}

async function switchTab() {
  await selectTab(activeTab.value === 'data' ? 'insights' : 'data');
}
</script>
