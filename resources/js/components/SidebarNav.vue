<template>
  <nav aria-label="Secciones">
    <ul class="flex flex-col gap-1">
      <li
        v-for="item in navItems"
        :key="item.label"
      >
        <RouterLink
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
    to: '/create',
    label: 'Crear',
    paths: [
      'M4 3.5h12a.5.5 0 0 1 .5.5v12a.5.5 0 0 1-.5.5H4a.5.5 0 0 1-.5-.5V4a.5.5 0 0 1 .5-.5Z',
      'M10 6.5v7M6.5 10h7',
    ],
  },
  {
    to: '/library',
    label: 'Biblioteca',
    paths: ['M3.5 4v12M7.5 4v12M11.5 4l4 12M3.5 7h4M3.5 13h4'],
  },
  {
    to: '/brand',
    label: 'Mi marca',
    paths: ['M4 17V3.5h11.5l-2 4 2 4H4'],
  },
  {
    to: '/inspiration',
    label: 'Inspiración',
    paths: [
      'M7.5 13.5v-1a5 5 0 1 1 5 0v1h-5Z',
      'M7.5 16h5M9 18h2',
    ],
  },
];

function routeIsActive(path) {
  return route.path === path;
}
</script>
