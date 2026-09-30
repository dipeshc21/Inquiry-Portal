import {
  createContext,
  useCallback,
  useEffect,
  useMemo,
  useState,
} from "react";
import authApi from "../api/auth";
import {
  AUTH_EXPIRED_EVENT,
  tokenStorage,
} from "../api/client";

export const AuthContext = createContext(null);

export function AuthProvider({ children }) {
  const [user, setUser] = useState(null);
  const [loading, setLoading] = useState(true);
  const [authError, setAuthError] = useState(null);
  const [bootstrapVersion, setBootstrapVersion] = useState(0);

  useEffect(() => {
    const controller = new AbortController();
    let active = true;

    async function bootstrap() {
      setLoading(true);
      setAuthError(null);

      if (!tokenStorage.get()) {
        setUser(null);
        setLoading(false);
        return;
      }

      try {
        const result = await authApi.me(controller.signal);
        if (active) setUser(result.data);
      } catch (error) {
        if (!active || error.code === "ERR_CANCELED") return;

        setUser(null);

        if (error.response?.status === 401) {
          tokenStorage.clear();
        } else {
          setAuthError(error);
        }
      } finally {
        if (active) setLoading(false);
      }
    }

    bootstrap();

    return () => {
      active = false;
      controller.abort();
    };
  }, [bootstrapVersion]);

  useEffect(() => {
    function expireSession() {
      setUser(null);
      setAuthError(null);
    }

    window.addEventListener(AUTH_EXPIRED_EVENT, expireSession);

    return () => {
      window.removeEventListener(AUTH_EXPIRED_EVENT, expireSession);
    };
  }, []);

  const login = useCallback(async (credentials) => {
    const result = await authApi.login(credentials);

    tokenStorage.set(result.data.token);
    setUser(result.data.user);
    setAuthError(null);

    return result.data.user;
  }, []);

  const logout = useCallback(async () => {
    try {
      if (tokenStorage.get()) await authApi.logout();
    } finally {
      tokenStorage.clear();
      setUser(null);
      setAuthError(null);
    }
  }, []);

  const refreshUser = useCallback(async () => {
    const result = await authApi.me();
    setUser(result.data);
    return result.data;
  }, []);

  const retryBootstrap = useCallback(() => {
    setBootstrapVersion((version) => version + 1);
  }, []);

  const value = useMemo(
    () => ({
      user,
      loading,
      authError,
      login,
      logout,
      refreshUser,
      retryBootstrap,
      isAuthenticated: Boolean(user),
      isAdmin: user?.role === "admin",
      canManageInquiries: ["admin", "manager"].includes(user?.role),
    }),
    [
      user,
      loading,
      authError,
      login,
      logout,
      refreshUser,
      retryBootstrap,
    ],
  );

  return (
    <AuthContext.Provider value={value}>
      {children}
    </AuthContext.Provider>
  );
}
