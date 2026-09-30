import { PRIORITIES, PRIORITY_CLASSES } from "../utils/constants";
import { labelFor } from "../utils/formatters";

export default function PriorityBadge({ priority }) {
  return (
    <span
      className={`inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium ring-1 ring-inset ${
        PRIORITY_CLASSES[priority] ?? PRIORITY_CLASSES.low
      }`}
    >
      <span
        aria-hidden="true"
        className="h-1.5 w-1.5 rounded-full bg-current"
      />
      {labelFor(PRIORITIES, priority)}
    </span>
  );
}
