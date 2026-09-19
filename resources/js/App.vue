<template>
  <main v-if="navigationError">
    <p role="alert">
      {{ navigationError }}
    </p>
    <button
      type="button"
      @click="retryNavigation"
    >
      Reintentar
    </button>
  </main>
  <RouterView v-else />
</template>


<script setup>
import { RouterView } from 'vue-router';
import { navigationError } from '@/router';

function retryNavigation() {
  window.location.reload();
}
</script>
