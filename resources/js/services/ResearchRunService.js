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

  // El audio se graba en la pantalla y viaja con el pedido.
  async createAudioResearch(audioFile) {
    return APIUpload('/api/research-runs', audioFile, { type: 'audio' }, {
      fileFieldName: 'audio_file',
    });
  },

  // Las fotos y los documentos viajan juntos, en un solo pedido; el análisis arranca al subirlos.
  async createUploadedFilesResearch(files) {
    return APIUpload('/api/research-runs', files, { type: 'uploaded_files' }, {
      fileFieldName: 'files[]',
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

  async getAudioResearchStatus() {
    return APICall('/api/research-runs/audio/status');
  },

  async getUploadedFilesResearchStatus() {
    return APICall('/api/research-runs/uploaded-files/status');
  },
};
