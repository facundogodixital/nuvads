import { APICall } from '@/helpers/APICall';

export default {
  async list() {
    return APICall('/api/content-types');
  },
};
