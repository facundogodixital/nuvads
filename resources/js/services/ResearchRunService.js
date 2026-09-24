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

  async getMetaAdsResearchStatus() {
    return APICall('/api/research-runs/meta-ads/status');
  },

  async getGoogleReviewsResearchStatus() {
    return APICall('/api/research-runs/google-reviews/status');
  },
};
