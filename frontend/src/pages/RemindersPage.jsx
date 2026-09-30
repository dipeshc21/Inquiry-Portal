import { useState } from "react";
import { Bell, RefreshCw } from "lucide-react";
import toast from "react-hot-toast";
import remindersApi from "../api/reminders";
import Button from "../components/Button";
import ConfirmDialog from "../components/ConfirmDialog";
import ErrorState from "../components/ErrorState";
import Pagination from "../components/Pagination";
import ReminderItem from "../components/ReminderItem";
import SearchInput from "../components/SearchInput";
import Spinner from "../components/Spinner";
import useDebounce from "../hooks/useDebounce";
import useFetch from "../hooks/useFetch";
import { errorMessage } from "../utils/errors";

const tabs = [
  { value: "upcoming", label: "Upcoming" },
  { value: "overdue", label: "Overdue" },
  { value: "completed", label: "Completed" },
];

export default function RemindersPage() {
  const [status, setStatus] = useState("upcoming");
  const [search, setSearch] = useState("");
  const query = useDebounce(search, 400);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [busyId, setBusyId] = useState(null);
  const [deleting, setDeleting] = useState(null);

  const list = useFetch(
    (signal) =>
      remindersApi.mine(
        { status, q: query, page, per_page: perPage },
        signal,
      ),
    [status, query, page, perPage],
  );

  async function refreshAfterMutation() {
    if (page > 1 && list.data?.length === 1) {
      setPage((current) => current - 1);
    } else {
      await list.refresh();
    }
  }

  async function complete(reminder) {
    if (busyId !== null) return;
    setBusyId(reminder.id);

    try {
      await remindersApi.complete(reminder.id);
      toast.success("Reminder completed.");
      await refreshAfterMutation();
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusyId(null);
    }
  }

  async function remove() {
    if (!deleting || busyId !== null) return;
    setBusyId(deleting.id);

    try {
      await remindersApi.remove(deleting.id);
      setDeleting(null);
      toast.success("Reminder deleted.");
      await refreshAfterMutation();
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setBusyId(null);
    }
  }

  return (
    <div className="page-container">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="page-title">My reminders</h1>
          <p className="page-description">
            Keep track of your follow-ups. Add reminders from an inquiry’s
            detail page.
          </p>
        </div>

        <Button
          variant="secondary"
          loading={list.loading}
          onClick={async () => {
            if (await list.refresh()) toast.success("Reminders refreshed.");
          }}
        >
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      </header>

      <section className="panel overflow-hidden">
        <div className="flex flex-wrap gap-2 border-b border-slate-200 p-4">
          {tabs.map((tab) => (
            <Button
              key={tab.value}
              variant={status === tab.value ? "primary" : "ghost"}
              aria-pressed={status === tab.value}
              onClick={() => {
                setStatus(tab.value);
                setPage(1);
              }}
            >
              {tab.label}
            </Button>
          ))}
        </div>

        <div className="space-y-5 p-5">
          <SearchInput
            value={search}
            onChange={(value) => {
              setSearch(value);
              setPage(1);
            }}
            placeholder="Search reminder titles"
            className="max-w-lg"
          />

          {status === "upcoming" && (
            <p className="text-xs text-slate-500">
              Showing reminders due within the next 30 days.
            </p>
          )}

          {list.error ? (
            <ErrorState error={list.error} onRetry={list.refresh} />
          ) : list.loading ? (
            <div className="flex justify-center py-14 text-brand-600">
              <Spinner label="Loading reminders" />
            </div>
          ) : list.data?.length ? (
            <div className="grid gap-4 xl:grid-cols-2">
              {list.data.map((reminder) => (
                <ReminderItem
                  key={reminder.id}
                  reminder={reminder}
                  showInquiry
                  busy={busyId !== null}
                  onComplete={complete}
                  onDelete={setDeleting}
                />
              ))}
            </div>
          ) : (
            <div className="py-14 text-center">
              <Bell className="mx-auto h-9 w-9 text-slate-300" />
              <p className="mt-3 font-medium text-slate-700">
                No {status} reminders
              </p>
              <p className="mt-1 text-sm text-slate-500">
                Your matching follow-ups will appear here.
              </p>
            </div>
          )}
        </div>

        {!list.error && (
          <Pagination
            meta={{ current_page: page, per_page: perPage, ...list.meta }}
            disabled={list.loading || busyId !== null}
            onPageChange={setPage}
            onPerPageChange={(value) => {
              setPerPage(value);
              setPage(1);
            }}
          />
        )}
      </section>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Delete reminder?"
        message={
          deleting
            ? `Delete “${deleting.title}”? This cannot be undone.`
            : ""
        }
        loading={busyId !== null}
        onClose={() => setDeleting(null)}
        onConfirm={remove}
      />
    </div>
  );
}
