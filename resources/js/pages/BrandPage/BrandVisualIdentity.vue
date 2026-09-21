<template>
  <section
    id="visual-identity"
    class="scroll-mt-6 rounded-sm border border-border bg-surface-raised p-5 sm:p-6"
    aria-labelledby="visual-identity-heading"
  >
    <header class="mb-6">
      <h2
        id="visual-identity-heading"
        class="text-lg font-medium"
      >
        Tu identidad visual
      </h2>
      <p class="mt-1 text-sm text-text-muted">
        Los elementos que hacen reconocible a tu marca.
      </p>
    </header>
    <div class="grid gap-8 sm:grid-cols-[144px_minmax(0,1fr)]">
      <div>
        <p class="spec-label mb-3">
          Logo
        </p>
        <div class="flex h-32 items-center justify-center overflow-hidden rounded-sm border border-dashed border-border bg-surface p-3">
          <img
            v-if="logoUrl"
            :src="logoUrl"
            alt="Vista previa del logo de tu marca"
            class="max-h-full max-w-full object-contain"
          >
          <svg
            v-else
            class="h-9 w-9 text-text-muted"
            viewBox="0 0 32 32"
            fill="none"
            aria-hidden="true"
          ><rect
            x="4"
            y="4"
            width="24"
            height="24"
            rx="3"
            stroke="currentColor"
          /><path
            d="m5 24 7-8 5 5 4-4 6 7M21 11h.01"
            stroke="currentColor"
            stroke-width="2"
            stroke-linecap="round"
            stroke-linejoin="round"
          /></svg>
        </div>
        <label class="mt-3 flex min-h-11 cursor-pointer items-center justify-center rounded-sm border border-border px-3 text-sm font-medium hover:bg-surface-selected focus-within:outline-2 focus-within:outline-accent">
          {{ logoUrl ? 'Cambiar logo' : 'Cargar logo' }}
          <input
            type="file"
            accept="image/png,image/jpeg,image/webp"
            class="sr-only"
            @change="previewLogo"
          >
        </label>
        <button
          v-if="logoUrl"
          type="button"
          class="mt-1 min-h-11 w-full cursor-pointer text-xs text-text-muted hover:text-text"
          @click="removeLogo"
        >
          Quitar logo
        </button>
      </div>
      <div>
        <p class="spec-label mb-3">
          Paleta de colores
        </p>
        <div class="flex flex-wrap gap-3">
          <div
            v-for="(color, index) in colors"
            :key="index"
            class="w-20"
          >
            <input
              v-model="colors[index]"
              type="color"
              :aria-label="`Color de marca ${index + 1}`"
              class="h-16 w-full cursor-pointer rounded-sm border border-border bg-surface p-1"
            >
            <p class="spec-label mt-2 text-center">
              {{ color }}
            </p>
            <button
              type="button"
              :aria-label="`Quitar color ${color}`"
              class="min-h-11 w-full cursor-pointer text-xs text-text-muted hover:text-text"
              @click="colors.splice(index, 1)"
            >
              Quitar
            </button>
          </div>
          <label class="flex min-h-16 min-w-28 cursor-pointer items-center justify-center gap-2 rounded-sm border border-dashed border-border px-4 text-sm hover:bg-surface-selected focus-within:outline-2 focus-within:outline-accent">
            <span aria-hidden="true">+</span> Agregar color
            <input
              type="color"
              class="sr-only"
              aria-label="Elegir un nuevo color de marca"
              @change="colors.push($event.target.value)"
            >
          </label>
        </div>
        <p
          v-if="!colors.length"
          class="mt-3 text-sm leading-6 text-text-muted"
        >
          Agrega tus colores o revísalos cuando tengamos el análisis de tu marca.
        </p>
        <p class="mt-5 border-t border-border pt-4 text-xs leading-5 text-text-muted">
          PNG, JPG o WebP para el logo. Los archivos y colores se muestran solo en esta vista previa.
        </p>
      </div>
    </div>
    <p
      v-if="logoError"
      role="alert"
      class="mt-4 text-sm text-danger"
    >
      {{ logoError }}
    </p>
  </section>
</template>


<script setup>
import { ref, onBeforeUnmount } from 'vue';

const colors = ref([]);
const logoUrl = ref('');
const logoError = ref('');

onBeforeUnmount(() => URL.revokeObjectURL(logoUrl.value));

function previewLogo(event) {
  const file = event.target.files[0];
  if (!file) {
    return;
  }

  const isSupportedImage = ['image/png', 'image/jpeg', 'image/webp'].includes(file.type);
  if (!isSupportedImage) {
    logoError.value = 'Selecciona una imagen PNG, JPG o WebP.';
    return;
  }

  URL.revokeObjectURL(logoUrl.value);
  logoUrl.value = URL.createObjectURL(file);
  logoError.value = '';
  event.target.value = '';
}

function removeLogo() {
  URL.revokeObjectURL(logoUrl.value);
  logoUrl.value = '';
}
</script>
