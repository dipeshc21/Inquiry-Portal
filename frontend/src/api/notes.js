import client, { unwrap } from "./client";

const notesApi = {
  list(inquiryId, params = {}, signal) {
    return client
      .get(`/inquiries/${inquiryId}/notes`, { params, signal })
      .then(unwrap);
  },

  create(inquiryId, body) {
    return client
      .post(`/inquiries/${inquiryId}/notes`, { body })
      .then(unwrap);
  },

  update(id, body) {
    return client.put(`/notes/${id}`, { body }).then(unwrap);
  },

  remove(id) {
    return client.delete(`/notes/${id}`).then(unwrap);
  },
};

export default notesApi;
