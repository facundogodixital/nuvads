import { APICall } from '@/helpers/APICall';

export default {
  // type: website, instagram, meta_ads o google_reviews. El enlace sale del competidor.
  async create(competitorId, type) {
    return APICall(`/api/competitors/${competitorId}/research-runs`, 'post', { type });
  },

  async getWebsiteResearchStatus(competitorId) {
    return APICall(`/api/competitors/${competitorId}/research-runs/website/status`);
  },

  async getInstagramResearchStatus(competitorId) {
    return APICall(`/api/competitors/${competitorId}/research-runs/instagram/status`);
  },

  async getMetaAdsResearchStatus(competitorId) {
    return APICall(`/api/competitors/${competitorId}/research-runs/meta-ads/status`);
  },

  async getGoogleReviewsResearchStatus(competitorId) {
    return APICall(`/api/competitors/${competitorId}/research-runs/google-reviews/status`);
  },
};
