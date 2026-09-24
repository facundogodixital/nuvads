import { APICall } from '@/helpers/APICall';

export default {
  async create(attributes) {
    return APICall('/api/research-runs', 'post', attributes);
  },

  async getWebsiteResearchStatus() {
    return APICall('/api/research-runs/website/status');
  },

  async getInstagramResearchStatus() {
    return APICall('/api/research-runs/instagram/status');
  },
};
