"use client";
import { ApiError } from "@/components/ui/api-error";
import { useConfirmation } from "@/components/ui/confirmation-dialog";
import { PetCard } from "@/features/pets/components/pet-card";
import { PetEditor } from "@/features/pets/components/pet-editor";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Page, type Pet } from "@/lib/api/types";
import { useSession } from "@/providers/session-provider";
import { useState, type FormEvent } from "react";

export function PetManager() {
  const { user } = useSession();
  const { ask, dialog } = useConfirmation();
  const resource = useApi<Page<Pet>>(user ? "/api/pets" : null);
  const [editing, setEditing] = useState<Pet | null | undefined>();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const payload = Object.fromEntries(form);
    setBusy(true);
    setError(undefined);
    try {
      await write(
        editing ? "patch" : "post",
        editing ? `/api/pets/${editing.id}` : "/api/pets",
        payload,
      );
      setEditing(undefined);
      resource.reload();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  async function action(pet: Pet, type: "archive" | "activate" | "delete") {
    if (type === "delete" && !(await ask(`Xóa hồ sơ ${pet.name}?`))) return;
    setBusy(true);
    setError(undefined);
    try {
      await write(
        type === "delete" ? "delete" : "patch",
        `/api/pets/${pet.id}${type === "delete" ? "" : `/${type}`}`,
      );
      resource.reload();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      {dialog}
      <ApiError error={resource.error ?? error} />
      <button
        className="app-button"
        onClick={() => {
          setEditing(null);
          setError(undefined);
        }}
      >
        + Thêm thú cưng
      </button>
      {resource.loading && <p role="status">Đang tải hồ sơ...</p>}
      <div className="app-grid">
        {resource.data?.data.map((pet) => (
          <PetCard key={pet.id} pet={pet} busy={busy} onEdit={setEditing} onAction={action} />
        ))}
      </div>
      {!resource.loading && !resource.error && resource.data?.data.length === 0 && (
        <p className="app-notice">
          Bạn chưa có hồ sơ thú cưng. Thêm hồ sơ đầu tiên để đặt dịch vụ.
        </p>
      )}
      {editing !== undefined && (
        <PetEditor
          editing={editing}
          error={error}
          busy={busy}
          submit={submit}
          onClose={() => setEditing(undefined)}
        />
      )}
    </>
  );
}
