"use client";

import { ApiError } from "@/components/ui/api-error";
import { useConfirmation } from "@/components/ui/confirmation-dialog";
import { ManagedListing, Slot } from "@/features/caregiver/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Page } from "@/lib/api/types";
import { vietnamISO } from "@/lib/date";
import { dateTime } from "@/lib/format";
import { useState } from "react";

export function AvailabilityManager({ listing }: { listing: ManagedListing }) {
  const [page, setPage] = useState(1);
  const slots = useApi<Page<Slot>>(
    `/api/caregiver/listings/${listing.id}/availability-slots?page=${page}`,
  );
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const editable = ["DRAFT", "ACTIVE", "SUSPENDED"].includes(listing.status ?? "");
  const { ask, dialog } = useConfirmation();
  return (
    <>
      {dialog}
      <details>
        <summary>Lịch nhận chăm sóc</summary>
        <ApiError error={slots.error ?? error} />
        {slots.data?.data.map((slot) => (
          <p key={slot.id}>
            {dateTime(slot.start_at)} → {dateTime(slot.end_at)}{" "}
            {editable && (
              <button
                disabled={busy}
                onClick={async () => {
                  if (!(await ask("Xóa khung lịch này?"))) return;
                  setBusy(true);
                  try {
                    await write(
                      "delete",
                      `/api/caregiver/listings/${listing.id}/availability-slots/${slot.id}`,
                    );
                    slots.reload();
                  } catch (failure) {
                    setError(failure);
                  } finally {
                    setBusy(false);
                  }
                }}
              >
                Xóa
              </button>
            )}
          </p>
        ))}
        <div className="app-actions">
          <button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
            Trước
          </button>
          <button
            disabled={page >= (slots.data?.meta?.last_page ?? 1)}
            onClick={() => setPage((value) => value + 1)}
          >
            Sau
          </button>
        </div>
        {editable && (
          <form
            className="app-form"
            onSubmit={async (event) => {
              event.preventDefault();
              const form = event.currentTarget;
              const data = new FormData(form);
              setBusy(true);
              setError(undefined);
              try {
                await write("post", `/api/caregiver/listings/${listing.id}/availability-slots`, {
                  start_at: vietnamISO(String(data.get("start"))),
                  end_at: vietnamISO(String(data.get("end"))),
                });
                form.reset();
                slots.reload();
              } catch (failure) {
                setError(failure);
              } finally {
                setBusy(false);
              }
            }}
          >
            <label>
              Bắt đầu (giờ Việt Nam)
              <input type="datetime-local" name="start" required />
            </label>
            <label>
              Kết thúc (giờ Việt Nam)
              <input type="datetime-local" name="end" required />
            </label>
            <button disabled={busy}>Thêm lịch trống</button>
          </form>
        )}
      </details>
    </>
  );
}
