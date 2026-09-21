import { APICall } from '@/helpers/APICall';

export default {
  async find() {
    return APICall('/api/brand');
  },

  async update(attributes) {
    return APICall('/api/brand', 'patch', attributes);
  },
};
