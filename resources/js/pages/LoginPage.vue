<template>
  <main class="relative flex min-h-dvh flex-col bg-surface p-0 text-text">
    <!-- El degradé del logo aparece solo acá y en el lockup. -->
    <div
      class="brand-rule"
      aria-hidden="true"
    />

    <header class="border-b border-border px-6 py-4">
      <div class="mx-auto flex w-full max-w-[864px] items-center justify-between">
        <p class="spec-label">
          Nuvads
        </p>
        <button
          type="button"
          class="flex h-8 w-8 items-center justify-center rounded-sm border border-border
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
      </div>
    </header>

    <section class="mx-auto flex w-full max-w-[432px] flex-1 flex-col justify-center px-6 py-12 sm:px-0">
      <img
        :src="lockupUrl"
        alt="Nuvads"
        class="mx-auto w-full max-w-80 sm:max-w-full"
      >
      <p class="mt-6 text-center text-text">
        Contenido de calidad para tu negocio, hecho por un sistema que lo conoce a fondo.
      </p>

      <p
        v-if="error || loginError"
        role="alert"
        class="mt-8 rounded-sm border border-danger px-4 py-3 text-sm text-danger"
      >
        {{ error || loginError }}
      </p>

      <button
        type="button"
        :disabled="isStartingLogin"
        class="mt-10 flex items-center justify-center gap-3 rounded-sm bg-accent px-6 py-3 font-medium
          text-text-on-accent transition hover:bg-accent-hover active:translate-y-px"
        @click="loginWithGoogle"
      >
        <svg
          class="h-5 w-5 shrink-0"
          viewBox="0 0 24 24"
          aria-hidden="true"
        >
          <path
            fill="#fff"
            d="M21.8 12.23c0-.68-.06-1.36-.19-2.03H12v3.85h5.51a4.7 4.7 0 0 1-2.04 3.09v2.56h3.3
              c1.93-1.78 3.03-4.4 3.03-7.47Z"
            opacity=".95"
          />
          <path
            fill="#fff"
            d="M12 22c2.76 0 5.07-.91 6.76-2.47l-3.29-2.56c-.92.62-2.09.98-3.47.98-2.66
              0-4.92-1.8-5.73-4.22H2.87v2.64A10.2 10.2 0 0 0 12 22Z"
            opacity=".8"
          />
          <path
            fill="#fff"
            d="M6.27 13.73a6.1 6.1 0 0 1 0-3.9V7.19H2.87a10.2 10.2 0 0 0 0 9.18l3.4-2.64Z"
            opacity=".65"
          />
          <path
            fill="#fff"
            d="M12 5.61c1.5 0 2.85.52 3.9 1.53l2.93-2.92A10.2 10.2 0 0 0 2.87 7.19l3.4 2.64C7.08 7.41
              9.34 5.61 12 5.61Z"
            opacity=".5"
          />
        </svg>
        Continuar con Google
      </button>
      <p class="mt-3 text-center text-sm text-text-muted">
        Inicia sesión o crea tu cuenta en un paso.
      </p>

      <div class="mt-14 border-t border-border pt-6">
        <button
          type="button"
          class="mx-auto flex items-center gap-2 text-sm text-text underline underline-offset-4
            transition hover:text-accent"
          :aria-expanded="userFormIsOpen"
          @click="toggleUserForm"
        >
          Ingresar con usuario
          <svg
            class="h-3.5 w-3.5 transition-transform duration-300"
            :class="{ 'rotate-180': userFormIsOpen }"
            viewBox="0 0 16 16"
            fill="none"
            aria-hidden="true"
          >
            <path
              d="M3.5 6 8 10.5 12.5 6"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            />
          </svg>
        </button>

        <!-- La nota al pie se despliega como una página que se abre. -->
        <Transition name="unfold">
          <div
            v-if="userFormIsOpen"
            class="unfold-frame"
          >
            <div class="min-h-0 overflow-hidden">
              <form
                class="flex flex-col gap-5 pt-8 pb-2"
                @submit.prevent
              >
                <div class="flex flex-col gap-1.5">
                  <label
                    for="login-identifier"
                    class="spec-label"
                  >Identificador</label>
                  <input
                    id="login-identifier"
                    ref="identifierInput"
                    v-model="identifier"
                    type="text"
                    autocomplete="organization"
                    class="w-full rounded-sm border border-border bg-surface-raised px-3.5 py-2.5"
                  >
                  <p class="text-xs text-text-muted">
                    Es el identificador de la cuenta que te dio el acceso.
                  </p>
                </div>

                <div class="flex flex-col gap-1.5">
                  <label
                    for="login-username"
                    class="spec-label"
                  >Usuario</label>
                  <input
                    id="login-username"
                    v-model="username"
                    type="text"
                    autocomplete="username"
                    class="w-full rounded-sm border border-border bg-surface-raised px-3.5 py-2.5"
                  >
                </div>

                <div class="flex flex-col gap-1.5">
                  <label
                    for="login-password"
                    class="spec-label"
                  >Contraseña</label>
                  <input
                    id="login-password"
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    class="w-full rounded-sm border border-border bg-surface-raised px-3.5 py-2.5"
                  >
                </div>

                <div class="mt-1 flex items-center justify-between gap-4">
                  <button
                    type="submit"
                    disabled
                    class="cursor-not-allowed rounded-sm bg-surface-selected px-6 py-2.5 font-medium
                      text-text-muted"
                  >
                    Entrar
                  </button>
                  <p class="text-xs text-text-muted">
                    El acceso con usuario estará disponible próximamente.
                  </p>
                </div>
              </form>
            </div>
          </div>
        </Transition>
      </div>
    </section>

    <footer class="border-t border-border px-6 py-5">
      <div class="mx-auto flex w-full max-w-[864px] justify-end">
        <p class="spec-label">
          nuvads.ai
        </p>
      </div>
    </footer>
  </main>
