"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { useConfirmation } from "@/components/ui/confirmation-dialog";
import { ManagedListing, Slot } from "@/features/caregiver/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Page } from "@/lib/api/types";
import { vietnamISO } from "@/lib/date";
import { useMemo, useState, type FormEvent } from "react";
import styles from "./availability-manager.module.css";

function dayLabel(value: string) {
  return new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", weekday: "long", day: "2-digit", month: "long" }).format(new Date(value));
}

function timeLabel(value: string) {
  return new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", hour: "2-digit", minute: "2-digit" }).format(new Date(value));
}

export function AvailabilityManager({ listings }: { listings: ManagedListing[] }) {
  const [page, setPage] = useState(1);
  const [selectedListingId, setSelectedListingId] = useState(String(listings[0]?.id ?? ""));
  const listing = listings.find((item) => String(item.id) === selectedListingId) ?? listings[0];
  const slots = useApi<Page<Slot>>(listing ? `/api/caregiver/listings/${listing.id}/availability-slots?page=${page}&per_page=50` : null);
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const editable = listing?.status !== "ARCHIVED";
  const { ask, dialog } = useConfirmation();
  const orderedSlots = useMemo(() => [...(slots.data?.data ?? [])].sort((a, b) => a.start_at.localeCompare(b.start_at)), [slots.data]);

  if (!listing) return null;

  async function removeSlot(slot: Slot) {
    if (!(await ask("Bạn có chắc muốn xóa khung giờ nhận chăm này?"))) return;
    setBusy(true);
    setError(undefined);
    try {
      await write("delete", `/api/caregiver/listings/${listing.id}/availability-slots/${slot.id}`);
      slots.reload();
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }

  async function addSlot(event: FormEvent<HTMLFormElement>) {
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
  }

  return (
    <section className={styles.manager}>
      {dialog}
      <header className={styles.header}>
        <span className={styles.serviceIcon}><PettelIcon name="calendar" /></span>
        <div className={styles.headerTitle}><small>LỊCH NHẬN CHĂM</small><h3>Quản lý khung giờ của bạn</h3><p>Lịch trống được thiết lập riêng cho từng dịch vụ.</p></div>
        <span className={styles.countBadge}><PettelIcon name="clock" /> {slots.data?.meta?.total ?? orderedSlots.length} khung giờ</span>
      </header>

      <ApiError error={slots.error ?? error} />

      <div className={styles.body}>
        <div className={styles.slotColumn}>
          <div className={styles.sectionHeading}><div><h4>Khung giờ đã mở</h4><p>Khách có thể gửi yêu cầu trong các khoảng thời gian này.</p></div>{(slots.data?.meta?.last_page ?? 1) > 1 && <div className={styles.pagination}><button type="button" disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>Trước</button><span>{page}/{slots.data?.meta?.last_page}</span><button type="button" disabled={page >= (slots.data?.meta?.last_page ?? 1)} onClick={() => setPage((value) => value + 1)}>Sau</button></div>}</div>
          {slots.loading && <p className={styles.empty}>Đang tải khung giờ...</p>}
          {!slots.loading && orderedSlots.length === 0 && <div className={styles.empty}><span><PettelIcon name="calendar" /></span><strong>Chưa có khung giờ nào</strong><small>Thêm lịch trống ở bên cạnh để khách biết khi nào bạn có thể nhận chăm.</small></div>}
          <div className={styles.slotList}>{orderedSlots.map((slot) => <article className={styles.slot} key={slot.id}>
            <span className={styles.slotDate}><PettelIcon name="calendar" /><strong>{dayLabel(slot.start_at)}</strong></span>
            <span className={styles.slotTime}>{timeLabel(slot.start_at)}<i>–</i>{timeLabel(slot.end_at)}</span>
            {editable && <button className={styles.deleteButton} type="button" disabled={busy} onClick={() => void removeSlot(slot)} aria-label="Xóa khung giờ"><span aria-hidden="true">×</span> Xóa</button>}
          </article>)}</div>
        </div>

        <aside className={styles.addPanel}>
          <span className={styles.addIcon}><PettelIcon name="plus" /></span>
          <h4>Thêm khung giờ</h4>
          <p>Chọn thời gian bạn sẵn sàng nhận lịch cho dịch vụ này.</p>
          {editable ? <form className={styles.form} onSubmit={addSlot}>
            <label>Ngày và giờ bắt đầu<input type="datetime-local" name="start" required /></label>
            <label className={styles.serviceSelect}>Chọn dịch vụ
              <select value={String(listing.id)} onChange={(event) => { setSelectedListingId(event.target.value); setPage(1); }}>
                {listings.map((item) => <option key={item.id} value={String(item.id)}>{item.title} · {item.service_type === "BOARDING" ? "Trông giữ qua đêm" : "Daycare ban ngày"}</option>)}
              </select>
            </label>
            <label>Ngày và giờ kết thúc<input type="datetime-local" name="end" required /></label>
            <button type="submit" disabled={busy}><PettelIcon name="plus" /> {busy ? "Đang lưu..." : "Mở khung giờ"}</button>
          </form> : <div className={styles.readOnly}><PettelIcon name="shield" /> Dịch vụ đã lưu trữ, không thể chỉnh lịch.</div>}
          <div className={styles.tip}><PettelIcon name="check" /><span>Không thể tạo các khung giờ bị trùng nhau.</span></div>
        </aside>
      </div>
    </section>
  );
}
