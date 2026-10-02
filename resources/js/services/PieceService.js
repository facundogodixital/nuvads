import { APICall } from '@/helpers/APICall';

export default {
  // Pide al modelo los textos de la pieza de una idea guardada y espera su respuesta, que puede tardar hasta dos
  // minutos. No guarda nada: devuelve la pieza sugerida. instructions son las indicaciones del usuario, o null.
  async generateSuggestedPiece(ideaId, instructions) {
    return APICall(`/api/ideas/${ideaId}/suggested-piece`, 'post', { instructions });
  },
};
