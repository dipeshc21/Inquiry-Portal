import client, { unwrap } from "./client";

const messagesApi = {
  list(inquiryId, params = {}, signal) {
    return client
      .get(`/inquiries/${inquiryId}/messages`, { params, signal })
      .then(unwrap);
  },

  create(inquiryId, body) {
    return client
      .post(`/inquiries/${inquiryId}/messages`, { body })
      .then(unwrap);
  },
};

export default messagesApi;
