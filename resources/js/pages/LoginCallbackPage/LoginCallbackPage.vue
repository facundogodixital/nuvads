<template>
  <main class="min-h-dvh bg-surface p-6 text-text">
    <p role="status">
      Completando el acceso…
    </p>
  </main>
</template>


<script setup>
import { onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import SessionService from '@/services/SessionService';
import { saveAuthToken, takeDestination, takeLoginVerifier } from '@/helpers/authStorage';

const route = useRoute();
const router = useRouter();

onMounted(async () => {
  const code = new URLSearchParams(route.hash.slice(1)).get('code');
  // Se quita el código del historial antes de hacer el canje.
  await router.replace('/login/callback');

  try {
    const verifier = takeLoginVerifier();
    const isCodeMissing = !code;
    const isVerifierMissing = !verifier;
    if (isCodeMissing || isVerifierMissing) {
      await router.replace({ path: '/login', query: { error: 'login_code_invalid' } });
      return;
    }

    const credentials = await SessionService.create(code, verifier);
    saveAuthToken(credentials.token);
    await router.replace(takeDestination());
  } catch (error) {
    await router.replace({ path: '/login', query: { error: error.code ?? 'google_unavailable' } });
  }
});
</script>
