import { useState } from "react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import toast from "react-hot-toast";
import teamsApi from "../api/teams";
import usersApi from "../api/users";
import Button from "../components/Button";
import ConfirmDialog from "../components/ConfirmDialog";
import DataTable from "../components/DataTable";
import ErrorState from "../components/ErrorState";
import FormInput from "../components/FormInput";
import FormSelect from "../components/FormSelect";
import Modal from "../components/Modal";
import Pagination from "../components/Pagination";
import SearchInput from "../components/SearchInput";
import useAuth from "../hooks/useAuth";
import useDebounce from "../hooks/useDebounce";
import useFetch from "../hooks/useFetch";
import { ROLES } from "../utils/constants";
import { errorMessage, fieldErrors } from "../utils/errors";
import { labelFor } from "../utils/formatters";
import { validatePassword } from "../utils/validation";

const emptyValues = {
  name: "",
  email: "",
  password: "",
  password_confirmation: "",
  role: "agent",
  team_id: "",
  is_active: true,
};

async function loadAllTeams(signal) {
  const first = await teamsApi.list({ per_page: 100, page: 1 }, signal);
  const teams = [...first.data];

  for (let page = 2; page <= first.meta.last_page; page += 1) {
    const result = await teamsApi.list({ per_page: 100, page }, signal);
    teams.push(...result.data);
  }

  return { ...first, data: teams };
}

