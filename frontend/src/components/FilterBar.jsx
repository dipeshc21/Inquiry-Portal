import { Filter, RotateCcw } from "lucide-react";
import {
  PRIORITIES,
  SOURCES,
  STATUSES,
} from "../utils/constants";
import Button from "./Button";
import FormInput from "./FormInput";
import FormSelect from "./FormSelect";

export default function FilterBar({
  filters,
  onChange,
  onReset,
  agents = [],
  canAssign = false,
}) {
  function toggleStatus(status) {
    const selected = filters.status ?? [];

    onChange({
      status: selected.includes(status)
        ? selected.filter((item) => item !== status)
        : [...selected, status],
    });
  }

  return (
    <details className="panel">
      <summary className="flex cursor-pointer list-none items-center gap-2 px-5 py-4 text-sm font-semibold">
        <Filter className="h-4 w-4 text-brand-600" />
        Filter inquiries
      </summary>

      <div className="space-y-5 border-t border-slate-100 p-5">
        <fieldset>
          <legend className="field-label">Status</legend>

          <div className="flex flex-wrap gap-2">
            {STATUSES.map((status) => {
              const selected = (filters.status ?? []).includes(status.value);

              return (
                <button
                  key={status.value}
                  type="button"
                  aria-pressed={selected}
                  onClick={() => toggleStatus(status.value)}
                  className={[
                    "rounded-full border px-3 py-1.5 text-xs font-medium",
                    selected
                      ? "border-brand-200 bg-brand-50 text-brand-700"
                      : "border-slate-200 bg-white text-slate-600",
                  ].join(" ")}
                >
                  {status.label}
                </button>
              );
            })}
          </div>
        </fieldset>

        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
          <FormSelect
            label="Source"
            placeholder="All sources"
            options={SOURCES}
            value={filters.source?.[0] ?? ""}
            onChange={(event) =>
              onChange({
                source: event.target.value ? [event.target.value] : [],
              })
            }
          />

          <FormSelect
            label="Priority"
            placeholder="All priorities"
            options={PRIORITIES}
            value={filters.priority ?? ""}
            onChange={(event) =>
              onChange({ priority: event.target.value })
            }
          />

          {canAssign && (
            <FormSelect
              label="Assigned agent"
              placeholder="All agents"
              options={agents.map((agent) => ({
                value: agent.id,
                label: agent.name,
              }))}
              value={filters.assigned_to ?? ""}
              disabled={Boolean(filters.unassigned)}
              onChange={(event) =>
                onChange({ assigned_to: event.target.value })
              }
            />
          )}

          <FormInput
            label="From"
            type="date"
            value={filters.date_from ?? ""}
            max={filters.date_to || undefined}
            onChange={(event) =>
              onChange({ date_from: event.target.value })
            }
          />

          <FormInput
            label="To"
            type="date"
            value={filters.date_to ?? ""}
            min={filters.date_from || undefined}
            onChange={(event) =>
              onChange({ date_to: event.target.value })
            }
          />
        </div>

        <div className="flex flex-wrap items-center justify-between gap-3">
          {canAssign ? (
            <label className="flex items-center gap-2 text-sm text-slate-600">
              <input
                type="checkbox"
                checked={Boolean(filters.unassigned)}
                onChange={(event) =>
                  onChange({
                    unassigned: event.target.checked,
                    assigned_to: "",
                  })
                }
              />
              Unassigned inquiries only
            </label>
          ) : (
            <span />
          )}

          <Button variant="ghost" size="sm" onClick={onReset}>
            <RotateCcw className="h-4 w-4" />
            Reset filters
          </Button>
        </div>
      </div>
    </details>
  );
}