</template>


<script setup>
import { ref, computed, nextTick, onMounted } from 'vue';
import lockupUrl from '@/assets/brand/lockup.svg';
import { createGoogleLoginUrl } from '@/helpers/authStorage';

defineProps({
  error: { type: String, default: '' },
});

const username = ref('');
const password = ref('');
const theme = ref('dark');
const loginError = ref('');
const identifier = ref('');
const userFormIsOpen = ref(false);
const identifierInput = ref(null);
const isStartingLogin = ref(false);

const themeIsDark = computed(() => theme.value === 'dark');
const themeToggleLabel = computed(() => (themeIsDark.value ? 'Cambiar a tema claro' : 'Cambiar a tema oscuro'));

// El login arranca en dark por decisión de producto; el resto de la app hereda del sistema.
onMounted(() => {
  document.documentElement.dataset.theme = theme.value;
});

async function loginWithGoogle() {
  loginError.value = '';
  isStartingLogin.value = true;
  try {
    window.location.assign(await createGoogleLoginUrl());
  } catch {
    loginError.value = 'No se pudo iniciar el acceso. Comprueba que el navegador permita almacenar datos.';
    isStartingLogin.value = false;
  }
}

function toggleTheme() {
  theme.value = themeIsDark.value ? 'light' : 'dark';
  document.documentElement.dataset.theme = theme.value;
}

async function toggleUserForm() {
  userFormIsOpen.value = !userFormIsOpen.value;

  const formWasOpened = userFormIsOpen.value;
  if (formWasOpened) {
    await nextTick();
    identifierInput.value?.focus();
  }
}
</script>


<style scoped>
.brand-rule {
  height: 3px;
  background: linear-gradient(90deg, var(--brand-cyan), var(--brand-mid), var(--brand-blue));
}

/* Etiquetas specimen: la grotesca del manual en versalitas espaciadas. */
.spec-label {
  font-size: 0.6875rem;
  font-weight: 500;
  letter-spacing: 0.14em;
  text-transform: uppercase;
  color: var(--text-muted);
}

/* El despliegue anima las filas de la grilla: la nota al pie "se abre". */
.unfold-frame {
  display: grid;
  grid-template-rows: 1fr;
}

.unfold-enter-active,
.unfold-leave-active {
  transition:
    opacity 0.3s ease-out,
    grid-template-rows 0.45s cubic-bezier(0.16, 1, 0.3, 1);
}

.unfold-enter-from,
.unfold-leave-to {
  opacity: 0;
  grid-template-rows: 0fr;
}
</style>
