import client, { unwrap } from "./client";

const dashboardApi = {
  stats(signal) {
    return client.get("/dashboard/stats", { signal }).then(unwrap);
  },
};

export default dashboardApi;
