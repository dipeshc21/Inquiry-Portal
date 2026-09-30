import { useEffect, useMemo, useRef, useState } from "react";
import { Download, ExternalLink, RefreshCw } from "lucide-react";
import { Link, useSearchParams } from "react-router-dom";
import toast from "react-hot-toast";
import inquiriesApi from "../api/inquiries";
import usersApi from "../api/users";
import Button from "../components/Button";
import DataTable from "../components/DataTable";
import ErrorState from "../components/ErrorState";
import FilterBar from "../components/FilterBar";
import Pagination from "../components/Pagination";
import PriorityBadge from "../components/PriorityBadge";
import SearchInput from "../components/SearchInput";
import useAuth from "../hooks/useAuth";
import useDebounce from "../hooks/useDebounce";
import useFetch from "../hooks/useFetch";
import {
  PAGE_SIZES,
  PRIORITIES,
  SOURCES,
  STATUSES,
} from "../utils/constants";
import { errorMessage } from "../utils/errors";
import {
  downloadBlob,
  formatDate,
  labelFor,
} from "../utils/formatters";

function readFilters(params) {
  const readList = (key, options) => {
    const supplied = [
      ...params.getAll(key),
      ...params.getAll(`${key}[]`),
    ].flatMap((value) => value.split(","));

    return [...new Set(supplied)].filter((value) =>
      options.some((option) => option.value === value),
    );
  };

  const requestedPage = Number(params.get("page"));
  const requestedSize = Number(params.get("per_page"));
  const sortBy = params.get("sort_by") ?? "created_at";
  const priority = params.get("priority") ?? "";

  return {
    q: params.get("q") ?? "",
    status: readList("status", STATUSES),
    source: readList("source", SOURCES),
    priority: PRIORITIES.some((item) => item.value === priority)
      ? priority
      : "",
    assigned_to: params.get("assigned_to") ?? "",
    unassigned: ["true", "1"].includes(params.get("unassigned")),
    date_from: params.get("date_from") ?? "",
    date_to: params.get("date_to") ?? "",
    page:
      Number.isInteger(requestedPage) && requestedPage > 0
        ? requestedPage
        : 1,
    per_page: PAGE_SIZES.includes(requestedSize) ? requestedSize : 15,
    sort_by: ["created_at", "name", "status", "priority"].includes(sortBy)
      ? sortBy
      : "created_at",
    sort_dir: params.get("sort_dir") === "asc" ? "asc" : "desc",
  };
}

function writeFilters(filters) {
  const params = new URLSearchParams();

  Object.entries(filters).forEach(([key, value]) => {
    if (
      value === "" ||
      value === null ||
      value === undefined ||
      value === false
    ) {
      return;
    }

    if (Array.isArray(value)) {
      value.forEach((item) => params.append(key, item));
    } else {
      params.set(key, String(value));
    }
  });

  return params;
}

