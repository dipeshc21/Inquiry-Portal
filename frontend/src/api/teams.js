import client, { unwrap } from "./client";

const teamsApi = {
  list(params = {}, signal) {
    return client.get("/teams", { params, signal }).then(unwrap);
  },

  get(id, signal) {
    return client.get(`/teams/${id}`, { signal }).then(unwrap);
  },

  create(values) {
    return client.post("/teams", values).then(unwrap);
  },

  update(id, values) {
    return client.put(`/teams/${id}`, values).then(unwrap);
  },

  remove(id) {
    return client.delete(`/teams/${id}`).then(unwrap);
  },
};

export default teamsApi;
