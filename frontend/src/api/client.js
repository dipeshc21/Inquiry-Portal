import axios from "axios";

const TOKEN_KEY = "inquiry_portal_token";

export const AUTH_EXPIRED_EVENT = "inquiry-portal:auth-expired";

export const tokenStorage = {
  get() {
    return sessionStorage.getItem(TOKEN_KEY);
  },

  set(token) {
    sessionStorage.setItem(TOKEN_KEY, token);
  },

  clear() {
    sessionStorage.removeItem(TOKEN_KEY);
  },
};

function serializeParams(params) {
  const query = new URLSearchParams();

  Object.entries(params ?? {}).forEach(([key, value]) => {
    if (value === undefined || value === null || value === "") return;

    if (Array.isArray(value)) {
      value.forEach((item) => query.append(`${key}[]`, String(item)));
    } else {
      query.append(key, String(value));
    }
  });

  return query.toString();
}

const client = axios.create({
  baseURL:
    import.meta.env.VITE_API_URL || "http://localhost:8000/api/v1",
  timeout: 30000,
  headers: {
    Accept: "application/json",
  },
  paramsSerializer: {
    serialize: serializeParams,
  },
});

client.interceptors.request.use((config) => {
  const token = tokenStorage.get();

  if (token && !config.skipAuth) {
    config.headers.Authorization = `Bearer ${token}`;
  }

  return config;
});

client.interceptors.response.use(
  (response) => response,
  async (error) => {
    // Download failures may arrive as JSON inside a Blob.
    if (
      error.response?.data instanceof Blob &&
      error.response.data.type.includes("json")
    ) {
      try {
        error.response.data = JSON.parse(
          await error.response.data.text(),
        );
      } catch {
        // Preserve the original transport error if decoding fails.
      }
    }

    if (
      error.response?.status === 401 &&
      !error.config?.skipAuth &&
      tokenStorage.get()
    ) {
      tokenStorage.clear();
      window.dispatchEvent(new Event(AUTH_EXPIRED_EVENT));
    }

    return Promise.reject(error);
  },
);

export const unwrap = (response) => response.data;

export default client;
