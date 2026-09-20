<template>
  <div
    ref="menuContainer"
    class="relative border-b border-border p-3"
    @keydown.esc.stop="closeMenuAndFocusTrigger"
  >
    <button
      ref="menuTrigger"
      type="button"
      class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-sm text-left text-sm
        transition hover:bg-surface-selected"
      :class="isCollapsed ? 'justify-center' : 'px-3'"
      :title="brandName"
      :aria-label="`Marcas: ${brandName}`"
      :aria-expanded="menuIsOpen"
      :aria-controls="menuId"
      @click="menuIsOpen = !menuIsOpen"
    >
      <span
        v-if="isCollapsed"
        class="font-medium"
      >{{ brandInitial }}</span>
      <template v-else>
        <span class="min-w-0 flex-1 truncate font-medium">{{ brandName }}</span>
        <svg
          class="h-4 w-4 shrink-0 text-text-muted transition-transform"
          :class="{ 'rotate-180': menuIsOpen }"
          viewBox="0 0 16 16"
          fill="none"
          aria-hidden="true"
        >
          <path
            d="m4 6 4 4 4-4"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
          />
        </svg>
      </template>
    </button>

    <div
      v-if="menuIsOpen"
      :id="menuId"
      class="absolute top-full left-3 z-20 w-48 rounded-sm border border-border bg-surface-raised p-1.5"
    >
      <button
        type="button"
        aria-current="true"
        class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-sm px-2.5 py-2 text-left
          text-sm transition hover:bg-surface-selected"
        @click="closeMenuAndFocusTrigger"
      >
        <span class="min-w-0 flex-1 break-words">{{ brandName }}</span>
        <svg
          class="h-4 w-4 shrink-0 text-accent"
          viewBox="0 0 16 16"
          fill="none"
          aria-hidden="true"
        >
          <path
            d="m3 8 3 3 7-7"
            stroke="currentColor"
            stroke-width="1.5"
            stroke-linecap="round"
            stroke-linejoin="round"
          />
        </svg>
        <span class="sr-only">Marca actual</span>
      </button>
    </div>
  </div>
</template>


<script setup>
import { useSessionStore } from '@/stores/sessionStore';
import { ref, useId, computed, onMounted, onBeforeUnmount } from 'vue';

defineProps({
  isCollapsed: { type: Boolean, default: false },
});

const sessionStore = useSessionStore();

const menuId = useId();
const menuIsOpen = ref(false);
const menuTrigger = ref(null);
const menuContainer = ref(null);

const brandName = computed(() => sessionStore.session?.brand?.name ?? '');
const brandInitial = computed(() => brandName.value.trim().charAt(0).toUpperCase());

onMounted(() => {
  document.addEventListener('pointerdown', closeMenuOnOutsideClick);
});

onBeforeUnmount(() => {
  document.removeEventListener('pointerdown', closeMenuOnOutsideClick);
});

function closeMenuAndFocusTrigger() {
  menuIsOpen.value = false;
  menuTrigger.value?.focus();
}

function closeMenuOnOutsideClick(event) {
  const clickIsOutside = !menuContainer.value?.contains(event.target);
  if (clickIsOutside) {
    menuIsOpen.value = false;
  }
}
</script>
