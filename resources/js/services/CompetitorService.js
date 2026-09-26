import { APICall } from '@/helpers/APICall';

export default {
  // Devuelve competitors y research_statuses: el estado de las cuatro fuentes de cada competidor, por su ID.
  async list() {
    return APICall('/api/competitors');
  },

  // Devuelve competitor y research_statuses: el estado de sus cuatro fuentes, por tipo.
  async find(competitorId) {
    return APICall(`/api/competitors/${competitorId}`);
  },

  async create(attributes) {
    return APICall('/api/competitors', 'post', attributes);
  },

  async update(competitorId, attributes) {
    return APICall(`/api/competitors/${competitorId}`, 'patch', attributes);
  },

  async delete(competitorId) {
    return APICall(`/api/competitors/${competitorId}`, 'delete');
  },
};
