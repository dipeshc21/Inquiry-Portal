import { useState } from "react";
import { Pencil, Plus, Trash2 } from "lucide-react";
import toast from "react-hot-toast";
import teamsApi from "../api/teams";
import Button from "../components/Button";
import ConfirmDialog from "../components/ConfirmDialog";
import DataTable from "../components/DataTable";
import ErrorState from "../components/ErrorState";
import FormInput from "../components/FormInput";
import Modal from "../components/Modal";
import Pagination from "../components/Pagination";
import SearchInput from "../components/SearchInput";
import useDebounce from "../hooks/useDebounce";
import useFetch from "../hooks/useFetch";
import { errorMessage, fieldErrors } from "../utils/errors";
import { formatDate } from "../utils/formatters";

export default function TeamsPage() {
  const [search, setSearch] = useState("");
  const query = useDebounce(search, 400);
  const [page, setPage] = useState(1);
  const [perPage, setPerPage] = useState(15);
  const [modalOpen, setModalOpen] = useState(false);
  const [editing, setEditing] = useState(null);
  const [name, setName] = useState("");
  const [errors, setErrors] = useState({});
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState(null);
  const [removing, setRemoving] = useState(false);

  const list = useFetch(
    (signal) =>
      teamsApi.list({ q: query, page, per_page: perPage }, signal),
    [query, page, perPage],
  );

  function openForm(team = null) {
    setEditing(team);
    setName(team?.name ?? "");
    setErrors({});
    setModalOpen(true);
  }

  async function save(event) {
    event.preventDefault();
    if (saving) return;

    if (!name.trim()) {
      setErrors({ name: "Enter a team name." });
      return;
    }

    setErrors({});
    setSaving(true);

    try {
      if (editing) {
        await teamsApi.update(editing.id, { name });
      } else {
        await teamsApi.create({ name });
      }

      setModalOpen(false);
      toast.success(editing ? "Team updated." : "Team created.");
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
      await teamsApi.remove(deleting.id);
      setDeleting(null);
      toast.success("Team deleted.");

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
      label: "Team",
      render: (row) => <span className="font-medium">{row.name}</span>,
    },
    {
      key: "users_count",
      label: "Members",
    },
    {
      key: "created_at",
      label: "Created",
      render: (row) => formatDate(row.created_at),
    },
    {
      key: "actions",
      label: "Actions",
      render: (row) => (
        <div className="flex gap-1">
          <Button
            variant="ghost"
            size="sm"
            aria-label={`Edit ${row.name}`}
            onClick={() => openForm(row)}
          >
            <Pencil className="h-4 w-4" />
          </Button>
          <Button
            variant="ghost"
            size="sm"
            aria-label={`Delete ${row.name}`}
            onClick={() => setDeleting(row)}
          >
            <Trash2 className="h-4 w-4 text-red-500" />
          </Button>
        </div>
      ),
    },
  ];

  return (
    <div className="page-container">
      <header className="flex flex-wrap items-start justify-between gap-4">
        <div>
          <h1 className="page-title">Teams</h1>
          <p className="page-description">
            Organize staff into teams. Assign members from the Users page.
          </p>
        </div>

        <Button onClick={() => openForm()}>
          <Plus className="h-4 w-4" />
          Add team
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
            placeholder="Search teams"
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
              emptyTitle="No matching teams"
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
        title={editing ? "Edit team" : "Add team"}
        busy={saving}
        size="sm"
        footer={
          <>
            <Button
              variant="secondary"
              disabled={saving}
              onClick={() => setModalOpen(false)}
            >
              Cancel
            </Button>
            <Button type="submit" form="team-form" loading={saving}>
              {editing ? "Save changes" : "Create team"}
            </Button>
          </>
        }
      >
        <form id="team-form" noValidate onSubmit={save}>
          <FormInput
            label="Team name"
            required
            maxLength={100}
            value={name}
            disabled={saving}
            error={errors.name}
            onChange={(event) => {
              setName(event.target.value);
              setErrors({});
            }}
          />
        </form>
      </Modal>

      <ConfirmDialog
        open={Boolean(deleting)}
        title="Delete team?"
        message={
          deleting
            ? `Delete ${deleting.name}? Its members will remain active and
              their team membership will be cleared.`
            : ""
        }
        loading={removing}
        onClose={() => setDeleting(null)}
        onConfirm={remove}
      />
    </div>
  );
}
