import { APICall } from '@/helpers/APICall';

export default {
  // Devuelve el nuevo análisis de los archivos que quedan.
  async delete(knowledgeSourceId) {
    return APICall(`/api/uploaded-files/${knowledgeSourceId}`, 'delete');
  },
};
