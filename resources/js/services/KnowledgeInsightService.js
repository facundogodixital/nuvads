import { APICall } from '@/helpers/APICall';

export default {
  async list({ types }) {
    return APICall('/api/knowledge-insights', 'get', { types });
  },
};
