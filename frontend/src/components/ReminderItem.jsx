import { Check, Clock, Trash2 } from "lucide-react";
import { Link } from "react-router-dom";
import { formatDateTime } from "../utils/formatters";
import Button from "./Button";

export default function ReminderItem({
  reminder,
  onComplete,
  onDelete,
  canManage = true,
  busy = false,
  showInquiry = false,
}) {
  return (
    <article
      className={`rounded-xl border p-4 ${
        reminder.is_completed
          ? "border-slate-200 bg-slate-50"
          : reminder.is_overdue
            ? "border-red-200 bg-red-50/40"
            : "border-slate-200 bg-white"
      }`}
    >
      <div className="flex items-start gap-3">
        <Clock
          aria-hidden="true"
          className={`mt-0.5 h-5 w-5 shrink-0 ${
            reminder.is_overdue ? "text-red-500" : "text-slate-400"
          }`}
        />

        <div className="min-w-0 flex-1">
          <p
            className={`text-sm font-medium ${
              reminder.is_completed ? "text-slate-500 line-through" : ""
            }`}
          >
            {reminder.title}
          </p>

          <p className="mt-1 text-xs text-slate-500">
            {formatDateTime(reminder.remind_at)}
            {reminder.is_completed
              ? " · Completed"
              : reminder.is_overdue
                ? " · Overdue"
                : ""}
          </p>

          {reminder.user?.name && (
            <p className="mt-1 text-xs text-slate-500">
              {reminder.user.name}
            </p>
          )}

          {showInquiry && reminder.inquiry && (
            <Link
              to={`/inquiries/${reminder.inquiry_id}`}
              className="text-link mt-2 inline-block text-xs"
            >
              {reminder.inquiry.reference_no}
              {" · "}
              {reminder.inquiry.subject}
            </Link>
          )}
        </div>
      </div>

      {canManage && (onComplete || onDelete) && (
        <div className="mt-3 flex justify-end gap-2">
          {!reminder.is_completed && onComplete && (
            <Button
              variant="secondary"
              size="sm"
              loading={busy}
              onClick={() => onComplete(reminder)}
            >
              <Check className="h-3.5 w-3.5" />
              Complete
            </Button>
          )}

          {onDelete && (
            <Button
              variant="ghost"
              size="sm"
              disabled={busy}
              aria-label="Delete reminder"
              onClick={() => onDelete(reminder)}
            >
              <Trash2 className="h-4 w-4" />
            </Button>
          )}
        </div>
      )}
    </article>
  );
}
