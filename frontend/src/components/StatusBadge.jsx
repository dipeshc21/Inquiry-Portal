import { STATUSES, STATUS_CLASSES } from "../utils/constants";
import { labelFor } from "../utils/formatters";

export default function StatusBadge({ status }) {
  return (
    <span
      className={`inline-flex items-center rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${
        STATUS_CLASSES[status] ?? "bg-slate-100 text-slate-600 ring-slate-200"
      }`}
    >
      {labelFor(STATUSES, status)}
    </span>
  );
}
