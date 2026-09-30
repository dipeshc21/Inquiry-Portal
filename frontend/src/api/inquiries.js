import client, { unwrap } from "./client";

const inquiriesApi = {
  list(params = {}, signal) {
    return client.get("/inquiries", { params, signal }).then(unwrap);
  },

  get(id, signal) {
    return client.get(`/inquiries/${id}`, { signal }).then(unwrap);
  },

  submit(values, files = []) {
    const form = new FormData();

    Object.entries(values).forEach(([key, value]) => {
      if (value !== undefined && value !== null) {
        form.append(key, String(value));
      }
    });

    files.forEach((file) => form.append("files[]", file));

    return client
      .post("/public/inquiries", form, { skipAuth: true })
      .then(unwrap);
  },

  update(id, values) {
    return client.put(`/inquiries/${id}`, values).then(unwrap);
  },

  status(id, status, note) {
    return client
      .patch(`/inquiries/${id}/status`, {
        status,
        ...(note ? { note } : {}),
      })
      .then(unwrap);
  },

  assign(id, assignedTo) {
    return client
      .patch(`/inquiries/${id}/assign`, {
        assigned_to:
          assignedTo === "" || assignedTo === null
            ? null
            : Number(assignedTo),
      })
      .then(unwrap);
  },

  remove(id) {
    return client.delete(`/inquiries/${id}`).then(unwrap);
  },

  export(params = {}) {
    return client.get("/inquiries/export", {
      params,
      responseType: "blob",
      timeout: 120000,
    });
  },
};

export default inquiriesApi;
