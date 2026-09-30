import { useState } from "react";
import { Link, useSearchParams } from "react-router-dom";
import {
  ArrowLeft,
  ArrowRight,
  CheckCircle2,
  Inbox,
  MessageSquare,
  Paperclip,
} from "lucide-react";
import toast from "react-hot-toast";
import inquiriesApi from "../api/inquiries";
import Button from "../components/Button";
import FileUpload from "../components/FileUpload";
import FormInput from "../components/FormInput";
import FormSelect from "../components/FormSelect";
import FormTextarea from "../components/FormTextarea";
import { APP_NAME, SOURCES } from "../utils/constants";
import { errorMessage, fieldErrors } from "../utils/errors";
import { validatePublicInquiry } from "../utils/validation";

export default function PublicInquiryPage() {
  const [searchParams] = useSearchParams();

  const [values, setValues] = useState(() => {
    const requestedSource = searchParams.get("source");

    return {
      name: "",
      email: "",
      phone: "",
      company: "",
      subject: "",
      message: "",
      source: SOURCES.some((source) => source.value === requestedSource)
        ? requestedSource
        : "website",
      utm_source: searchParams.get("utm_source") ?? "",
      utm_medium: searchParams.get("utm_medium") ?? "",
      utm_campaign: searchParams.get("utm_campaign") ?? "",
      honeypot: "",
    };
  });

  const [files, setFiles] = useState([]);
  const [errors, setErrors] = useState({});
  const [submitError, setSubmitError] = useState("");
  const [submitting, setSubmitting] = useState(false);
  const [reference, setReference] = useState("");

  function change(field, value) {
    setValues((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: undefined }));
  }

  async function submit(event) {
    event.preventDefault();

    const validation = validatePublicInquiry(values, files);
    setErrors(validation);
    setSubmitError("");

    if (Object.keys(validation).length > 0) {
      toast.error("Please check the highlighted fields.");
      return;
    }

    setSubmitting(true);

    try {
      const result = await inquiriesApi.submit(values, files);
      setReference(result.data.reference_no);
      setFiles([]);
      toast.success("Your inquiry has been received.");
      window.scrollTo({ top: 0, behavior: "smooth" });
    } catch (error) {
      setErrors(fieldErrors(error));
      setSubmitError(errorMessage(error));
      toast.error(errorMessage(error));
    } finally {
      setSubmitting(false);
    }
  }

  function startAnother() {
    setReference("");
    setErrors({});
    setSubmitError("");
    setValues((current) => ({
      ...current,
      subject: "",
      message: "",
      honeypot: "",
    }));
  }

  return (
    <div className="min-h-screen bg-white">
      <header className="border-b border-slate-100">
        <div className="mx-auto flex max-w-6xl items-center justify-between gap-4 px-5 py-5">
          <Link to="/inquiry" className="flex items-center gap-3">
            <span className="rounded-xl bg-brand-600 p-2.5 text-white">
              <Inbox className="h-6 w-6" />
            </span>
            <span className="font-bold tracking-tight">Inquiry Portal</span>
          </Link>

          <Link to="/login" className="text-sm text-slate-500 hover:text-brand-600">
            Staff sign in
          </Link>
        </div>
      </header>

      {reference ? (
        <main className="mx-auto max-w-xl px-5 py-20 text-center">
          <CheckCircle2 className="mx-auto h-16 w-16 text-emerald-500" />

          <h1 className="mt-6 text-3xl font-bold tracking-tight">
            Your inquiry is with us
          </h1>

          <p className="mt-4 leading-7 text-slate-600">
            Thank you for getting in touch. Our team will review your message
            and contact you using the email address you provided.
          </p>

          <div className="my-8 rounded-2xl border border-brand-100 bg-brand-50 p-6">
            <p className="text-sm text-brand-700">Your reference number</p>
            <p className="mt-2 select-all font-mono text-xl font-bold text-brand-900">
              {reference}
            </p>
          </div>

          <p className="mb-6 text-sm text-slate-500">
            Keep this reference for future correspondence.
          </p>

          <Button variant="secondary" onClick={startAnother}>
            <ArrowLeft className="h-4 w-4" />
            Submit another inquiry
          </Button>
        </main>
      ) : (
        <main className="mx-auto grid max-w-6xl gap-10 px-5 py-10 lg:grid-cols-[0.8fr_1.2fr] lg:gap-16 lg:py-16">
          <section>
            <span className="inline-flex rounded-full bg-brand-50 px-3 py-1 text-xs font-semibold text-brand-700">
              Let’s start a conversation
            </span>

            <h1 className="mt-5 text-4xl font-bold leading-tight tracking-tight text-slate-900 sm:text-5xl">
              How can we help your business?
            </h1>

            <p className="mt-6 text-lg leading-8 text-slate-500">
              Tell us what you need. We’ll connect you with the right person
              and keep your conversation moving.
            </p>

            <div className="mt-10 space-y-6">
              <div className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                  <MessageSquare className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="font-semibold">A conversation with our team</h2>
                  <p className="mt-1 text-sm leading-6 text-slate-500">
                    Share your goals, questions, or project requirements.
                  </p>
                </div>
              </div>

              <div className="flex gap-4">
                <span className="flex h-11 w-11 shrink-0 items-center justify-center rounded-xl bg-brand-50 text-brand-600">
                  <Paperclip className="h-5 w-5" />
                </span>
                <div>
                  <h2 className="font-semibold">Room for the details</h2>
                  <p className="mt-1 text-sm leading-6 text-slate-500">
                    Attach a document or image to help explain your request.
                  </p>
                </div>
              </div>
            </div>
          </section>

          <section className="panel p-5 sm:p-8">
            <h2 className="text-xl font-semibold">Send an inquiry</h2>
            <p className="mt-2 text-sm text-slate-500">
              Fields marked with an asterisk are required.
            </p>

            <form noValidate onSubmit={submit} className="mt-6 space-y-5">
              <fieldset disabled={submitting} className="space-y-5">
                <div className="grid gap-5 sm:grid-cols-2">
                  <FormInput
                    label="Full name"
                    name="name"
                    autoComplete="name"
                    required
                    maxLength={100}
                    value={values.name}
                    error={errors.name}
                    onChange={(event) => change("name", event.target.value)}
                  />

                  <FormInput
                    label="Email address"
                    name="email"
                    type="email"
                    autoComplete="email"
                    required
                    maxLength={255}
                    value={values.email}
                    error={errors.email}
                    onChange={(event) => change("email", event.target.value)}
                  />

                  <FormInput
                    label="Phone number"
                    name="phone"
                    type="tel"
                    autoComplete="tel"
                    maxLength={30}
                    value={values.phone}
                    error={errors.phone}
                    onChange={(event) => change("phone", event.target.value)}
                  />

                  <FormInput
                    label="Company"
                    name="company"
                    autoComplete="organization"
                    maxLength={150}
                    value={values.company}
                    error={errors.company}
                    onChange={(event) => change("company", event.target.value)}
                  />
                </div>

                <FormInput
                  label="Subject"
                  name="subject"
                  required
                  maxLength={150}
                  value={values.subject}
                  error={errors.subject}
                  onChange={(event) => change("subject", event.target.value)}
                />

                <FormTextarea
                  label="How can we help?"
                  name="message"
                  required
                  rows={6}
                  maxLength={5000}
                  value={values.message}
                  error={errors.message}
                  help={`${values.message.length}/5000 characters`}
                  onChange={(event) => change("message", event.target.value)}
                />

                <FormSelect
                  label="How did you hear about us?"
                  options={SOURCES}
                  value={values.source}
                  error={errors.source}
                  onChange={(event) => change("source", event.target.value)}
                />

                <FileUpload
                  files={files}
                  errors={errors}
                  disabled={submitting}
                  onChange={(next) => {
                    setFiles(next);
                    setErrors((current) =>
                      Object.fromEntries(
                        Object.entries(current).filter(
                          ([key]) => !key.startsWith("files"),
                        ),
                      ),
                    );
                  }}
                />

                <div
                  aria-hidden="true"
                  className="absolute -left-[10000px] h-px w-px overflow-hidden"
                >
                  <label htmlFor="contact-website">
                    Leave this field empty
                  </label>
                  <input
                    id="contact-website"
                    name="honeypot"
                    tabIndex={-1}
                    autoComplete="off"
                    value={values.honeypot}
                    onChange={(event) =>
                      change("honeypot", event.target.value)
                    }
                  />
                </div>
              </fieldset>

              {submitError && (
                <p
                  role="alert"
                  className="rounded-lg bg-red-50 p-3 text-sm text-red-700"
                >
                  {submitError}
                </p>
              )}

              {Object.entries(errors)
                .filter(([key]) => key.startsWith("utm_") || key === "honeypot")
                .map(([key, message]) =>
                  message ? (
                    <p key={key} role="alert" className="field-error">
                      {message}
                    </p>
                  ) : null,
                )}

              <Button
                type="submit"
                size="lg"
                loading={submitting}
                className="w-full"
              >
                Send inquiry
                <ArrowRight className="h-4 w-4" />
              </Button>
            </form>
          </section>
        </main>
      )}

      <footer className="border-t border-slate-100 px-5 py-6 text-center text-xs text-slate-400">
        {APP_NAME}
      </footer>
    </div>
  );
}
