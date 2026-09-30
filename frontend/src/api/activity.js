import client, { unwrap } from "./client";

const activityApi = {
  list(params = {}, signal) {
    return client.get("/activity-logs", { params, signal }).then(unwrap);
  },

  forInquiry(inquiryId, params = {}, signal) {
    return client
      .get(`/inquiries/${inquiryId}/activity`, { params, signal })
      .then(unwrap);
  },
};

export default activityApi;
