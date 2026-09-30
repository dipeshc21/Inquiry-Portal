import { useEffect, useRef, useState } from "react";
import {
  ArrowLeft,
  Download,
  FileText,
  Pencil,
  Plus,
  Send,
  Trash2,
  Upload,
} from "lucide-react";
import { Link, useNavigate, useParams } from "react-router-dom";
import toast from "react-hot-toast";
import attachmentsApi from "../api/attachments";
import inquiriesApi from "../api/inquiries";
import messagesApi from "../api/messages";
import notesApi from "../api/notes";
import remindersApi from "../api/reminders";
import usersApi from "../api/users";
import Button from "../components/Button";
import ConfirmDialog from "../components/ConfirmDialog";
import DetailPanel from "../components/DetailPanel";
import ErrorState from "../components/ErrorState";
import FileUpload from "../components/FileUpload";
import FormInput from "../components/FormInput";
import FormSelect from "../components/FormSelect";
import FormTextarea from "../components/FormTextarea";
import Modal from "../components/Modal";
import NoteCard from "../components/NoteCard";
import PriorityBadge from "../components/PriorityBadge";
import ReminderItem from "../components/ReminderItem";
import Spinner from "../components/Spinner";
import StatusBadge from "../components/StatusBadge";
import Timeline from "../components/Timeline";
import useAuth from "../hooks/useAuth";
import useFetch from "../hooks/useFetch";
import { PRIORITIES, SOURCES, STATUSES } from "../utils/constants";
import { errorMessage, fieldErrors } from "../utils/errors";
import {
  downloadBlob,
  formatBytes,
  formatDateTime,
  labelFor,
  toLocalDateTimeInput,
} from "../utils/formatters";
import {
  validateFiles,
  validateReminder,
} from "../utils/validation";

function ContactField({ label, children }) {
  return (
    <div>
      <dt className="text-xs font-medium text-slate-400">{label}</dt>
      <dd className="mt-1 break-words text-sm text-slate-700">
        {children || "—"}
      </dd>
    </div>
  );
}

