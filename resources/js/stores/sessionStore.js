import { ref } from 'vue';
import { defineStore } from 'pinia';
import SessionService from '@/services/SessionService';

export const useSessionStore = defineStore('session', () => {
  const session = ref(null);

  async function find() {
    session.value = await SessionService.find();
    return session.value;
  }

  function clear() {
    session.value = null;
  }

  return { session, find, clear };
});
