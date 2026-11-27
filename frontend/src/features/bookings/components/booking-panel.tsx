"use client";

import { ApiError } from "@/components/ui/api-error";
import { useConfirmation } from "@/components/ui/confirmation-dialog";
import { CareFeed } from "@/features/bookings/components/care-feed";
import { ReviewForm } from "@/features/bookings/components/review-form";
import { ReportForm } from "@/features/reports/components/report-form";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Booking } from "@/lib/api/types";
import { statuses } from "@/lib/constants";
import { dateTime, money } from "@/lib/format";
import Link from "next/link";
import { useRef, useState } from "react";
import styles from "./booking-panel.module.css";

const bookingStatusStyles: Record<string, string> = {
  REQUESTED: styles.statusPending,
  AWAITING_PAYMENT: styles.statusPending,
  CONFIRMED: styles.statusConfirmed,
  IN_PROGRESS: styles.statusActive,
  AWAITING_CONFIRMATION: styles.statusPending,
  COMPLETED: styles.statusCompleted,
  CANCELLED: styles.statusClosed,
  REJECTED: styles.statusClosed,
  EXPIRED: styles.statusClosed,
};

export function BookingPanel({
  id,
  onChange,
  variant = "default",
}: {
  id: string;
  onChange: () => void;
  variant?: "default" | "caregiver";
}) {
  const booking = useApi<{ data: Booking }>(`/api/v1/bookings/${id}`, 15000);
  const [error, setError] = useState<unknown>();
  const [notice, setNotice] = useState("");
  const [busy, setBusy] = useState(false);
  const [attempt, setAttempt] = useState<{
    id: string;
    amount_vnd: number;
    is_sandbox: boolean;
  } | null>(null);
  const keys = useRef<Record<string, string>>({});
  const { ask, dialog } = useConfirmation();
  async function action(name: string, payload?: unknown) {
    setBusy(true);
    setError(undefined);
    setNotice("");
    const key = (keys.current[name] ??= crypto.randomUUID());
    try {
      await write("post", `/api/v1/bookings/${id}/${name}`, payload, key);
      delete keys.current[name];
      setNotice("Yêu cầu đã được gửi. Trạng thái bên dưới được cập nhật từ máy chủ.");
      booking.reload();
      onChange();
      return true;
    } catch (failure) {
      setError(failure);
      return false;
    } finally {
      setBusy(false);
    }
  }
  async function pay() {
    setBusy(true);
    setError(undefined);
    try {
      const result = await write<{ data: NonNullable<typeof attempt> }>(
        "post",
        `/api/v1/bookings/${id}/sandbox-payments`,
        undefined,
        (keys.current.payment ??= crypto.randomUUID()),
      );
      setAttempt(result.data);
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  const value = booking.data?.data;
  const owner = value?.participant_role === "OWNER";
  const canReopenExpiredPayment =
    !owner && value?.status === "CANCELLED" && value.cancellation_reason_code === "PAYMENT_EXPIRED";
  return (
    <article className={`${styles.panel} ${variant === "caregiver" ? styles.panelCaregiver : ""}`}>
      {dialog}
      <ApiError error={booking.error ?? error} />
      {booking.loading && !value && (
        <p className={styles.loading}>Đang tải thông tin đơn chăm sóc...</p>
      )}
      {value && (
        <>
          <header className={styles.heading}>
            <div>
              <span className={styles.eyebrow}>CHI TIẾT ĐƠN CHĂM SÓC</span>
              <h2>Đơn #{value.id}</h2>
              <p>{value.listing_title || "Dịch vụ chăm sóc thú cưng"}</p>
            </div>
            <span className={`${styles.status} ${bookingStatusStyles[value.status] ?? ""}`}>
              <span aria-hidden="true" />
              {statuses[value.status] ?? value.status}
            </span>
          </header>

          <section className={styles.summaryGrid} aria-label="Thông tin đơn chăm sóc">
            <div className={styles.summaryItem}>
              <span>Giờ gửi bé</span>
              <strong>{dateTime(value.start_at)}</strong>
            </div>
            <div className={styles.summaryItem}>
              <span>Giờ đón bé</span>
              <strong>{dateTime(value.end_at)}</strong>
            </div>
            <div className={styles.summaryItem}>
              <span>Thú cưng</span>
              <strong>{value.pet_name || "Chưa có tên thú cưng"}</strong>
            </div>
            <div className={`${styles.summaryItem} ${styles.priceItem}`}>
              <span>Tổng chi phí</span>
              <strong>{money(value.price_snapshot_vnd)}</strong>
            </div>
          </section>

          {value.payment_due_at && value.status === "AWAITING_PAYMENT" && (
            <div className={styles.deadline}>
              <strong>Hạn thanh toán</strong>
              <span>{dateTime(value.payment_due_at)}</span>
            </div>
          )}
          {canReopenExpiredPayment && (
            <div className={styles.reopenNotice} role="status">
              Đơn đã tự hủy do quá hạn thanh toán. Bạn có thể mở lại để chủ nuôi có thêm 15 phút;
              khung giờ cần còn trống.
            </div>
          )}
          {notice && (
            <p className={styles.notice} role="status">
              {notice}
            </p>
          )}

          <div className={styles.actions}>
            <Link className={styles.primaryAction} href={`/chat?booking=${id}`}>
              Mở chat của đơn
            </Link>
            {canReopenExpiredPayment && (
              <button
                className={styles.primaryAction}
                disabled={busy}
                onClick={async () => {
                  if (
                    await ask(
                      `Đơn #${id} đã tự hủy vì hết thời gian thanh toán. Bạn có thể mở lại đơn để chủ nuôi có thêm 15 phút thanh toán. PETTEL sẽ kiểm tra khung giờ còn trống trước khi mở.`,
                      {
                        title: "Mở lại đơn chăm sóc?",
                        cancelLabel: "Để sau",
                        confirmLabel: "Mở lại đơn",
                        className: "pet-booking-confirmation pet-reopen-confirmation",
                      },
                    )
                  )
                    void action("reopen-expired-payment");
                }}
              >
                Mở lại đơn · 15 phút
              </button>
            )}
            {!owner && value.status === "REQUESTED" && (
              <>
                <button
                  className={styles.primaryAction}
                  disabled={busy}
                  onClick={() => void action("accept")}
                >
                  Nhận đơn
                </button>
                <button
                  className={styles.dangerAction}
                  disabled={busy}
                  onClick={async () => {
                    if (await ask("Từ chối yêu cầu đặt lịch này?")) void action("reject");
                  }}
                >
                  Từ chối
                </button>
              </>
            )}
            {!owner && value.status === "CONFIRMED" && (
              <button
                className={styles.primaryAction}
                disabled={busy}
                onClick={() => void action("start")}
              >
                Bắt đầu chăm sóc
              </button>
            )}
            {!owner && value.status === "IN_PROGRESS" && (
              <button
                className={styles.primaryAction}
                disabled={busy}
                onClick={() => void action("request-completion")}
              >
                Yêu cầu xác nhận hoàn thành
              </button>
            )}
            {owner && value.status === "AWAITING_PAYMENT" && (
              <button className={styles.primaryAction} disabled={busy} onClick={() => void pay()}>
                Thanh toán sandbox
              </button>
            )}
            {owner && value.status === "AWAITING_CONFIRMATION" && (
              <button
                className={styles.primaryAction}
                disabled={busy}
                onClick={async () => {
                  if (
                    await ask(
                      "Hãy xác nhận bạn đã nhận lại thú cưng. Sau khi xác nhận, đơn chăm sóc sẽ được đánh dấu là đã hoàn thành.",
                      {
                        title: "Xác nhận hoàn thành dịch vụ?",
                        cancelLabel: "Quay lại",
                        confirmLabel: "Xác nhận hoàn thành",
                        className: "pet-booking-confirmation pet-completion-confirmation",
                      },
                    )
                  )
                    void action("confirm-completion");
                }}
              >
                Xác nhận hoàn thành
              </button>
            )}
            {["REQUESTED", "AWAITING_PAYMENT", "CONFIRMED"].includes(value.status) && (
              <button
                className={styles.dangerAction}
                disabled={busy}
                onClick={async () => {
                  if (
                    await ask(
                      "Gửi yêu cầu hủy đơn? Điều kiện hủy và hoàn tiền sẽ do máy chủ kiểm tra.",
                    )
                  )
                    void action("cancel");
                }}
              >
                Hủy đơn
              </button>
            )}
          </div>

          {attempt?.is_sandbox && value.status === "AWAITING_PAYMENT" && (
            <div className={styles.paymentNotice}>
              <h3>Thanh toán thử nghiệm</h3>
              <p>{money(attempt.amount_vnd)} · Không thu tiền thật.</p>
              <div className={styles.actions}>
                {[
                  ["SUCCESS", "Giả lập thành công"],
                  ["FAILURE", "Giả lập thất bại"],
                ].map(([result, label]) => (
                  <button
                    className={styles.secondaryAction}
                    key={result}
                    disabled={busy}
                    onClick={async () => {
                      const success = await action(`sandbox-payments/${attempt.id}/simulate`, {
                        result,
                      });
                      if (success) {
                        setAttempt(null);
                        delete keys.current.payment;
                      }
                    }}
                  >
                    {label}
                  </button>
                ))}
              </div>
            </div>
          )}

          <div className={styles.sectionDivider} />
          <section className={styles.detailSection}>
            <CareFeed booking={value} />
          </section>
          {owner && value.status === "COMPLETED" && (
            <section className={styles.detailSection}>
              <ReviewForm id={id} />
            </section>
          )}
          <div className={styles.reportSection}>
            <ReportForm target={{ booking_id: id }} />
          </div>
        </>
      )}
    </article>
  );
}
