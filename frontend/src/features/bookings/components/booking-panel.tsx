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

export function BookingPanel({ id, onChange }: { id: string; onChange: () => void }) {
  const booking = useApi<{ data: Booking }>(`/api/bookings/${id}`, 15000);
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
      await write("post", `/api/bookings/${id}/${name}`, payload, key);
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
        `/api/bookings/${id}/sandbox-payments`,
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
  return (
    <div className="app-card">
      {dialog}
      <ApiError error={booking.error ?? error} />
      {booking.loading && <p>Đang cập nhật đơn...</p>}
      {value && (
        <>
          <h2>
            Đơn #{value.id} · {value.listing_title}
          </h2>
          <p>
            <strong>{statuses[value.status] ?? value.status}</strong>
          </p>
          <p>
            {dateTime(value.start_at)} → {dateTime(value.end_at)}
          </p>
          <h3>{money(value.price_snapshot_vnd)}</h3>
          {value.payment_due_at && value.status === "AWAITING_PAYMENT" && (
            <p>Hạn thanh toán: {dateTime(value.payment_due_at)}</p>
          )}
          <p role="status">{notice}</p>
          <div className="app-actions">
            <Link className="app-button" href={`/chat?booking=${id}`}>
              Mở chat của đơn
            </Link>
            {!owner && value.status === "REQUESTED" && (
              <>
                <button disabled={busy} onClick={() => void action("accept")}>
                  Nhận đơn
                </button>
                <button
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
              <button disabled={busy} onClick={() => void action("start")}>
                Bắt đầu chăm sóc
              </button>
            )}
            {!owner && value.status === "IN_PROGRESS" && (
              <button disabled={busy} onClick={() => void action("request-completion")}>
                Yêu cầu xác nhận hoàn thành
              </button>
            )}
            {owner && value.status === "AWAITING_PAYMENT" && (
              <button disabled={busy} onClick={() => void pay()}>
                Thanh toán sandbox
              </button>
            )}
            {owner && value.status === "AWAITING_CONFIRMATION" && (
              <button
                disabled={busy}
                onClick={async () => {
                  if (await ask("Xác nhận đã nhận lại thú cưng và hoàn thành dịch vụ?"))
                    void action("confirm-completion");
                }}
              >
                Xác nhận hoàn thành
              </button>
            )}
            {["REQUESTED", "AWAITING_PAYMENT", "CONFIRMED"].includes(value.status) && (
              <button
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
            <div className="app-notice">
              <h3>Thanh toán thử nghiệm</h3>
              <p>{money(attempt.amount_vnd)} · Không thu tiền thật.</p>
              <div className="app-actions">
                {[
                  ["SUCCESS", "Giả lập thành công"],
                  ["FAILURE", "Giả lập thất bại"],
                ].map(([result, label]) => (
                  <button
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
          <CareFeed booking={value} />
          {owner && value.status === "COMPLETED" && <ReviewForm id={id} />}
          <ReportForm target={{ booking_id: id }} />
        </>
      )}
    </div>
  );
}
