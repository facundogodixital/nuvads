<template>
  <div
    v-if="brandLogoModalStore.isOpen"
    class="modal-scrim fixed inset-0 z-50 flex items-center justify-center p-4"
    @click.self="brandLogoModalStore.close()"
  >
    <div
      ref="dialog"
      role="dialog"
      aria-modal="true"
      aria-label="Vista del logo"
      tabindex="-1"
      class="w-full max-w-lg rounded-sm border border-border bg-surface-raised p-4 shadow-lg outline-none"
      @keydown.esc="brandLogoModalStore.close()"
    >
      <div class="checkerboard flex h-72 items-center justify-center rounded-sm border border-border p-4">
        <img
          :src="brandLogoModalStore.logoUrl"
          alt="Logo de tu marca"
          class="max-h-full max-w-full object-contain"
        >
      </div>
      <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
        <p class="text-xs leading-5 text-text-muted">
          El damero se ve a través de las zonas transparentes. Si el logo tiene fondo de color, lo tapa.
        </p>
        <button
          type="button"
          class="min-h-11 shrink-0 cursor-pointer rounded-sm border border-border px-3 text-sm font-medium hover:bg-surface-selected"
          @click="brandLogoModalStore.close()"
        >
          Cerrar
        </button>
      </div>
    </div>
  </div>
</template>


<script setup>
import { ref, watch, nextTick } from 'vue';
import { useBrandLogoModalStore } from '@/stores/brandLogoModalStore';

const brandLogoModalStore = useBrandLogoModalStore();

const dialog = ref(null);

// Al abrir, el foco entra al diálogo para que Escape lo cierre.
watch(() => brandLogoModalStore.isOpen, async (isOpen) => {
  if (!isOpen) {
    return;
  }
  await nextTick();
  dialog.value?.focus();
});
</script>


<style scoped>
.modal-scrim {
  background-color: var(--scrim);
}

/* Damero con dos superficies del tema: hace visibles las zonas transparentes del logo. */
.checkerboard {
  background-color: var(--surface-raised);
  background-image: conic-gradient(
    var(--surface-selected) 25%, transparent 25% 50%, var(--surface-selected) 50% 75%, transparent 75%
  );
  background-size: 20px 20px;
}
</style>
