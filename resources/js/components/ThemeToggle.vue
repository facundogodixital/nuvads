<template>
  <button
    type="button"
    class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center rounded-sm border border-border
      text-text-muted transition hover:border-accent hover:text-accent"
    :aria-label="themeToggleLabel"
    @click="toggleTheme"
  >
    <svg
      v-if="themeIsDark"
      class="h-4 w-4"
      viewBox="0 0 16 16"
      fill="none"
      aria-hidden="true"
    >
      <circle
        cx="8"
        cy="8"
        r="3"
        stroke="currentColor"
        stroke-width="1.5"
      />
      <path
        d="M8 1.5v2M8 12.5v2M1.5 8h2M12.5 8h2M3.4 3.4l1.4 1.4M11.2 11.2l1.4 1.4M12.6 3.4l-1.4
          1.4M4.8 11.2l-1.4 1.4"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linecap="round"
      />
    </svg>
    <svg
      v-else
      class="h-4 w-4"
      viewBox="0 0 16 16"
      fill="none"
      aria-hidden="true"
    >
      <path
        d="M13 9.5A5.5 5.5 0 1 1 6.5 3a4.3 4.3 0 0 0 6.5 6.5Z"
        stroke="currentColor"
        stroke-width="1.5"
        stroke-linejoin="round"
      />
    </svg>
  </button>
</template>


<script setup>
import { ref, computed } from 'vue';
import { applyTheme, storeTheme } from '@/helpers/preferencesStorage';

// El router ya dejó aplicado el tema vigente antes de montar la pantalla.
const theme = ref(document.documentElement.dataset.theme === 'dark' ? 'dark' : 'light');

const themeIsDark = computed(() => theme.value === 'dark');
const themeToggleLabel = computed(() => (themeIsDark.value ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'));

function toggleTheme() {
  theme.value = themeIsDark.value ? 'light' : 'dark';
  applyTheme(theme.value);
  storeTheme(theme.value);
}
</script>
