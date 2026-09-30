import client, { unwrap } from "./client";

const attachmentsApi = {
  upload(inquiryId, files, onUploadProgress) {
    const form = new FormData();
    files.forEach((file) => form.append("files[]", file));

    return client
      .post(`/inquiries/${inquiryId}/attachments`, form, {
        onUploadProgress,
      })
      .then(unwrap);
  },

  download(id) {
    return client.get(`/attachments/${id}/download`, {
      responseType: "blob",
      timeout: 60000,
    });
  },

  remove(id) {
    return client.delete(`/attachments/${id}`).then(unwrap);
  },
};

export default attachmentsApi;
