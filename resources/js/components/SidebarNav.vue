<template>
  <nav aria-label="Secciones">
    <ul class="flex flex-col gap-1">
      <li
        v-for="item in navItems"
        :key="item.label"
      >
        <RouterLink
          v-if="item.to"
          :to="item.to"
          :title="isCollapsed ? item.label : null"
          class="flex items-center gap-3 rounded-sm py-2.5 text-sm transition"
          :class="[
            isCollapsed ? 'justify-center px-0' : 'px-3',
            routeIsActive(item.to)
              ? 'bg-accent-soft font-medium text-accent'
              : 'text-text-muted hover:bg-surface-selected hover:text-text',
          ]"
          @click="emit('navigate')"
        >
          <svg
            class="h-5 w-5 shrink-0"
            viewBox="0 0 20 20"
            fill="none"
            aria-hidden="true"
          >
            <path
              v-for="path in item.paths"
              :key="path"
              :d="path"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <span v-if="!isCollapsed">{{ item.label }}</span>
        </RouterLink>

        <!-- Entradas placeholder: marcan el lugar de las secciones futuras. -->
        <span
          v-else
          :title="isCollapsed ? `${item.label} · Próximamente` : 'Próximamente'"
          class="flex cursor-not-allowed items-center gap-3 rounded-sm py-2.5 text-sm
            text-text-muted"
          :class="isCollapsed ? 'justify-center px-0' : 'px-3'"
        >
          <svg
            class="h-5 w-5 shrink-0"
            viewBox="0 0 20 20"
            fill="none"
            aria-hidden="true"
          >
            <path
              v-for="path in item.paths"
              :key="path"
              :d="path"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          <span v-if="!isCollapsed">{{ item.label }}</span>
        </span>
      </li>
    </ul>
  </nav>
</template>


<script setup>
import { useRoute, RouterLink } from 'vue-router';

defineProps({
  isCollapsed: { type: Boolean, default: false },
});

const emit = defineEmits(['navigate']);

const route = useRoute();

// Íconos del manual: trazo 1.5, mismos que el resto del sistema.
const navItems = [
  {
    to: '/',
    label: 'Inicio',
    paths: ['M3.5 8.5 10 3l6.5 5.5V16a1 1 0 0 1-1 1h-3.5v-4.5h-4V17H4.5a1 1 0 0 1-1-1Z'],
  },
  {
    label: 'Lorem ipsum',
    paths: [
      'M4 5.5A1.5 1.5 0 0 1 5.5 4h9A1.5 1.5 0 0 1 16 5.5v9a1.5 1.5 0 0 1-1.5 1.5h-9A1.5 1.5 0 0 1 4 14.5Z',
      'M4 13l3.5-3 2.5 2 3-2.5 3 2.5',
      'M8.25 7.75h.01',
    ],
  },
  {
    label: 'Dolor sit',
    paths: [
      'M6 3h5.5L15 6.5V16a1 1 0 0 1-1 1H6a1 1 0 0 1-1-1V4a1 1 0 0 1 1-1Z',
      'M11 3v4h4',
      'M7.5 10.5h5M7.5 13.5h5',
    ],
  },
  {
    label: 'Amet magna',
    paths: ['M4.5 16V9.5M10 16V4M15.5 16v-4.5'],
  },
];

function routeIsActive(path) {
  return route.path === path;
}
</script>
