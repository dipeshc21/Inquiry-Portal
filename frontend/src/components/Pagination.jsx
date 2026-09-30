import { ChevronLeft, ChevronRight } from "lucide-react";
import { PAGE_SIZES } from "../utils/constants";
import Button from "./Button";

export default function Pagination({
  meta = {},
  onPageChange,
  onPerPageChange,
  disabled = false,
}) {
  const current = Number(meta.current_page) || 1;
  const last = Number(meta.last_page) || 1;
  const total = Number(meta.total) || 0;

  return (
    <nav
      aria-label="Table pagination"
      className="flex flex-col gap-4 border-t border-slate-200 px-4 py-4 sm:flex-row sm:items-center sm:justify-between"
    >
      <p className="text-sm text-slate-500" aria-live="polite">
        {total > 0
          ? `${meta.from ?? 0}–${meta.to ?? 0} of ${total}`
          : "0 records"}
      </p>

      <div className="flex flex-wrap items-center gap-3">
        {onPerPageChange && (
          <label className="flex items-center gap-2 text-sm text-slate-600">
            Rows
            <select
              value={Number(meta.per_page) || 15}
              disabled={disabled}
              onChange={(event) =>
                onPerPageChange(Number(event.target.value))
              }
              className="rounded-lg border border-slate-300 bg-white px-2 py-1.5"
            >
              {PAGE_SIZES.map((size) => (
                <option key={size} value={size}>
                  {size}
                </option>
              ))}
            </select>
          </label>
        )}

        <Button
          variant="secondary"
          size="sm"
          aria-label="Previous page"
          disabled={disabled || current <= 1}
          onClick={() => onPageChange(current - 1)}
        >
          <ChevronLeft className="h-4 w-4" />
        </Button>

        <span className="text-sm text-slate-600">
          {current} / {last}
        </span>

        <Button
          variant="secondary"
          size="sm"
          aria-label="Next page"
          disabled={disabled || current >= last}
          onClick={() => onPageChange(current + 1)}
        >
          <ChevronRight className="h-4 w-4" />
        </Button>
      </div>
    </nav>
  );
}
