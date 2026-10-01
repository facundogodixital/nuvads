import { APICall } from '@/helpers/APICall';

export default {
  // Pide al modelo ideas del tipo para la marca y espera su respuesta, que puede tardar hasta dos minutos. No guarda
  // nada: devuelve las ideas sugeridas, cada una con las reseñas que muestra.
  async generateSuggestedIdeas(contentTypeId) {
    return APICall(`/api/content-types/${contentTypeId}/suggested-ideas`, 'post');
  },

  // Guarda la idea sugerida que eligió el usuario: content_type_id, title, angle, knowledge_insight_ids y
  // knowledge_source_ids, tal como llegaron.
  async create(attributes) {
    return APICall('/api/ideas', 'post', attributes);
  },
};