export default function UsersPage() {
  const { user: currentUser } = useAuth();

  const [search, setSearch] = useState("");
  const query = useDebounce(search, 400);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [values, setValues] = useState(emptyValues);
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [removing, setRemoving] = useState(false);

  const list = useFetch(
    (signal) =>
      usersApi.list({ q: query, page, per_page: perPage }, signal),
    [query, page, perPage],
  );

  const teams = useFetch(loadAllTeams);

  function openForm(target = null) {
    setEditing(target);
    setValues(
      target
        ? {
            name: target.name,
            email: target.email,
            password: "",
            password_confirmation: "",
            role: target.role,
            team_id: target.team_id ?? "",
            is_active: Boolean(target.is_active),
          }
        : { ...emptyValues },
    );
    setErrors({});
    setModalOpen(true);
  }

  function change(field, value) {
    setValues((current) => ({ ...current, [field]: value }));
    setErrors((current) => ({ ...current, [field]: undefined }));
  }

  async function save(event) {
    event.preventDefault();
    if (saving) return;

    const validation = {};

    if (!values.name.trim()) validation.name = "Enter a name.";
    if (!values.email.trim()) validation.email = "Enter an email address.";

    if (!editing || values.password) {
      if (!validatePassword(values.password)) {
        validation.password =
          "Use 12–200 characters with uppercase, lowercase, and a number.";
      }

      if (values.password !== values.password_confirmation) {
        validation.password_confirmation = "The passwords do not match.";
      }
    }

    setErrors(validation);
    if (Object.keys(validation).length) return;

    const payload = {
      ...values,
      team_id: values.team_id === "" ? null : Number(values.team_id),
    };

    if (editing && !payload.password) {
      delete payload.password;
      delete payload.password_confirmation;
    }

    setSaving(true);

    try {
      if (editing) {
        await usersApi.update(editing.id, payload);
      } else {
        await usersApi.create(payload);
      }

      setModalOpen(false);
      toast.success(editing ? "User updated." : "User created.");
      await list.refresh();
    } catch (error) {
      setErrors(fieldErrors(error));
      toast.error(errorMessage(error));
    } finally {
      setSaving(false);
    }
  }

  async function remove() {
    if (!deleting || removing) return;
    setRemoving(true);

    try {
      await usersApi.remove(deleting.id);
      setDeleting(null);
      toast.success("User deleted.");

      if (page > 1 && list.data?.length === 1) {
        setPage((current) => current - 1);
      } else {
        await list.refresh();
      }
    } catch (error) {
      toast.error(errorMessage(error));
    } finally {
      setRemoving(false);
    }
  }

  const columns = [
    {
      key: "name",
      label: "User",
      render: (row) => (
        <div>
          <p className="font-medium">{row.name}</p>
          <p className="mt-1 text-xs text-slate-500">{row.email}</p>
        </div>
      ),
    },
    {
      key: "role",
      label: "Role",
      render: (row) => labelFor(ROLES, row.role),
    },
    {
      key: "team",
      label: "Team",
      render: (row) => row.team?.name ?? "No team",
    },
    {
      key: "is_active",
      label: "Status",
      render: (row) => (
        <span
          className={`rounded-full px-2.5 py-1 text-xs font-medium ${
            row.is_active
              ? "bg-emerald-50 text-emerald-700"
              : "bg-slate-100 text-slate-500"
          }`}
        >
          {row.is_active ? "Active" : "Inactive"}
        </span>
      ),
    },
    {
      key: "actions",
      label: "Actions",
      render: (row) => (
        <div className="flex gap-1">
          <Button
            size="sm"
            variant="ghost"
            aria-label={`Edit ${row.name}`}
            onClick={() => openForm(row)}
          >
            <Pencil className="h-4 w-4" />
          </Button>

          {row.id !== currentUser.id && (
            <Button
              size="sm"
              variant="ghost"
              aria-label={`Delete ${row.name}`}
              onClick={() => setDeleting(row)}
            >
              <Trash2 className="h-4 w-4 text-red-500" />
            </Button>
          )}
        </div>
      ),
    },
  ];

  const editingSelf = editing?.id === currentUser.id;

  return (
    <div className="page-container">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="page-title">Users</h1>
          <p className="page-description">
            Manage staff access, roles, and team membership.
          </p>
        </div>

        <Button onClick={() => openForm()}>
          <Plus className="h-4 w-4" />
          Add user
        </Button>
      </header>

      <section className="panel overflow-hidden">
        <div className="border-b border-slate-100 p-4">
          <SearchInput
            value={search}
            onChange={(value) => {
              setSearch(value);
              setPage(1);
            }}
            placeholder="Search users by name or email"
            className="max-w-lg"
          />
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
              emptyTitle="No matching users"
            />
            <Pagination
              meta={{ current_page: page, per_page: perPage, ...list.meta }}
              disabled={list.loading}
              onPageChange={setPage}
              onPerPageChange={(value) => {
                setPerPage(value);
                setPage(1);
              }}
            />
          </>
        )}
      </section>

      <Modal
        open={modalOpen}
        onClose={() => setModalOpen(false)}
        title={editing ? "Edit user" : "Add user"}
        busy={saving}
        footer={
          <>
            <Button
              variant="secondary"
              disabled={saving}
              onClick={() => setModalOpen(false)}
            >
              Cancel
            </Button>
            <Button type="submit" form="user-form" loading={saving}>
              {editing ? "Save changes" : "Create user"}
            </Button>
          </>
        }
      >
        <form id="user-form" noValidate onSubmit={save}>
          <fieldset disabled={saving} className="space-y-4">
            <FormInput
              label="Full name"
              required
              maxLength={100}
              value={values.name}
              error={errors.name}
              onChange={(event) => change("name", event.target.value)}
            />

            <FormInput
              label="Email"
              type="email"
              required
              maxLength={255}
              value={values.email}
              error={errors.email}
              onChange={(event) => change("email", event.target.value)}
            />

            <div className="grid gap-4 sm:grid-cols-2">
              <FormSelect
                label="Role"
                options={ROLES}
                value={values.role}
                error={errors.role}
                disabled={editingSelf}
                onChange={(event) => change("role", event.target.value)}
              />

              <FormSelect
                label="Team"
                placeholder="No team"
                options={(teams.data ?? []).map((team) => ({
                  value: team.id,
                  label: team.name,
                }))}
                value={values.team_id}
                error={errors.team_id}
                disabled={teams.loading || Boolean(teams.error)}
                onChange={(event) => change("team_id", event.target.value)}
              />
            </div>

            {teams.error && (
              <ErrorState error={teams.error} onRetry={teams.refresh} />
            )}

            <FormInput
              label={editing ? "New password" : "Password"}
              type="password"
              autoComplete="new-password"
              required={!editing}
              maxLength={200}
              value={values.password}
              error={errors.password}
              help={
                editing
                  ? "Leave blank to keep the current password."
                  : "At least 12 characters, including uppercase, lowercase, and a number."
              }
              onChange={(event) => change("password", event.target.value)}
            />

            <FormInput
              label="Confirm password"
              type="password"
              autoComplete="new-password"
              maxLength={200}
              value={values.password_confirmation}
              error={errors.password_confirmation}
              onChange={(event) =>
                change("password_confirmation", event.target.value)
              }
            />

            <label className="flex items-center gap-2 text-sm text-slate-700">
              <input
                type="checkbox"
                checked={values.is_active}
                disabled={editingSelf}
                onChange={(event) => change("is_active", event.target.checked)}
              />
              Account is active
            </label>

            {errors.is_active && (
              <p className="field-error">{errors.is_active}</p>
            )}

            {editingSelf && (
              <p className="text-xs leading-5 text-slate-500">
                You cannot deactivate yourself or remove your own administrator
                role. Changing your email or password will require signing in
                again.
              </p>
            )}
          </fieldset>
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Delete user?"
        message={
          deleting
            ? `Delete ${deleting.name}? Their active inquiries will become
              unassigned. Accounts that have authored notes or reminders
              must be deactivated instead.`
            : ""
        }
        loading={removing}
        onClose={() => setDeleting(null)}
        onConfirm={remove}
      />
    </div>
  );
}
