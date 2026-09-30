import client, { unwrap } from "./client";

const authApi = {
  login(credentials) {
    return client
      .post("/auth/login", credentials, { skipAuth: true })
      .then(unwrap);
  },

  logout() {
    return client.post("/auth/logout").then(unwrap);
  },

  me(signal) {
    return client.get("/auth/me", { signal }).then(unwrap);
  },
};

export default authApi;
