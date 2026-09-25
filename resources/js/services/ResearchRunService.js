import { APICall, APIUpload } from '@/helpers/APICall';

export default {
  async create(attributes) {
    return APICall('/api/research-runs', 'post', attributes);
  },

  // Los chats de WhatsApp no salen de la marca: el zip viaja con el pedido.
  async createWhatsAppConversationsResearch(zipFile) {
    return APIUpload('/api/research-runs', zipFile, { type: 'whatsapp_conversations' }, {
      fileFieldName: 'zip_file',
    });
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

  async getWhatsAppConversationsResearchStatus() {
    return APICall('/api/research-runs/whatsapp-conversations/status');
  },
};
