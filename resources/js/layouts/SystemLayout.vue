<template>
  <div class="flex h-dvh flex-col bg-surface text-text">
    <!-- El degradé del logo aparece solo acá y en el lockup. -->
    <div
      class="brand-rule shrink-0"
      aria-hidden="true"
    />

    <header class="flex h-14 shrink-0 items-center justify-between gap-4 border-b border-border px-4 sm:px-6">
      <div class="flex min-w-0 items-center gap-2">
        <button
          ref="drawerOpenButton"
          type="button"
          class="flex h-8 w-8 shrink-0 cursor-pointer items-center justify-center text-text-muted
            transition hover:text-accent md:hidden"
          aria-label="Abrir menú"
          @click="openDrawer"
        >
          <svg
            class="h-5 w-5"
            viewBox="0 0 20 20"
            fill="none"
            aria-hidden="true"
          >
            <path
              d="M3.5 5.5h13M3.5 10h13M3.5 14.5h13"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
            />
          </svg>
        </button>

        <RouterLink
          to="/"
          class="flex min-w-0 items-center"
        >
          <img
            :src="lockupUrl"
            alt="Nuvads"
            class="hidden h-12 w-auto md:block"
          >
          <img
            :src="logoUrl"
            alt="Nuvads"
            class="h-8 w-auto md:hidden"
          >
        </RouterLink>
      </div>

      <div class="flex items-center gap-3">
        <p class="spec-label shrink-0 rounded-full border border-border px-3.5 py-1.5">
          600 créditos
        </p>
        <ThemeToggle />
        <UserMenu />
      </div>
    </header>

    <div class="flex min-h-0 flex-1">
      <aside
        class="hidden shrink-0 flex-col border-r border-border transition-[width] duration-250 md:flex"
        :class="sidebarIsCollapsed ? 'w-[72px]' : 'w-[216px]'"
      >
        <BrandMenu :is-collapsed="sidebarIsCollapsed" />
        <SidebarNav
          :is-collapsed="sidebarIsCollapsed"
          class="flex-1 overflow-y-auto p-3"
        />

        <div class="border-t border-border p-3">
          <button
            type="button"
            class="flex w-full cursor-pointer items-center gap-3 rounded-sm py-2.5 text-sm text-text-muted
              transition hover:bg-surface-selected hover:text-text"
            :class="sidebarIsCollapsed ? 'justify-center px-0' : 'px-3'"
            :title="sidebarIsCollapsed ? 'Expandir menú' : null"
            :aria-label="sidebarIsCollapsed ? 'Expandir menú' : 'Contraer menú'"
            @click="toggleSidebar"
          >
            <svg
              class="h-5 w-5 shrink-0 transition-transform duration-300"
              :class="{ 'rotate-180': sidebarIsCollapsed }"
              viewBox="0 0 20 20"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="M11 5l-5 5 5 5M15.5 5l-5 5 5 5"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
                stroke-linejoin="round"
              />
            </svg>
            <span v-if="!sidebarIsCollapsed">Contraer</span>
          </button>
        </div>
      </aside>

      <!-- relative: los elementos absolutos de las páginas (sr-only, popovers) quedan dentro del área con scroll. -->
      <main class="relative min-w-0 flex-1 overflow-y-auto p-6 sm:p-8">
        <slot />
      </main>
    </div>

    <!-- Drawer mobile: la misma navegación, sobre un velo de tinta. -->
    <Transition name="scrim">
      <div
        v-if="drawerIsOpen"
        class="drawer-scrim fixed inset-0 z-30 md:hidden"
        @click="closeDrawer"
      />
    </Transition>

    <Transition name="drawer">
      <div
        v-if="drawerIsOpen"
        class="fixed inset-y-0 left-0 z-40 flex w-[216px] flex-col border-r border-border bg-surface
          md:hidden"
      >
        <div class="flex h-14 shrink-0 items-center justify-between border-b border-border pr-2 pl-4">
          <img
            :src="lockupUrl"
            alt="Nuvads"
            class="h-6 w-auto"
          >
          <button
            ref="drawerCloseButton"
            type="button"
            class="flex h-8 w-8 cursor-pointer items-center justify-center text-text-muted transition
              hover:text-accent"
            aria-label="Cerrar menú"
            @click="closeDrawer"
          >
            <svg
              class="h-4 w-4"
              viewBox="0 0 16 16"
              fill="none"
              aria-hidden="true"
            >
              <path
                d="m4 4 8 8M12 4l-8 8"
                stroke="currentColor"
                stroke-width="1.5"
                stroke-linecap="round"
              />
            </svg>
          </button>
        </div>
        <BrandMenu />
        <SidebarNav
          class="flex-1 overflow-y-auto p-3"
          @navigate="closeDrawer"
        />
      </div>
    </Transition>
  </div>
</template>


<script setup>
import { ref, nextTick, onMounted, onBeforeUnmount } from 'vue';
import { RouterLink } from 'vue-router';
import logoUrl from '@/assets/brand/logo.svg';
import UserMenu from '@/components/UserMenu.vue';
import lockupUrl from '@/assets/brand/lockup.svg';
import BrandMenu from '@/components/BrandMenu.vue';
import ThemeToggle from '@/components/ThemeToggle.vue';
import SidebarNav from '@/components/SidebarNav.vue';
import { getStoredSidebarIsCollapsed, storeSidebarIsCollapsed } from '@/helpers/preferencesStorage';

const drawerIsOpen = ref(false);
const drawerOpenButton = ref(null);
const drawerCloseButton = ref(null);
const sidebarIsCollapsed = ref(getStoredSidebarIsCollapsed());

onMounted(() => {
  document.addEventListener('keydown', closeDrawerOnEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', closeDrawerOnEscape);
  document.body.style.overflow = '';
});

function toggleSidebar() {
  sidebarIsCollapsed.value = !sidebarIsCollapsed.value;
  storeSidebarIsCollapsed(sidebarIsCollapsed.value);
}

// Al abrir, el foco entra al drawer y el fondo deja de desplazarse; al cerrar vuelven.
async function openDrawer() {
  drawerIsOpen.value = true;
  document.body.style.overflow = 'hidden';

  await nextTick();
  drawerCloseButton.value?.focus();
}

function closeDrawer() {
  drawerIsOpen.value = false;
  document.body.style.overflow = '';
  drawerOpenButton.value?.focus();
}

function closeDrawerOnEscape(event) {
  const drawerCanClose = event.key === 'Escape' && drawerIsOpen.value;
  if (drawerCanClose) {
    closeDrawer();
  }
}
</script>


<style scoped>
.drawer-scrim {
  background-color: var(--scrim);
}

/* El drawer se abre como una página: el mismo ease exponencial del manual. */
.drawer-enter-active,
.drawer-leave-active {
  transition: transform 0.35s cubic-bezier(0.16, 1, 0.3, 1);
}

.drawer-enter-from,
.drawer-leave-to {
  transform: translateX(-100%);
}

.scrim-enter-active,
.scrim-leave-active {
  transition: opacity 0.25s ease-out;
}

.scrim-enter-from,
.scrim-leave-to {
  opacity: 0;
}
</style>
