import { FileQuestion } from "lucide-react";
import { Link } from "react-router-dom";
import useAuth from "../hooks/useAuth";

export default function NotFoundPage() {
  const { user } = useAuth();

  return (
    <main className="flex min-h-screen items-center justify-center px-5 py-12">
      <div className="max-w-md text-center">
        <FileQuestion className="mx-auto h-16 w-16 text-brand-500" />
        <p className="mt-6 text-sm font-semibold text-slate-400">404</p>
        <h1 className="mt-2 text-3xl font-bold">Page not found</h1>
        <p className="mt-4 leading-7 text-slate-500">
          This page does not exist, or the address may have changed.
        </p>
        <Link
          to={user ? "/dashboard" : "/inquiry"}
          className="mt-7 inline-flex rounded-lg bg-brand-600 px-5 py-3 text-sm font-medium text-white hover:bg-brand-700"
        >
          {user ? "Return to dashboard" : "Go to inquiry form"}
        </Link>
      </div>
    </main>
  );
}
