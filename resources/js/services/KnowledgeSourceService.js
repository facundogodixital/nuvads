import { APICall } from '@/helpers/APICall';

export default {
  async delete(knowledgeSourceId) {
    return APICall(`/api/knowledge-sources/${knowledgeSourceId}`, 'delete');
  },
};
