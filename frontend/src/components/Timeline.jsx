import { Activity } from "lucide-react";
import { formatDateTime } from "../utils/formatters";

export default function Timeline({ items = [] }) {
  if (items.length === 0) {
    return (
      <p className="py-4 text-sm text-slate-500">
        No activity has been recorded yet.
      </p>
    );
  }

  return (
    <ol className="space-y-5">
      {items.map((item) => (
        <li key={item.id} className="flex gap-3">
          <div className="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-50 text-brand-600">
            <Activity className="h-4 w-4" aria-hidden="true" />
          </div>

          <div className="min-w-0 flex-1">
            <p className="text-sm text-slate-800">{item.description}</p>

            <p className="mt-1 text-xs text-slate-500">
              {item.user?.name ?? "System"}
              {" · "}
              {formatDateTime(item.created_at)}
            </p>

            {(item.old_values || item.new_values) && (
              <details className="mt-2 text-xs">
                <summary className="cursor-pointer text-slate-500">
                  View changes
                </summary>

                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                  {[
                    ["Before", item.old_values],
                    ["After", item.new_values],
                  ].map(([label, values]) => (
                    <div key={label} className="min-w-0">
                      <p className="mb-1 font-medium text-slate-600">
                        {label}
                      </p>
                      <pre className="max-h-48 overflow-auto whitespace-pre-wrap break-words rounded-lg bg-slate-50 p-3 text-slate-600">
                        {JSON.stringify(values ?? {}, null, 2)}
                      </pre>
                    </div>
                  ))}
                </div>
              </details>
            )}
          </div>
        </li>
      ))}
    </ol>
  );
}
