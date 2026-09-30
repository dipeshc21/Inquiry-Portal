import { useEffect, useState } from "react";
import { Save, Shuffle } from "lucide-react";
import toast from "react-hot-toast";
import settingsApi from "../api/settings";
import Button from "../components/Button";
import DetailPanel from "../components/DetailPanel";
import ErrorState from "../components/ErrorState";
import FormSelect from "../components/FormSelect";
import Spinner from "../components/Spinner";
import useFetch from "../hooks/useFetch";
import { errorMessage, fieldErrors } from "../utils/errors";

export default function SettingsPage() {
  const settings = useFetch((signal) => settingsApi.assignment(signal));

  const [values, setValues] = useState({
    auto_assign: true,
    strategy: "round_robin",
  });
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);

  useEffect(() => {
    if (settings.data) {
      setValues({
        auto_assign: Boolean(settings.data.auto_assign),
        strategy: settings.data.strategy,
      });
    }
  }, [settings.data]);

  async function save(event) {
    event.preventDefault();
    if (saving) return;

    setSaving(true);
    setErrors({});

    try {
      const result = await settingsApi.updateAssignment(values);
      settings.setData(result.data);
      toast.success("Assignment settings saved.");
    } catch (error) {
      setErrors(fieldErrors(error));
      toast.error(errorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  const changed =
    settings.data &&
    (values.auto_assign !== Boolean(settings.data.auto_assign) ||
      values.strategy !== settings.data.strategy);

  return (
    <div className="page-container">
      <header>
        <h1 className="page-title">Settings</h1>
        <p className="page-description">
          Configure how new inquiries are assigned to your team.
        </p>
      </header>

      <div className="max-w-3xl">
        {settings.error ? (
          <ErrorState error={settings.error} onRetry={settings.refresh} />
        ) : settings.loading ? (
          <div className="flex justify-center py-16 text-brand-600">
            <Spinner label="Loading settings" />
          </div>
        ) : (
          <DetailPanel
            title="Automatic assignment"
            description="Applies to new public inquiry submissions"
          >
            <form noValidate onSubmit={save} className="space-y-6">
              <label className="flex items-start gap-3">
                <input
                  type="checkbox"
                  className="mt-1"
                  checked={values.auto_assign}
                  disabled={saving}
                  onChange={(event) =>
                    setValues((current) => ({
                      ...current,
                      auto_assign: event.target.checked,
                    }))
                  }
                />

                <span>
                  <span className="block text-sm font-medium">
                    Automatically assign new inquiries
                  </span>
                  <span className="mt-1 block text-sm leading-6 text-slate-500">
                    Assign submissions to active agents. If no eligible agent
                    is available, the inquiry remains unassigned.
                  </span>
                </span>
              </label>

              {errors.auto_assign && (
                <p className="field-error">{errors.auto_assign}</p>
              )}

              <FormSelect
                label="Assignment strategy"
                value={values.strategy}
                disabled={saving || !values.auto_assign}
                error={errors.strategy}
                options={[
                  { value: "round_robin", label: "Round robin" },
                  { value: "least_loaded", label: "Least loaded" },
                ]}
                onChange={(event) =>
                  setValues((current) => ({
                    ...current,
                    strategy: event.target.value,
                  }))
                }
              />

              <div className="flex gap-3 rounded-xl bg-brand-50 p-4">
                <Shuffle className="mt-0.5 h-5 w-5 shrink-0 text-brand-600" />
                <div className="text-sm leading-6 text-brand-900">
                  {values.strategy === "round_robin" ? (
                    <>
                      Round robin selects the active agent who was assigned
                      an inquiry least recently. Agents who have never been
                      assigned are selected first.
                    </>
                  ) : (
                    <>
                      Least loaded selects the active agent with the fewest
                      unresolved inquiries, including pending inquiries.
                      Ties are resolved by assignment recency.
                    </>
                  )}
                </div>
              </div>

              <p className="text-xs leading-5 text-slate-500">
                Existing assignments are unchanged. Administrators and
                managers can assign or unassign individual inquiries from
                their detail pages.
              </p>

              <div className="flex justify-end border-t border-slate-100 pt-5">
                <Button
                  type="submit"
                  loading={saving}
                  disabled={!changed}
                >
                  <Save className="h-4 w-4" />
                  Save settings
                </Button>
              </div>
            </form>
          </DetailPanel>
        )}
      </div>
    </div>
  );
}
