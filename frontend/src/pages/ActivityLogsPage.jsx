import { useState } from "react";
import { RefreshCw, RotateCcw } from "lucide-react";
import toast from "react-hot-toast";
import activityApi from "../api/activity";
import Button from "../components/Button";
import ErrorState from "../components/ErrorState";
import FormInput from "../components/FormInput";
import FormSelect from "../components/FormSelect";
import Pagination from "../components/Pagination";
import SearchInput from "../components/SearchInput";
import Spinner from "../components/Spinner";
import Timeline from "../components/Timeline";
import useDebounce from "../hooks/useDebounce";
import useFetch from "../hooks/useFetch";

const actions = [
  ["created", "Inquiry created"],
  ["updated", "Inquiry updated"],
  ["status_changed", "Status changed"],
  ["assigned", "Assignment changed"],
  ["deleted", "Inquiry deleted"],
  ["message_added", "Message added"],
  ["note_added", "Note added"],
  ["note_updated", "Note updated"],
  ["note_deleted", "Note deleted"],
  ["reminder_added", "Reminder added"],
  ["reminder_completed", "Reminder completed"],
  ["reminder_deleted", "Reminder deleted"],
  ["reminder_notified", "Reminder notification sent"],
  ["attachment_uploaded", "Attachment uploaded"],
  ["attachment_deleted", "Attachment deleted"],
  ["user_created", "User created"],
  ["user_updated", "User updated"],
  ["user_deleted", "User deleted"],
  ["team_created", "Team created"],
  ["team_updated", "Team updated"],
  ["team_deleted", "Team deleted"],
  ["settings_updated", "Settings updated"],
  ["demo_seeded", "Demo data initialized"],
].map(([value, label]) => ({ value, label }));

const emptyFilters = {
  action: "",
  inquiry_id: "",
  user_id: "",
  date_from: "",
  date_to: "",
};

export default function ActivityLogsPage() {
  const [search, setSearch] = useState("");
  const query = useDebounce(search, 400);
  const [filters, setFilters] = useState(emptyFilters);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const filterKey = JSON.stringify(filters);

  const list = useFetch(
    (signal) =>
      activityApi.list(
        { ...filters, q: query, page, per_page: perPage },
        signal,
      ),
    [query, filterKey, page, perPage],
  );

  function change(field, value) {
    setFilters((current) => ({ ...current, [field]: value }));
    setPage(1);
  }

  return (
    <div className="page-container">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="page-title">Activity log</h1>
          <p className="page-description">
            Review inquiry changes and administrative actions.
          </p>
        </div>

        <Button
          variant="secondary"
          loading={list.loading}
          onClick={async () => {
            if (await list.refresh()) toast.success("Activity log refreshed.");
          }}
        >
          <RefreshCw className="h-4 w-4" />
          Refresh
        </Button>
      </header>

      <section className="panel space-y-4 p-5">
        <SearchInput
          value={search}
          onChange={(value) => {
            setSearch(value);
            setPage(1);
          }}
          placeholder="Search activity descriptions"
          className="max-w-lg"
        />

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <FormSelect
            label="Action"
            placeholder="All actions"
            options={actions}
            value={filters.action}
            onChange={(event) => change("action", event.target.value)}
          />
          <FormInput
            label="Inquiry ID"
            type="number"
            min="1"
            step="1"
            value={filters.inquiry_id}
            onChange={(event) => change("inquiry_id", event.target.value)}
          />
          <FormInput
            label="User ID"
            type="number"
            min="1"
            step="1"
            value={filters.user_id}
            onChange={(event) => change("user_id", event.target.value)}
          />
          <FormInput
            label="From"
            type="date"
            max={filters.date_to || undefined}
            value={filters.date_from}
            onChange={(event) => change("date_from", event.target.value)}
          />
          <FormInput
            label="To"
            type="date"
            min={filters.date_from || undefined}
            value={filters.date_to}
            onChange={(event) => change("date_to", event.target.value)}
          />
        </div>

        <div className="flex justify-end">
          <Button
            variant="ghost"
            size="sm"
            onClick={() => {
              setSearch("");
              setFilters({ ...emptyFilters });
              setPage(1);
            }}
          >
            <RotateCcw className="h-4 w-4" />
            Reset filters
          </Button>
        </div>
      </section>

      <section className="panel overflow-hidden">
        <div className="p-5">
          {list.error ? (
            <ErrorState error={list.error} onRetry={list.refresh} />
          ) : list.loading ? (
            <div className="flex justify-center py-14 text-brand-600">
              <Spinner label="Loading activity" />
            </div>
          ) : (
            <Timeline items={list.data ?? []} />
          )}
        </div>

        {!list.error && (
          <Pagination
            meta={{ current_page: page, per_page: perPage, ...list.meta }}
            disabled={list.loading}
            onPageChange={setPage}
            onPerPageChange={(value) => {
              setPerPage(value);
              setPage(1);
            }}
          />
        )}
      </section>
    </div>
  );
}
