import { ArrowDown, ArrowUp, ArrowUpDown, Inbox } from "lucide-react";

export default function DataTable({
  columns,
  rows = [],
  loading = false,
  rowKey = "id",
  sortBy,
  sortDir = "desc",
  onSort,
  selectedIds = [],
  onSelectionChange,
  emptyTitle = "No records found",
  emptyDescription = "Try adjusting your search or filters.",
}) {
  const selectable = Boolean(onSelectionChange);
  const pageIds = rows.map((row) => row[rowKey]);
  const allSelected =
    pageIds.length > 0 &&
    pageIds.every((id) => selectedIds.includes(id));

  const someSelected =
    !allSelected && pageIds.some((id) => selectedIds.includes(id));

  function toggleAll() {
    if (allSelected) {
      onSelectionChange(
        selectedIds.filter((id) => !pageIds.includes(id)),
      );
    } else {
      onSelectionChange([...new Set([...selectedIds, ...pageIds])]);
    }
  }

  function toggleRow(id) {
    onSelectionChange(
      selectedIds.includes(id)
        ? selectedIds.filter((selected) => selected !== id)
        : [...selectedIds, id],
    );
  }

  return (
    <div className="overflow-x-auto" aria-busy={loading}>
      <table className="w-full border-collapse">
        <thead className="border-b border-slate-200 bg-slate-50">
          <tr>
            {selectable && (
              <th className="w-12 px-4 py-3">
                <input
                  type="checkbox"
                  aria-label="Select all rows on this page"
                  checked={allSelected}
                  disabled={loading || rows.length === 0}
                  ref={(element) => {
                    if (element) element.indeterminate = someSelected;
                  }}
                  onChange={toggleAll}
                />
              </th>
            )}

            {columns.map((column) => {
              const active = sortBy === column.key;
              const SortIcon = !active
                ? ArrowUpDown
                : sortDir === "asc"
                  ? ArrowUp
                  : ArrowDown;

              return (
                <th
                  key={column.key}
                  scope="col"
                  aria-sort={
                    column.sortable
                      ? active
                        ? sortDir === "asc"
                          ? "ascending"
                          : "descending"
                        : "none"
                      : undefined
                  }
                  className={`table-cell whitespace-nowrap font-semibold text-slate-600 ${column.className ?? ""}`}
                >
                  {column.sortable && onSort ? (
                    <button
                      type="button"
                      onClick={() =>
                        onSort(
                          column.key,
                          active && sortDir === "asc" ? "desc" : "asc",
                        )
                      }
                      className="inline-flex items-center gap-2 rounded"
                    >
                      {column.label}
                      <SortIcon className="h-3.5 w-3.5" />
                    </button>
                  ) : (
                    column.label
                  )}
                </th>
              );
            })}
          </tr>
        </thead>

        <tbody className="divide-y divide-slate-100">
          {loading
            ? Array.from({ length: 5 }, (_, index) => (
                <tr key={`skeleton-${index}`} aria-hidden="true">
                  {selectable && (
                    <td className="table-cell">
                      <div className="h-4 w-4 animate-pulse rounded bg-slate-100" />
                    </td>
                  )}

                  {columns.map((column) => (
                    <td key={column.key} className="table-cell">
                      <div className="h-5 min-w-16 animate-pulse rounded bg-slate-100" />
                    </td>
                  ))}
                </tr>
              ))
            : rows.map((row) => (
                <tr
                  key={row[rowKey]}
                  className={
                    selectedIds.includes(row[rowKey])
                      ? "bg-brand-50/60"
                      : "hover:bg-slate-50/70"
                  }
                >
                  {selectable && (
                    <td className="table-cell">
                      <input
                        type="checkbox"
                        aria-label={`Select row ${row[rowKey]}`}
                        checked={selectedIds.includes(row[rowKey])}
                        onChange={() => toggleRow(row[rowKey])}
                      />
                    </td>
                  )}

                  {columns.map((column) => (
                    <td
                      key={column.key}
                      className={`table-cell ${column.className ?? ""}`}
                    >
                      {column.render
                        ? column.render(row)
                        : (row[column.key] ?? "—")}
                    </td>
                  ))}
                </tr>
              ))}

          {!loading && rows.length === 0 && (
            <tr>
              <td
                colSpan={columns.length + (selectable ? 1 : 0)}
                className="px-6 py-14 text-center"
              >
                <Inbox className="mx-auto mb-3 h-9 w-9 text-slate-300" />
                <p className="font-medium text-slate-700">{emptyTitle}</p>
                <p className="mt-1 text-sm text-slate-500">
                  {emptyDescription}
                </p>
              </td>
            </tr>
          )}
        </tbody>
      </table>
    </div>
  );
}
