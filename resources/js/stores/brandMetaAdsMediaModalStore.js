import { ref } from 'vue';
import { defineStore } from 'pinia';

export const useBrandMetaAdsMediaModalStore = defineStore('brandMetaAdsMediaModal', () => {
  const isOpen = ref(false);
  const ad = ref(null);
  const mediaIndex = ref(0);

  // Abre el anuncio en la imagen o el video pedido.
  function open(selectedAd, selectedMediaIndex = 0) {
    ad.value = selectedAd;
    mediaIndex.value = selectedMediaIndex;
    isOpen.value = true;
  }

  function showMedia(index) {
    mediaIndex.value = index;
  }

  function close() {
    isOpen.value = false;
    ad.value = null;
    mediaIndex.value = 0;
  }

  return { isOpen, ad, mediaIndex, open, showMedia, close };
});
