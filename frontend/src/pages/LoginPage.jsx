import { useState } from "react";
import { Link, Navigate, useLocation } from "react-router-dom";
import { ArrowRight, Inbox, LockKeyhole } from "lucide-react";
import toast from "react-hot-toast";
import Button from "../components/Button";
import FormInput from "../components/FormInput";
import Spinner from "../components/Spinner";
import useAuth from "../hooks/useAuth";
import { errorMessage, fieldErrors } from "../utils/errors";

export default function LoginPage() {
  const { user, loading, login } = useAuth();
  const location = useLocation();

  const [values, setValues] = useState({ email: "", password: "" });
  const [errors, setErrors] = useState({});
  const [message, setMessage] = useState("");
  const [submitting, setSubmitting] = useState(false);

  const requestedPath = location.state?.from;
  const destination =
    typeof requestedPath === "string" &&
    requestedPath.startsWith("/") &&
    !requestedPath.startsWith("//") &&
    !requestedPath.startsWith("/login")
      ? requestedPath
      : "/dashboard";

  async function submit(event) {
    event.preventDefault();

    const validation = {};

    if (!values.email.trim()) validation.email = "Enter your email address.";
    if (!values.password) validation.password = "Enter your password.";

    setErrors(validation);
    setMessage("");

    if (Object.keys(validation).length > 0) return;

    setSubmitting(true);

    try {
      await login(values);
      toast.success("Welcome back.");
    } catch (error) {
      const text =
        error.response?.status === 401
          ? "The email or password is incorrect, or the account is inactive."
          : errorMessage(error);

      setErrors(fieldErrors(error));
      setMessage(text);
      toast.error(text);
    } finally {
      setSubmitting(false);
    }
  }

  if (loading) {
    return (
      <div className="flex min-h-screen items-center justify-center text-brand-600">
        <Spinner label="Checking your session" />
      </div>
    );
  }

  if (user) return <Navigate to={destination} replace />;

  return (
    <main className="flex min-h-screen items-center justify-center px-5 py-12">
      <div className="w-full max-w-md">
        <Link
          to="/inquiry"
          className="mb-8 flex items-center justify-center gap-3"
        >
          <span className="rounded-xl bg-brand-600 p-3 text-white">
            <Inbox className="h-7 w-7" />
          </span>
          <span className="text-xl font-bold tracking-tight">
            Inquiry Portal
          </span>
        </Link>

        <section className="panel p-7 sm:p-9">
          <div className="mb-6">
            <h1 className="text-2xl font-bold tracking-tight">Welcome back</h1>
            <p className="mt-2 text-sm text-slate-500">
              Sign in to your customer workspace.
            </p>
          </div>

          <form noValidate onSubmit={submit} className="space-y-5">
            <FormInput
              label="Email address"
              type="email"
              autoComplete="username"
              required
              value={values.email}
              error={errors.email}
              disabled={submitting}
              onChange={(event) =>
                setValues((current) => ({
                  ...current,
                  email: event.target.value,
                }))
              }
            />

            <FormInput
              label="Password"
              type="password"
              autoComplete="current-password"
              required
              value={values.password}
              error={errors.password}
              disabled={submitting}
              onChange={(event) =>
                setValues((current) => ({
                  ...current,
                  password: event.target.value,
                }))
              }
            />

            {message && (
              <p
                role="alert"
                className="rounded-lg bg-red-50 p-3 text-sm text-red-700"
              >
                {message}
              </p>
            )}

            <Button
              type="submit"
              loading={submitting}
              className="w-full"
              size="lg"
            >
              Sign in
              <ArrowRight className="h-4 w-4" />
            </Button>
          </form>

          <p className="mt-6 flex items-center justify-center gap-2 text-xs text-slate-400">
            <LockKeyhole className="h-3.5 w-3.5" />
            Staff access only
          </p>
        </section>

        <p className="mt-6 text-center text-sm text-slate-500">
          Need to contact our team?{" "}
          <Link to="/inquiry" className="text-link">
            Send an inquiry
          </Link>
        </p>
      </div>
    </main>
  );
}