export default function InquiriesPage() {
  const { canManageInquiries } = useAuth();
  const [searchParams, setSearchParams] = useSearchParams();

  const queryKey = searchParams.toString();

  const filters = useMemo(
    () => readFilters(new URLSearchParams(queryKey)),
    [queryKey],
  );

  const [search, setSearch] = useState(filters.q);
  const debouncedSearch = useDebounce(search, 400);

  const committedSearch = useRef(filters.q);
  const currentQuery = useRef(queryKey);
  currentQuery.current = queryKey;

  const [selectedIds, setSelectedIds] = useState([]);
  const [exporting, setExporting] = useState(false);
  const [busyIds, setBusyIds] = useState([]);
  const activeStatusRequests = useRef(new Set());

  const list = useFetch(
    (signal) => inquiriesApi.list(filters, signal),
    [queryKey],
  );

  const agents = useFetch(
    (signal) => usersApi.assignable(signal),
    [],
    { enabled: canManageInquiries },
  );

  function changeFilters(patch, replace = false) {
    setSearchParams(
      (current) =>
        writeFilters({
          ...readFilters(current),
          ...patch,
        }),
      { replace },
    );
  }

  useEffect(() => {
    if (filters.q !== committedSearch.current) {
      committedSearch.current = filters.q;
      setSearch(filters.q);
    }
  }, [filters.q]);

  useEffect(() => {
    const next = debouncedSearch.trim();

    if (next !== committedSearch.current) {
      committedSearch.current = next;

      setSearchParams(
        (current) =>
          writeFilters({
            ...readFilters(current),
            q: next,
            page: 1,
          }),
        { replace: true },
      );
    }
  }, [debouncedSearch, setSearchParams]);

  useEffect(() => {
    setSelectedIds([]);
  }, [queryKey]);

  function resetFilters() {
    committedSearch.current = "";
    setSearch("");
    setSearchParams(
      writeFilters({
        page: 1,
        per_page: filters.per_page,
        sort_by: "created_at",
        sort_dir: "desc",
      }),
    );
  }

  async function changeStatus(row, status) {
    if (
      status === row.status ||
      activeStatusRequests.current.has(row.id)
    ) {
      return;
    }

    const previousStatus = row.status;
    const requestQuery = currentQuery.current;

    activeStatusRequests.current.add(row.id);
    setBusyIds([...activeStatusRequests.current]);

    list.setData((rows) =>
      rows?.map((item) =>
        item.id === row.id ? { ...item, status } : item,
      ),
    );

    try {
      await inquiriesApi.status(row.id, status);
      toast.success("Status updated.");

      if (currentQuery.current === requestQuery) {
        await list.refresh();
      }
    } catch (error) {
      if (currentQuery.current === requestQuery) {
        list.setData((rows) =>
          rows?.map((item) =>
            item.id === row.id && item.status === status
              ? { ...item, status: previousStatus }
              : item,
          ),
        );
      }

      toast.error(errorMessage(error));
    } finally {
      activeStatusRequests.current.delete(row.id);
      setBusyIds([...activeStatusRequests.current]);
    }
  }

  async function exportCsv() {
    setExporting(true);

    try {
      const { page, per_page, ...exportFilters } = filters;

      const response = await inquiriesApi.export({
        ...exportFilters,
        ...(selectedIds.length ? { ids: selectedIds } : {}),
      });

      downloadBlob(response, "inquiries.csv");
      toast.success("CSV exported.");
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setExporting(false);
    }
  }

  const columns = [
    {
      key: "reference_no",
      label: "Reference",
      render: (row) => (
        <Link
          to={`/inquiries/${row.id}`}
          className="text-link whitespace-nowrap"
        >
          {row.reference_no}
        </Link>
      ),
    },
    {
      key: "name",
      label: "Customer",
      sortable: true,
      render: (row) => (
        <div className="min-w-40">
          <p className="font-medium">{row.name}</p>
          <p className="mt-0.5 text-xs text-slate-500">{row.email}</p>
          {row.company && (
            <p className="mt-0.5 text-xs text-slate-400">{row.company}</p>
          )}
        </div>
      ),
    },
    {
      key: "subject",
      label: "Subject",
      render: (row) => (
        <p className="max-w-56 truncate" title={row.subject}>
          {row.subject}
        </p>
      ),
    },
    {
      key: "source",
      label: "Source",
      render: (row) => (
        <span className="whitespace-nowrap text-slate-600">
          {labelFor(SOURCES, row.source)}
        </span>
      ),
    },
    {
      key: "status",
      label: "Status",
      sortable: true,
      render: (row) => (
        <select
          value={row.status}
          disabled={busyIds.includes(row.id)}
          aria-label={`Status for ${row.reference_no}`}
          onChange={(event) => changeStatus(row, event.target.value)}
          className="min-w-28 rounded-lg border border-slate-200 bg-white px-2 py-2 text-xs disabled:opacity-50"
        >
          {STATUSES.map((status) => (
            <option key={status.value} value={status.value}>
              {status.label}
            </option>
          ))}
        </select>
      ),
    },
    {
      key: "priority",
      label: "Priority",
      sortable: true,
      render: (row) => <PriorityBadge priority={row.priority} />,
    },
    {
      key: "assignee",
      label: "Assignee",
      render: (row) => (
        <span className="whitespace-nowrap text-slate-600">
          {row.assignee?.name ?? "Unassigned"}
        </span>
      ),
    },
    {
      key: "created_at",
      label: "Received",
      sortable: true,
      render: (row) => (
        <span className="whitespace-nowrap text-slate-500">
          {formatDate(row.created_at)}
        </span>
      ),
    },
  ];

  return (
    <div className="page-container">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="page-title">Inquiries</h1>
          <p className="page-description">
            Search customer requests and keep every conversation moving.
          </p>
        </div>

        <div className="flex flex-wrap gap-2">
          <Link
            to="/inquiry"
            target="_blank"
            rel="noopener noreferrer"
            className="inline-flex items-center gap-2 rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50"
          >
            Public form
            <ExternalLink className="h-4 w-4" />
          </Link>

          {canManageInquiries && (
            <Button loading={exporting} onClick={exportCsv}>
              <Download className="h-4 w-4" />
              {selectedIds.length
                ? `Export ${selectedIds.length} selected`
                : "Export CSV"}
            </Button>
          )}
        </div>
      </header>

      {agents.error && (
        <ErrorState error={agents.error} onRetry={agents.refresh} />
      )}

      <FilterBar
        filters={filters}
        agents={agents.data ?? []}
        canAssign={canManageInquiries}
        onChange={(patch) => changeFilters({ ...patch, page: 1 })}
        onReset={resetFilters}
      />

      <section className="panel overflow-hidden">
        <div className="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 p-4">
          <SearchInput
            value={search}
            onChange={setSearch}
            placeholder="Search name, email, phone, subject, or reference"
            className="w-full sm:max-w-lg"
          />

          <Button
            variant="secondary"
            loading={list.loading}
            onClick={async () => {
              const result = await list.refresh();
              if (result) toast.success("Inquiries refreshed.");
            }}
          >
            <RefreshCw className="h-4 w-4" />
            Refresh
          </Button>
        </div>

        {list.error ? (
          <div className="p-5">
            <ErrorState error={list.error} onRetry={list.refresh} />
          </div>
        ) : (
          <>
            <DataTable
              columns={columns}
              rows={list.data ?? []}
              loading={list.loading}
              sortBy={filters.sort_by}
              sortDir={filters.sort_dir}
              onSort={(sort_by, sort_dir) =>
                changeFilters({ sort_by, sort_dir, page: 1 })
              }
              selectedIds={selectedIds}
              onSelectionChange={
                canManageInquiries ? setSelectedIds : undefined
              }
              emptyTitle="No matching inquiries"
              emptyDescription="Change your search or filters to see more results."
            />

            <Pagination
              meta={{
                current_page: filters.page,
                per_page: filters.per_page,
                ...list.meta,
              }}
              disabled={list.loading}
              onPageChange={(page) => changeFilters({ page })}
              onPerPageChange={(per_page) =>
                changeFilters({ per_page, page: 1 })
              }
            />
          </>
        )}
      </section>
    </div>
  );
}
