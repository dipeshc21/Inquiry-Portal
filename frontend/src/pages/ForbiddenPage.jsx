import { ShieldX } from "lucide-react";
import { Link } from "react-router-dom";
import useAuth from "../hooks/useAuth";

export default function ForbiddenPage() {
  const { user } = useAuth();

  return (
    <main className="flex min-h-screen items-center justify-center px-5 py-12">
      <div className="max-w-md text-center">
        <ShieldX className="mx-auto h-16 w-16 text-amber-500" />
        <p className="mt-6 text-sm font-semibold text-slate-400">403</p>
        <h1 className="mt-2 text-3xl font-bold">Access restricted</h1>
        <p className="mt-4 leading-7 text-slate-500">
          Your account does not have permission to view this page.
          Contact an administrator if you need access.
        </p>
        <Link
          to={user ? "/dashboard" : "/login"}
          className="mt-7 inline-flex rounded-lg bg-brand-600 px-5 py-3 text-sm font-medium text-white hover:bg-brand-700"
        >
          {user ? "Return to dashboard" : "Go to sign in"}
        </Link>
      </div>
    </main>
  );
}
