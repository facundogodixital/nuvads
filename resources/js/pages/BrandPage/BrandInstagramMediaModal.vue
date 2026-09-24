<template>
  <div
    v-if="brandInstagramMediaModalStore.isOpen"
    class="modal-scrim fixed inset-0 z-50 flex items-center justify-center p-4"
    @click.self="brandInstagramMediaModalStore.close()"
  >
    <div
      ref="dialog"
      role="dialog"
      aria-modal="true"
      aria-label="Contenido del posteo"
      tabindex="-1"
      class="flex max-h-full max-w-3xl min-w-72 flex-col rounded-sm border border-border bg-surface-raised outline-none"
      @keydown.esc="brandInstagramMediaModalStore.close()"
      @keydown.left="showPreviousMedia"
      @keydown.right="showNextMedia"
    >
      <div class="flex min-h-0 flex-1 items-center justify-center bg-surface p-3">
        <p
          v-if="hasMediaFailed"
          class="max-w-xs p-8 text-center text-sm leading-6 text-text-muted"
        >
          Este contenido ya no está disponible desde Instagram. Puedes verlo en el posteo original.
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
            {{ brandInstagramMediaModalStore.mediaIndex + 1 }} de {{ mediaItems.length }}
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
            :href="brandInstagramMediaModalStore.post.payload.url"
            target="_blank"
            rel="noopener noreferrer"
            class="inline-flex min-h-11 items-center text-sm underline underline-offset-4 hover:text-accent"
          >
            Ver en Instagram
          </a>
          <button
            type="button"
            class="min-h-11 shrink-0 cursor-pointer rounded-sm border border-border px-3 text-sm font-medium hover:bg-surface-selected"
            @click="brandInstagramMediaModalStore.close()"
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
import { useBrandInstagramMediaModalStore } from '@/stores/brandInstagramMediaModalStore';

const brandInstagramMediaModalStore = useBrandInstagramMediaModalStore();

const dialog = ref(null);
const hasMediaFailed = ref(false);
let openerElement = null;

// Los reels muestran su video, con la portada mientras carga; los demás, las imágenes que leyó el análisis.
const mediaItems = computed(() => {
  const post = brandInstagramMediaModalStore.post;
  if (!post) {
    return [];
  }

  const isReelWithVideo = post.payload.raw.type === 'Video' && Boolean(post.payload.raw.videoUrl);
  if (isReelWithVideo) {
    return [{ type: 'video', url: post.payload.raw.videoUrl, posterUrl: post.payload.image_urls?.[0] }];
  }
  return (post.payload.image_urls ?? []).map((url, index) => ({
    type: 'image',
    url,
    description: post.payload.images?.[index]?.description ?? '',
  }));
});
const currentMedia = computed(() => mediaItems.value[brandInstagramMediaModalStore.mediaIndex] ?? {});
const isFirstMedia = computed(() => brandInstagramMediaModalStore.mediaIndex === 0);
const isLastMedia = computed(() => brandInstagramMediaModalStore.mediaIndex === mediaItems.value.length - 1);

// Al abrir, el foco entra al diálogo para que funcionen Escape y las flechas; al cerrar, vuelve a lo que se clickeó.
watch(() => brandInstagramMediaModalStore.isOpen, async (isOpen) => {
  if (!isOpen) {
    openerElement?.focus();
    openerElement = null;
    return;
  }
  openerElement = document.activeElement;
  await nextTick();
  dialog.value?.focus();
});

watch(() => [brandInstagramMediaModalStore.post, brandInstagramMediaModalStore.mediaIndex], () => {
  hasMediaFailed.value = false;
});

function showPreviousMedia() {
  if (isFirstMedia.value) {
    return;
  }
  brandInstagramMediaModalStore.showMedia(brandInstagramMediaModalStore.mediaIndex - 1);
}

function showNextMedia() {
  if (isLastMedia.value) {
    return;
  }
  brandInstagramMediaModalStore.showMedia(brandInstagramMediaModalStore.mediaIndex + 1);
}
</script>


<style scoped>
.modal-scrim {
  background-color: var(--scrim);
}
</style>
