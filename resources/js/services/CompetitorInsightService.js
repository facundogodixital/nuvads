import { APICall } from '@/helpers/APICall';

export default {
  async getWebsiteInsights(competitorId) {
    return APICall(`/api/competitors/${competitorId}/insights/website`);
  },

  async getInstagramInsights(competitorId) {
    return APICall(`/api/competitors/${competitorId}/insights/instagram`);
  },

  async getMetaAdsInsights(competitorId) {
    return APICall(`/api/competitors/${competitorId}/insights/meta-ads`);
  },

  async getGoogleReviewsInsights(competitorId) {
    return APICall(`/api/competitors/${competitorId}/insights/google-reviews`);
  },
};
