<template>
  <div class="relative">
    <button
      type="button"
      class="flex h-8 w-8 cursor-pointer items-center justify-center rounded-full bg-accent text-sm
        font-medium text-text-on-accent transition hover:bg-accent-hover"
      aria-haspopup="menu"
      :aria-expanded="menuIsOpen"
      aria-label="Menú de usuario"
      @click="toggleMenu"
    >
      {{ userInitial }}
    </button>

    <!-- Velo invisible: un click fuera del menú lo cierra. -->
    <div
      v-if="menuIsOpen"
      class="fixed inset-0 z-10"
      @click="closeMenu"
    />

    <Transition name="menu">
      <div
        v-if="menuIsOpen"
        role="menu"
        class="absolute top-10 right-0 z-20 w-52 rounded-sm border border-border bg-surface-raised p-1.5"
      >
        <button
          type="button"
          role="menuitem"
          :disabled="isLoggingOut"
          class="flex w-full cursor-pointer items-center gap-2.5 rounded-sm px-2.5 py-2 text-left
            text-sm transition hover:bg-surface-selected disabled:cursor-wait"
          @click="logout"
        >
          <svg
            class="h-4 w-4 text-text-muted"
            viewBox="0 0 16 16"
            fill="none"
            aria-hidden="true"
          >
            <path
              d="M6.5 2.5H4A1.5 1.5 0 0 0 2.5 4v8A1.5 1.5 0 0 0 4 13.5h2.5M10.5 5 13.5 8l-3 3M13.5 8H6"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
          Cerrar sesión
        </button>
        <p
          v-if="logoutError"
          role="alert"
          class="px-2.5 py-1.5 text-xs text-danger"
        >
          {{ logoutError }}
        </p>
      </div>
    </Transition>
  </div>
</template>


<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { useRouter } from 'vue-router';
import SessionService from '@/services/SessionService';
import { clearAuthToken } from '@/helpers/authStorage';
import { useSessionStore } from '@/stores/sessionStore';

const sessionStore = useSessionStore();

const router = useRouter();
const logoutError = ref('');
const menuIsOpen = ref(false);
const isLoggingOut = ref(false);

const userInitial = computed(() => {
  const userName = sessionStore.session?.user.name ?? '';
  return userName.trim().charAt(0).toUpperCase();
});

onMounted(() => {
  document.addEventListener('keydown', closeMenuOnEscape);
});

onBeforeUnmount(() => {
  document.removeEventListener('keydown', closeMenuOnEscape);
});

function toggleMenu() {
  menuIsOpen.value = !menuIsOpen.value;
}

function closeMenu() {
  menuIsOpen.value = false;
}

function closeMenuOnEscape(event) {
  const escapeWasPressed = event.key === 'Escape';
  if (escapeWasPressed) {
    closeMenu();
  }
}

async function logout() {
  logoutError.value = '';
  isLoggingOut.value = true;

  try {
    await SessionService.delete();
    clearAuthToken();
    sessionStore.clear();
    await router.replace('/login');
  } catch (error) {
    logoutError.value = error.message;
  } finally {
    isLoggingOut.value = false;
  }
}
</script>


<style scoped>
/* El menú aparece como una nota que se asienta: leve caída con desvanecimiento. */
.menu-enter-active,
.menu-leave-active {
  transition:
    opacity 0.18s ease-out,
    transform 0.18s ease-out;
}

.menu-enter-from,
.menu-leave-to {
  opacity: 0;
  transform: translateY(-4px);
}
</style>
