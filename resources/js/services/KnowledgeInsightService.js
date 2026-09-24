import { APICall } from '@/helpers/APICall';

export default {
  async getWebsiteInsights() {
    return APICall('/api/knowledge-insights/website');
  },

  async getInstagramInsights() {
    return APICall('/api/knowledge-insights/instagram');
  },
};
