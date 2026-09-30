import client, { unwrap } from "./client";

const usersApi = {
  list(params = {}, signal) {
    return client.get("/users", { params, signal }).then(unwrap);
  },

  assignable(signal) {
    return client.get("/users/assignable", { signal }).then(unwrap);
  },

  get(id, signal) {
    return client.get(`/users/${id}`, { signal }).then(unwrap);
  },

  create(values) {
    return client.post("/users", values).then(unwrap);
  },

  update(id, values) {
    return client.put(`/users/${id}`, values).then(unwrap);
  },

  remove(id) {
    return client.delete(`/users/${id}`).then(unwrap);
  },
};

export default usersApi;
