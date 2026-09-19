<template>
  <div class="flex min-h-dvh flex-col bg-surface text-text">
    <header class="flex justify-end border-b border-border px-6 py-4">
      <button
        type="button"
        :disabled="isLoggingOut"
        class="cursor-pointer text-sm underline underline-offset-4 hover:text-accent disabled:cursor-wait"
        @click="logout"
      >
        Cerrar sesión
      </button>
    </header>

    <main class="flex-1 p-6">
      <p
        v-if="logoutError"
        role="alert"
        class="mb-4 text-danger"
      >
        {{ logoutError }}
      </p>
      <slot />
    </main>
  </div>
</template>


<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import SessionService from '@/services/SessionService';
import { clearAuthToken } from '@/helpers/authStorage';

const router = useRouter();
const logoutError = ref('');
const isLoggingOut = ref(false);

async function logout() {
  logoutError.value = '';
  isLoggingOut.value = true;

  try {
    await SessionService.delete();
    clearAuthToken();
    await router.replace('/login');
  } catch (error) {
    logoutError.value = error.message;
  } finally {
    isLoggingOut.value = false;
  }
}
</script>
