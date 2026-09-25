import { APICall } from '@/helpers/APICall';

export default {
  async getWebsiteInsights() {
    return APICall('/api/knowledge-insights/website');
  },

  async getInstagramInsights() {
    return APICall('/api/knowledge-insights/instagram');
  },

  async getMetaAdsInsights() {
    return APICall('/api/knowledge-insights/meta-ads');
  },

  async getGoogleReviewsInsights() {
    return APICall('/api/knowledge-insights/google-reviews');
  },

  async getWhatsAppConversationsInsights() {
    return APICall('/api/knowledge-insights/whatsapp-conversations');
  },

  async getAudioInsights() {
    return APICall('/api/knowledge-insights/audio');
  },

  async getUploadedFilesInsights() {
    return APICall('/api/knowledge-insights/uploaded-files');
  },
};
