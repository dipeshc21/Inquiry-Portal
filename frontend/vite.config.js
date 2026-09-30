import { defineConfig, loadEnv } from "vite";
import react from "@vitejs/plugin-react";

const LOOPBACK_HOSTS = new Set(["localhost", "127.0.0.1", "0.0.0.0", "[::1]"]);

/**
 * On hosted builds (Vercel sets VERCEL=1), refuse to build unless
 * VITE_API_URL points at a public HTTPS backend. Without this check a
 * missing variable produces a site that silently calls the wrong URL.
 */
function assertHostedApiUrl(value) {
  const help =
    "Set VITE_API_URL in Vercel → Settings → Environment Variables " +
    "(e.g. https://your-backend.up.railway.app/api/v1), then redeploy.";

  if (!value || !value.trim()) {
    throw new Error(`VITE_API_URL is not set. ${help}`);
  }

  let url;
  try {
    url = new URL(value.trim());
  } catch {
    throw new Error(`VITE_API_URL is not a valid URL: "${value}". ${help}`);
  }

  if (url.protocol !== "https:") {
    throw new Error(`VITE_API_URL must use https:// on a hosted build. ${help}`);
  }

  if (LOOPBACK_HOSTS.has(url.hostname)) {
    throw new Error(`VITE_API_URL points to ${url.hostname}, which only exists on your own computer. ${help}`);
  }
}

export default defineConfig(({ command, mode }) => {
  const env = loadEnv(mode, process.cwd(), "VITE_");

  if (command === "build" && process.env.VERCEL) {
    assertHostedApiUrl(env.VITE_API_URL);
  }

  return {
    plugins: [react()],
    server: {
      host: "127.0.0.1",
      port: 5173,
      strictPort: true,
    },
    preview: {
      host: "127.0.0.1",
      port: 4173,
      strictPort: true,
    },
    build: {
      sourcemap: false,
      chunkSizeWarningLimit: 750,
    },
  };
});
