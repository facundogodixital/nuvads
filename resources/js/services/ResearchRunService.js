import { APICall } from '@/helpers/APICall';

export default {
  async create(attributes) {
    return APICall('/api/research-runs', 'post', attributes);
  },

  async getWebsiteStatus() {
    return APICall('/api/research-runs/website/status');
  },
};
