import client, { unwrap } from "./client";

const settingsApi = {
  assignment(signal) {
    return client
      .get("/settings/assignment", { signal })
      .then(unwrap);
  },

  updateAssignment(values) {
    return client
      .put("/settings/assignment", values)
      .then(unwrap);
  },
};

export default settingsApi;
