import { ref } from 'vue';
import { defineStore } from 'pinia';

export const useBrandInstagramMediaModalStore = defineStore('brandInstagramMediaModal', () => {
  const isOpen = ref(false);
  const post = ref(null);
  const mediaIndex = ref(0);

  // Abre el posteo en la imagen pedida; los reels tienen un solo contenido, el video.
  function open(selectedPost, selectedMediaIndex = 0) {
    post.value = selectedPost;
    mediaIndex.value = selectedMediaIndex;
    isOpen.value = true;
  }

  function showMedia(index) {
    mediaIndex.value = index;
  }

  function close() {
    isOpen.value = false;
    post.value = null;
    mediaIndex.value = 0;
  }

  return { isOpen, post, mediaIndex, open, showMedia, close };
});
