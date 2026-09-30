import client, { unwrap } from "./client";

const remindersApi = {
  list(inquiryId, params = {}, signal) {
    return client
      .get(`/inquiries/${inquiryId}/reminders`, { params, signal })
      .then(unwrap);
  },

  mine(params = {}, signal) {
    return client
      .get("/reminders/upcoming", { params, signal })
      .then(unwrap);
  },

  create(inquiryId, values) {
    return client
      .post(`/inquiries/${inquiryId}/reminders`, {
        title: values.title,
        remind_at: new Date(values.remind_at).toISOString(),
      })
      .then(unwrap);
  },

  complete(id) {
    return client.patch(`/reminders/${id}/complete`).then(unwrap);
  },

  remove(id) {
    return client.delete(`/reminders/${id}`).then(unwrap);
  },
};

export default remindersApi;
