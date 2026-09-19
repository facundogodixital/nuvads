import { APICall } from '@/helpers/APICall';

export default {
  async create(code, verifier) {
    return APICall('/api/auth/exchange', 'post', { code, verifier });
  },

  async find() {
    return APICall('/api/auth/me');
  },

  async delete() {
    return APICall('/api/auth/logout', 'post');
  },
};
