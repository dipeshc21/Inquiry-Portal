import { LockKeyhole, Pencil, Trash2 } from "lucide-react";
import { formatDateTime } from "../utils/formatters";

export default function NoteCard({
  note,
  canEdit = false,
  onEdit,
  onDelete,
  disabled = false,
}) {
  return (
    <article className="rounded-xl border border-amber-100 bg-amber-50/50 p-4">
      <div className="flex items-start justify-between gap-3">
        <div>
          <p className="text-sm font-medium">
            {note.user?.name ?? "Staff member"}
          </p>
          <p className="mt-0.5 text-xs text-slate-500">
            {formatDateTime(note.created_at)}
          </p>
        </div>

        {canEdit && (
          <div className="flex gap-1">
            <button
              type="button"
              disabled={disabled}
              onClick={() => onEdit(note)}
              aria-label="Edit note"
              className="rounded p-2 text-slate-500 hover:bg-white"
            >
              <Pencil className="h-4 w-4" />
            </button>
            <button
              type="button"
              disabled={disabled}
              onClick={() => onDelete(note)}
              aria-label="Delete note"
              className="rounded p-2 text-slate-500 hover:bg-red-50 hover:text-red-600"
            >
              <Trash2 className="h-4 w-4" />
            </button>
          </div>
        )}
      </div>

      <p className="mt-3 whitespace-pre-wrap break-words text-sm leading-6 text-slate-700">
        {note.body}
      </p>

      <span className="mt-3 inline-flex items-center gap-1 text-xs text-amber-700">
        <LockKeyhole className="h-3 w-3" />
        Internal note
      </span>
    </article>
  );
}
