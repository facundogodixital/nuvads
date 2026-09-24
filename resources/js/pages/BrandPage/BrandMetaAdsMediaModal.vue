<template>
  <div
    v-if="brandMetaAdsMediaModalStore.isOpen"
    class="modal-scrim fixed inset-0 z-50 flex items-center justify-center p-4"
    @click.self="brandMetaAdsMediaModalStore.close()"
  >
    <div
      ref="dialog"
      role="dialog"
      aria-modal="true"
      aria-label="Contenido del anuncio"
      tabindex="-1"
      class="flex max-h-full max-w-3xl min-w-72 flex-col rounded-sm border border-border bg-surface-raised outline-none"
      @keydown.esc="brandMetaAdsMediaModalStore.close()"
      @keydown.left="showPreviousMedia"
      @keydown.right="showNextMedia"
    >
      <div class="flex min-h-0 flex-1 items-center justify-center bg-surface p-3">
        <p
          v-if="hasMediaFailed"
          class="max-w-xs p-8 text-center text-sm leading-6 text-text-muted"
        >
          Este contenido ya no está disponible desde Meta. Puedes verlo en la Biblioteca de anuncios.
        </p>
        <video
          v-else-if="currentMedia.type === 'video'"
          :key="currentMedia.url"
          :src="currentMedia.url"
          :poster="currentMedia.posterUrl"
          controls
          playsinline
          preload="metadata"
          class="max-h-[75dvh] max-w-full rounded-sm"
          @error="hasMediaFailed = true"
        />
        <img
          v-else
          :key="currentMedia.url"
          :src="currentMedia.url"
          :alt="currentMedia.description"
          referrerpolicy="no-referrer"
          class="max-h-[75dvh] max-w-full rounded-sm object-contain"
          @error="hasMediaFailed = true"
        >
      </div>

      <footer class="flex flex-wrap items-center justify-between gap-3 border-t border-border px-3 py-2">
        <div
          v-if="mediaItems.length > 1"
          class="flex items-center gap-1"
        >
          <button
            type="button"
            aria-label="Contenido anterior"
            :disabled="isFirstMedia"
            class="flex h-11 w-11 items-center justify-center rounded-sm enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:text-text-muted disabled:opacity-40"
            @click="showPreviousMedia"
          >
            <svg
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            ><path
              d="m15 6-6 6 6 6"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            /></svg>
          </button>
          <span class="spec-label min-w-12 text-center tabular-nums">
            {{ brandMetaAdsMediaModalStore.mediaIndex + 1 }} de {{ mediaItems.length }}
          </span>
          <button
            type="button"
            aria-label="Contenido siguiente"
            :disabled="isLastMedia"
            class="flex h-11 w-11 items-center justify-center rounded-sm enabled:cursor-pointer enabled:hover:bg-surface-selected disabled:text-text-muted disabled:opacity-40"
            @click="showNextMedia"
          >
            <svg
              class="h-5 w-5"
              viewBox="0 0 24 24"
              fill="none"
              aria-hidden="true"
            ><path
              d="m9 6 6 6-6 6"
              stroke="currentColor"
              stroke-width="1.5"
              stroke-linecap="round"
              stroke-linejoin="round"
            /></svg>
          </button>
        </div>

        <div class="ml-auto flex items-center gap-3">
          <a
            :href="brandMetaAdsMediaModalStore.ad.payload.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-11 items-center text-sm underline underline-offset-4 hover:text-accent"
          >
            Ver en la Biblioteca de anuncios
          </a>
          <button
            type="button"
            class="min-h-11 shrink-0 cursor-pointer rounded-sm border border-border px-3 text-sm font-medium hover:bg-surface-selected"
            @click="brandMetaAdsMediaModalStore.close()"
          >
            Cerrar
          </button>
        </div>
      </footer>
    </div>
  </div>
</template>


<script setup>
import { ref, watch, computed, nextTick } from 'vue';
import { useBrandMetaAdsMediaModalStore } from '@/stores/brandMetaAdsMediaModalStore';

const brandMetaAdsMediaModalStore = useBrandMetaAdsMediaModalStore();

const dialog = ref(null);
const hasMediaFailed = ref(false);
let openerElement = null;

// Los videos se reproducen con su portada mientras cargan; las imágenes llevan lo que vio el análisis.
const mediaItems = computed(() => {
  const ad = brandMetaAdsMediaModalStore.ad;
  if (!ad) {
    return [];
  }

  return ad.payload.media.map((media, index) => {
    const isVideo = media.type === 'video' && Boolean(media.video_url);
    if (isVideo) {
      return { type: 'video', url: media.video_url, posterUrl: media.image_url };
    }
    return { type: 'image', url: media.image_url, description: ad.payload.images?.[index]?.description ?? '' };
  });
});
const currentMedia = computed(() => mediaItems.value[brandMetaAdsMediaModalStore.mediaIndex] ?? {});
const isFirstMedia = computed(() => brandMetaAdsMediaModalStore.mediaIndex === 0);
const isLastMedia = computed(() => brandMetaAdsMediaModalStore.mediaIndex === mediaItems.value.length - 1);

// Al abrir, el foco entra al diálogo para que funcionen Escape y las flechas; al cerrar, vuelve a lo que se clickeó.
watch(() => brandMetaAdsMediaModalStore.isOpen, async (isOpen) => {
  if (!isOpen) {
    openerElement?.focus();
    openerElement = null;
    return;
  }
  openerElement = document.activeElement;
  await nextTick();
  dialog.value?.focus();
});

watch(() => [brandMetaAdsMediaModalStore.ad, brandMetaAdsMediaModalStore.mediaIndex], () => {
  hasMediaFailed.value = false;
});

function showPreviousMedia() {
  if (isFirstMedia.value) {
    return;
  }
  brandMetaAdsMediaModalStore.showMedia(brandMetaAdsMediaModalStore.mediaIndex - 1);
}

function showNextMedia() {
  if (isLastMedia.value) {
    return;
  }
  brandMetaAdsMediaModalStore.showMedia(brandMetaAdsMediaModalStore.mediaIndex + 1);
}
</script>


<style scoped>
.modal-scrim {
  background-color: var(--scrim);
}
</style>