export default function InquiryDetailPage() {
  const { id } = useParams();
  const navigate = useNavigate();
  const { user, isAdmin, canManageInquiries } = useAuth();

  const detail = useFetch(
    (signal) => inquiriesApi.get(id, signal),
    [id],
  );

  const agents = useFetch(
    (signal) => usersApi.assignable(signal),
    [],
    { enabled: canManageInquiries },
  );

  const inquiry = detail.data;
  const currentId = useRef(id);
  currentId.current = id;

  const [busyAction, setBusyAction] = useState("");
  const actionLock = useRef(false);

  const [reply, setReply] = useState("");
  const [replyErrors, setReplyErrors] = useState({});

  const [editOpen, setEditOpen] = useState(false);
  const [editValues, setEditValues] = useState({});
  const [editErrors, setEditErrors] = useState({});

  const [noteOpen, setNoteOpen] = useState(false);
  const [editingNote, setEditingNote] = useState(null);
  const [noteBody, setNoteBody] = useState("");
  const [noteErrors, setNoteErrors] = useState({});

  const [reminderOpen, setReminderOpen] = useState(false);
  const [reminderValues, setReminderValues] = useState({
    title: "",
    remind_at: "",
  });
  const [reminderErrors, setReminderErrors] = useState({});

  const [files, setFiles] = useState([]);
  const [uploadErrors, setUploadErrors] = useState({});
  const [confirmation, setConfirmation] = useState(null);

  useEffect(() => {
    setReply("");
    setReplyErrors({});
    setFiles([]);
    setUploadErrors({});
    setConfirmation(null);
    setEditOpen(false);
    setNoteOpen(false);
    setReminderOpen(false);
  }, [id]);

  async function perform(
    key,
    action,
    successMessage,
    { onSuccess, onError, refresh = true } = {},
  ) {
    if (actionLock.current) return false;

    actionLock.current = true;
    setBusyAction(key);
    const startedForId = id;

    try {
      await action();

      if (currentId.current === startedForId) {
        onSuccess?.();
        toast.success(successMessage);

        if (refresh) await detail.refresh();
      }

      return true;
    } catch (error) {
      if (currentId.current === startedForId) {
        onError?.(error);
        toast.error(errorMessage(error));
      }

      return false;
    } finally {
      actionLock.current = false;
      setBusyAction("");
    }
  }

  async function changeStatus(status) {
    if (!inquiry || status === inquiry.status || actionLock.current) return;

    const previousStatus = inquiry.status;
    const previousClosedAt = inquiry.closed_at;

    detail.setData((current) => ({
      ...current,
      status,
      closed_at: ["won", "lost"].includes(status)
        ? current.closed_at ?? new Date().toISOString()
        : null,
    }));

    await perform(
      "status",
      () => inquiriesApi.status(id, status),
      "Status updated.",
      {
        onError: () => {
          detail.setData((current) =>
            current?.status === status
              ? {
                  ...current,
                  status: previousStatus,
                  closed_at: previousClosedAt,
                }
              : current,
          );
        },
      },
    );
  }

  function openEdit() {
    setEditValues({
      name: inquiry.name,
      email: inquiry.email,
      phone: inquiry.phone ?? "",
      company: inquiry.company ?? "",
      subject: inquiry.subject,
      source: inquiry.source,
      priority: inquiry.priority,
      utm_source: inquiry.utm_source ?? "",
      utm_medium: inquiry.utm_medium ?? "",
      utm_campaign: inquiry.utm_campaign ?? "",
    });

    setEditErrors({});
    setEditOpen(true);
  }

  function changeEdit(field, value) {
    setEditValues((current) => ({ ...current, [field]: value }));
    setEditErrors((current) => ({ ...current, [field]: undefined }));
  }

  async function saveEdit(event) {
    event.preventDefault();
    setEditErrors({});

    await perform(
      "edit",
      () => inquiriesApi.update(id, editValues),
      "Inquiry details updated.",
      {
        onSuccess: () => setEditOpen(false),
        onError: (error) => setEditErrors(fieldErrors(error)),
      },
    );
  }

  function openNote(note = null) {
    setEditingNote(note);
    setNoteBody(note?.body ?? "");
    setNoteErrors({});
    setNoteOpen(true);
  }

  async function saveNote(event) {
    event.preventDefault();

    if (!noteBody.trim()) {
      setNoteErrors({ body: "Enter a note." });
      return;
    }

    setNoteErrors({});

    await perform(
      "note",
      () =>
        editingNote
          ? notesApi.update(editingNote.id, noteBody)
          : notesApi.create(id, noteBody),
      editingNote ? "Note updated." : "Note added.",
      {
        onSuccess: () => {
          setNoteOpen(false);
          setNoteBody("");
        },
        onError: (error) => setNoteErrors(fieldErrors(error)),
      },
    );
  }

  async function sendReply(event) {
    event.preventDefault();

    if (!reply.trim()) {
      setReplyErrors({ body: "Enter a reply." });
      return;
    }

    setReplyErrors({});

    await perform(
      "reply",
      () => messagesApi.create(id, reply),
      "Reply saved and queued for email delivery.",
      {
        onSuccess: () => setReply(""),
        onError: (error) => setReplyErrors(fieldErrors(error)),
      },
    );
  }

  function openReminder() {
    setReminderValues({
      title: "",
      remind_at: toLocalDateTimeInput(Date.now() + 24 * 60 * 60 * 1000),
    });
    setReminderErrors({});
    setReminderOpen(true);
  }

  async function saveReminder(event) {
    event.preventDefault();

    const errors = validateReminder(reminderValues);
    setReminderErrors(errors);

    if (Object.keys(errors).length) return;

    await perform(
      "reminder",
      () => remindersApi.create(id, reminderValues),
      "Reminder created.",
      {
        onSuccess: () => setReminderOpen(false),
        onError: (error) => setReminderErrors(fieldErrors(error)),
      },
    );
  }

  async function uploadFiles() {
    const errors = validateFiles(files);

    if (files.length === 0) errors.files = "Select at least one file.";

    setUploadErrors(errors);
    if (Object.keys(errors).length) return;

    await perform(
      "upload",
      () => attachmentsApi.upload(id, files),
      "Attachments uploaded.",
      {
        onSuccess: () => setFiles([]),
        onError: (error) => setUploadErrors(fieldErrors(error)),
      },
    );
  }

  async function downloadAttachment(attachment) {
    try {
      const response = await attachmentsApi.download(attachment.id);
      downloadBlob(response, attachment.original_name);
      toast.success("Download started.");
    } catch (error) {
      toast.error(errorMessage(error));
    }
  }

  function askDelete(type, record) {
    const labels = {
      inquiry: "inquiry",
      note: "note",
      reminder: "reminder",
      attachment: "attachment",
    };

    setConfirmation({
      type,
      record,
      title: `Delete ${labels[type]}?`,
      message:
        type === "inquiry"
          ? `Delete ${inquiry.reference_no}? It will be removed from active inquiries.`
          : `This ${labels[type]} will be deleted. This action cannot be undone.`,
    });
  }

  async function confirmDelete() {
    if (!confirmation) return;

    const { type, record } = confirmation;

    const actions = {
      inquiry: () => inquiriesApi.remove(id),
      note: () => notesApi.remove(record.id),
      reminder: () => remindersApi.remove(record.id),
      attachment: () => attachmentsApi.remove(record.id),
    };

    await perform(
      "delete",
      actions[type],
      `${type[0].toUpperCase()}${type.slice(1)} deleted.`,
      {
        refresh: type !== "inquiry",
        onSuccess: () => {
          setConfirmation(null);

          if (type === "inquiry") {
            navigate("/inquiries", { replace: true });
          }
        },
      },
    );
  }

  if (detail.loading && !inquiry) {
    return (
      <div className="flex justify-center py-24 text-brand-600">
        <Spinner label="Loading inquiry" />
      </div>
    );
  }

  if (detail.error) {
    return (
      <div className="page-container">
        <Link to="/inquiries" className="text-link inline-flex items-center gap-2">
          <ArrowLeft className="h-4 w-4" />
          Back to inquiries
        </Link>
        <ErrorState error={detail.error} onRetry={detail.refresh} />
      </div>
    );
  }

  if (!inquiry) return null;

  const disabled = Boolean(busyAction) || detail.loading;

  const agentOptions = (agents.data ?? []).map((agent) => ({
    value: agent.id,
    label: agent.name,
  }));

  if (
    inquiry.assignee &&
    !agentOptions.some(
      (option) => Number(option.value) === Number(inquiry.assigned_to),
    )
  ) {
    agentOptions.push({
      value: inquiry.assigned_to,
      label: `${inquiry.assignee.name} (currently assigned)`,
      disabled: true,
    });
  }

  return (
    <div className="page-container">
      <Link
        to="/inquiries"
        className="text-link inline-flex items-center gap-2 text-sm"
      >
        <ArrowLeft className="h-4 w-4" />
        Back to inquiries
      </Link>

      <header className="flex flex-wrap items-start justify-between gap-4">
        <div className="min-w-0">
          <p className="font-mono text-sm text-brand-600">
            {inquiry.reference_no}
          </p>
          <h1 className="page-title mt-2 break-words">{inquiry.subject}</h1>

          <div className="mt-3 flex flex-wrap items-center gap-3">
            <StatusBadge status={inquiry.status} />
            <PriorityBadge priority={inquiry.priority} />
            <span className="text-xs text-slate-500">
              Received {formatDateTime(inquiry.created_at)}
            </span>
          </div>
        </div>

        <div className="flex gap-2">
          <Button variant="secondary" disabled={disabled} onClick={openEdit}>
            <Pencil className="h-4 w-4" />
            Edit details
          </Button>

          {isAdmin && (
            <Button
              variant="danger"
              disabled={disabled}
              onClick={() => askDelete("inquiry", inquiry)}
            >
              <Trash2 className="h-4 w-4" />
              Delete
            </Button>
          )}
        </div>
      </header>

      <div className="grid items-start gap-6 xl:grid-cols-[320px_minmax(0,1fr)]">
        <aside className="space-y-6">
          <DetailPanel title="Contact information">
            <dl className="space-y-4">
              <ContactField label="Name">{inquiry.name}</ContactField>
              <ContactField label="Email">{inquiry.email}</ContactField>
              <ContactField label="Phone">{inquiry.phone}</ContactField>
              <ContactField label="Company">{inquiry.company}</ContactField>
              <ContactField label="Source">
                {labelFor(SOURCES, inquiry.source)}
              </ContactField>
              <ContactField label="Last updated">
                {formatDateTime(inquiry.updated_at)}
              </ContactField>
              {inquiry.closed_at && (
                <ContactField label="Closed">
                  {formatDateTime(inquiry.closed_at)}
                </ContactField>
              )}
            </dl>
          </DetailPanel>

          <DetailPanel title="Inquiry management">
            <div className="space-y-4">
              <FormSelect
                label="Status"
                options={STATUSES}
                value={inquiry.status}
                disabled={disabled}
                onChange={(event) => changeStatus(event.target.value)}
              />

              <FormSelect
                label="Priority"
                options={PRIORITIES}
                value={inquiry.priority}
                disabled={disabled}
                onChange={(event) => {
                  const priority = event.target.value;

                  perform(
                    "priority",
                    () => inquiriesApi.update(id, { priority }),
                    "Priority updated.",
                  );
                }}
              />

              {canManageInquiries ? (
                <>
                  <FormSelect
                    label="Assigned agent"
                    placeholder="Unassigned"
                    options={agentOptions}
                    value={inquiry.assigned_to ?? ""}
                    disabled={disabled || agents.loading}
                    onChange={(event) => {
                      const assignedTo = event.target.value;

                      perform(
                        "assign",
                        () => inquiriesApi.assign(id, assignedTo),
                        "Assignment updated.",
                      );
                    }}
                  />

                  {agents.error && (
                    <ErrorState
                      error={agents.error}
                      onRetry={agents.refresh}
                    />
                  )}
                </>
              ) : (
                <div>
                  <p className="field-label">Assigned agent</p>
                  <p className="text-sm text-slate-600">
                    {inquiry.assignee?.name ?? "Unassigned"}
                  </p>
                </div>
              )}
            </div>
          </DetailPanel>

          <DetailPanel title="Source tracking">
            <dl className="space-y-4">
              <ContactField label="UTM source">
                {inquiry.utm_source}
              </ContactField>
              <ContactField label="UTM medium">
                {inquiry.utm_medium}
              </ContactField>
              <ContactField label="UTM campaign">
                {inquiry.utm_campaign}
              </ContactField>
            </dl>
          </DetailPanel>
        </aside>

        <div className="min-w-0 space-y-6">
          <DetailPanel
            title="Conversation"
            description="Customer messages and staff replies"
          >
            <div className="space-y-4">
              {(inquiry.messages ?? []).length === 0 && (
                <p className="text-sm text-slate-500">No messages yet.</p>
              )}

              {(inquiry.messages ?? []).map((message) => (
                <article
                  key={message.id}
                  className={`rounded-xl p-4 ${
                    message.sender_type === "staff"
                      ? "border border-brand-100 bg-brand-50/50"
                      : "border border-slate-200 bg-slate-50"
                  }`}
                >
                  <div className="flex flex-wrap items-center justify-between gap-2">
                    <p className="text-sm font-semibold">
                      {message.sender_type === "staff"
                        ? message.user?.name ?? "Staff member"
                        : inquiry.name}
                      <span className="ml-2 text-xs font-normal text-slate-400">
                        {message.sender_type === "staff" ? "Staff" : "Customer"}
                      </span>
                    </p>
                    <time className="text-xs text-slate-400">
                      {formatDateTime(message.created_at)}
                    </time>
                  </div>

                  <p className="mt-3 whitespace-pre-wrap break-words text-sm leading-7 text-slate-700">
                    {message.body}
                  </p>
                </article>
              ))}
            </div>

            <form
              noValidate
              onSubmit={sendReply}
              className="mt-6 border-t border-slate-100 pt-5"
            >
              <FormTextarea
                label="Reply to customer"
                rows={4}
                maxLength={10000}
                value={reply}
                disabled={disabled}
                error={replyErrors.body}
                help={`Your reply will be emailed to ${inquiry.email}.`}
                onChange={(event) => setReply(event.target.value)}
              />

              <div className="mt-3 flex justify-end">
                <Button
                  type="submit"
                  loading={busyAction === "reply"}
                  disabled={disabled}
                >
                  <Send className="h-4 w-4" />
                  Send reply
                </Button>
              </div>
            </form>
          </DetailPanel>

          <DetailPanel
            title="Internal notes"
            description="Visible to authorized staff only"
            actions={
              <Button
                size="sm"
                variant="secondary"
                disabled={disabled}
                onClick={() => openNote()}
              >
                <Plus className="h-4 w-4" />
                Add note
              </Button>
            }
          >
            {(inquiry.notes ?? []).length ? (
              <div className="space-y-3">
                {inquiry.notes.map((note) => (
                  <NoteCard
                    key={note.id}
                    note={note}
                    disabled={disabled}
                    canEdit={isAdmin || Number(note.user_id) === user.id}
                    onEdit={openNote}
                    onDelete={(item) => askDelete("note", item)}
                  />
                ))}
              </div>
            ) : (
              <p className="text-sm text-slate-500">
                No internal notes yet.
              </p>
            )}
          </DetailPanel>

          <DetailPanel
            title="Follow-up reminders"
            actions={
              <Button
                size="sm"
                variant="secondary"
                disabled={disabled}
                onClick={openReminder}
              >
                <Plus className="h-4 w-4" />
                Add reminder
              </Button>
            }
          >
            {(inquiry.reminders ?? []).length ? (
              <div className="space-y-3">
                {inquiry.reminders.map((reminder) => (
                  <ReminderItem
                    key={reminder.id}
                    reminder={reminder}
                    busy={disabled}
                    canManage={
                      canManageInquiries ||
                      Number(reminder.user_id) === user.id
                    }
                    onComplete={(item) =>
                      perform(
                        `complete-${item.id}`,
                        () => remindersApi.complete(item.id),
                        "Reminder completed.",
                      )
                    }
                    onDelete={(item) => askDelete("reminder", item)}
                  />
                ))}
              </div>
            ) : (
              <p className="text-sm text-slate-500">
                No reminders have been added.
              </p>
            )}
          </DetailPanel>

          <DetailPanel title="Attachments">
            {(inquiry.attachments ?? []).length > 0 ? (
              <ul className="mb-6 divide-y divide-slate-100">
                {inquiry.attachments.map((attachment) => (
                  <li
                    key={attachment.id}
                    className="flex items-center gap-3 py-3 first:pt-0"
                  >
                    <FileText className="h-7 w-7 shrink-0 text-slate-400" />

                    <div className="min-w-0 flex-1">
                      <p className="truncate text-sm font-medium">
                        {attachment.original_name}
                      </p>
                      <p className="mt-1 text-xs text-slate-500">
                        {formatBytes(attachment.size)}
                        {" · "}
                        {attachment.uploader?.name ?? "Customer"}
                      </p>
                    </div>

                    <Button
                      variant="ghost"
                      size="sm"
                      aria-label={`Download ${attachment.original_name}`}
                      onClick={() => downloadAttachment(attachment)}
                    >
                      <Download className="h-4 w-4" />
                    </Button>

                    <Button
                      variant="ghost"
                      size="sm"
                      disabled={disabled}
                      aria-label={`Delete ${attachment.original_name}`}
                      onClick={() => askDelete("attachment", attachment)}
                    >
                      <Trash2 className="h-4 w-4" />
                    </Button>
                  </li>
                ))}
              </ul>
            ) : (
              <p className="mb-5 text-sm text-slate-500">
                No attachments yet.
              </p>
            )}

            <FileUpload
              files={files}
              errors={uploadErrors}
              disabled={disabled}
              onChange={(next) => {
                setFiles(next);
                setUploadErrors({});
              }}
            />

            <div className="mt-3 flex justify-end">
              <Button
                variant="secondary"
                loading={busyAction === "upload"}
                disabled={disabled || files.length === 0}
                onClick={uploadFiles}
              >
                <Upload className="h-4 w-4" />
                Upload attachments
              </Button>
            </div>
          </DetailPanel>

          <DetailPanel title="Activity timeline">
            <Timeline items={inquiry.activity_logs ?? []} />
          </DetailPanel>
        </div>
      </div>

      <Modal
        open={editOpen}
        onClose={() => setEditOpen(false)}
        title="Edit inquiry details"
        size="lg"
        busy={Boolean(busyAction)}
        footer={
          <>
            <Button
              variant="secondary"
              disabled={Boolean(busyAction)}
              onClick={() => setEditOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              form="edit-inquiry-form"
              loading={busyAction === "edit"}
            >
              Save changes
            </Button>
          </>
        }
      >
        <form id="edit-inquiry-form" noValidate onSubmit={saveEdit}>
          <fieldset
            disabled={Boolean(busyAction)}
            className="grid gap-4 sm:grid-cols-2"
          >
            <FormInput
              label="Name"
              required
              maxLength={100}
              value={editValues.name ?? ""}
              error={editErrors.name}
              onChange={(event) => changeEdit("name", event.target.value)}
            />
            <FormInput
              label="Email"
              type="email"
              required
              maxLength={255}
              value={editValues.email ?? ""}
              error={editErrors.email}
              onChange={(event) => changeEdit("email", event.target.value)}
            />
            <FormInput
              label="Phone"
              type="tel"
              maxLength={30}
              value={editValues.phone ?? ""}
              error={editErrors.phone}
              onChange={(event) => changeEdit("phone", event.target.value)}
            />
            <FormInput
              label="Company"
              maxLength={150}
              value={editValues.company ?? ""}
              error={editErrors.company}
              onChange={(event) => changeEdit("company", event.target.value)}
            />
            <FormInput
              label="Subject"
              required
              maxLength={150}
              className="sm:col-span-2"
              value={editValues.subject ?? ""}
              error={editErrors.subject}
              onChange={(event) => changeEdit("subject", event.target.value)}
            />
            <FormSelect
              label="Source"
              options={SOURCES}
              value={editValues.source ?? "website"}
              error={editErrors.source}
              onChange={(event) => changeEdit("source", event.target.value)}
            />
            <FormSelect
              label="Priority"
              options={PRIORITIES}
              value={editValues.priority ?? "medium"}
              error={editErrors.priority}
              onChange={(event) => changeEdit("priority", event.target.value)}
            />

            {[
              ["utm_source", "UTM source"],
              ["utm_medium", "UTM medium"],
              ["utm_campaign", "UTM campaign"],
            ].map(([field, label]) => (
              <FormInput
                key={field}
                label={label}
                maxLength={150}
                value={editValues[field] ?? ""}
                error={editErrors[field]}
                onChange={(event) => changeEdit(field, event.target.value)}
              />
            ))}
          </fieldset>
        </form>
      </Modal>

      <Modal
        open={noteOpen}
        onClose={() => setNoteOpen(false)}
        title={editingNote ? "Edit internal note" : "Add internal note"}
        busy={Boolean(busyAction)}
        footer={
          <>
            <Button
              variant="secondary"
              disabled={Boolean(busyAction)}
              onClick={() => setNoteOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              form="note-form"
              loading={busyAction === "note"}
            >
              {editingNote ? "Save note" : "Add note"}
            </Button>
          </>
        }
      >
        <form id="note-form" noValidate onSubmit={saveNote}>
          <FormTextarea
            label="Internal note"
            required
            rows={6}
            maxLength={5000}
            value={noteBody}
            error={noteErrors.body}
            disabled={Boolean(busyAction)}
            onChange={(event) => setNoteBody(event.target.value)}
          />
        </form>
      </Modal>

      <Modal
        open={reminderOpen}
        onClose={() => setReminderOpen(false)}
        title="Add follow-up reminder"
        busy={Boolean(busyAction)}
        footer={
          <>
            <Button
              variant="secondary"
              disabled={Boolean(busyAction)}
              onClick={() => setReminderOpen(false)}
            >
              Cancel
            </Button>
            <Button
              type="submit"
              form="reminder-form"
              loading={busyAction === "reminder"}
            >
              Create reminder
            </Button>
          </>
        }
      >
        <form
          id="reminder-form"
          noValidate
          onSubmit={saveReminder}
          className="space-y-4"
        >
          <FormInput
            label="Title"
            required
            maxLength={200}
            value={reminderValues.title}
            error={reminderErrors.title}
            disabled={Boolean(busyAction)}
            onChange={(event) =>
              setReminderValues((current) => ({
                ...current,
                title: event.target.value,
              }))
            }
          />

          <FormInput
            label="Date and time"
            type="datetime-local"
            required
            value={reminderValues.remind_at}
            error={reminderErrors.remind_at}
            help="Uses your device’s local time zone."
            disabled={Boolean(busyAction)}
            onChange={(event) =>
              setReminderValues((current) => ({
                ...current,
                remind_at: event.target.value,
              }))
            }
          />
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(confirmation)}
        title={confirmation?.title}
        message={confirmation?.message}
        loading={busyAction === "delete"}
        onClose={() => setConfirmation(null)}
        onConfirm={confirmDelete}
      />
    </div>
  );
}
