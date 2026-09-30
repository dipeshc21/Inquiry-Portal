import { Navigate, Outlet, useLocation } from "react-router-dom";
import useAuth from "../hooks/useAuth";
import ErrorState from "./ErrorState";
import Spinner from "./Spinner";

export default function ProtectedRoute({ roles, children }) {
  const {
    user,
    loading,
    authError,
    retryBootstrap,
  } = useAuth();

  const location = useLocation();

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center text-brand-600">
        <Spinner label="Loading your account" />
      </div>
    );
  }

  if (authError) {
    return (
      <div className="mx-auto max-w-lg px-4 py-20">
        <ErrorState error={authError} onRetry={retryBootstrap} />
      </div>
    );
  }

  if (!user) {
    return (
      <Navigate
        to="/login"
        replace
        state={{
          from: `${location.pathname}${location.search}`,
        }}
      />
    );
  }

  if (roles && !roles.includes(user.role)) {
    return <Navigate to="/403" replace />;
  }

  return children ?? <Outlet />;
}
