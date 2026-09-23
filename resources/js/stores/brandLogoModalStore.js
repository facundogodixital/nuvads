import { ref } from 'vue';
import { defineStore } from 'pinia';

export const useBrandLogoModalStore = defineStore('brandLogoModal', () => {
  const isOpen = ref(false);
  const logoUrl = ref('');

  function open(url) {
    logoUrl.value = url;
    isOpen.value = true;
  }

  function close() {
    isOpen.value = false;
    logoUrl.value = '';
  }

  return { isOpen, logoUrl, open, close };
});
