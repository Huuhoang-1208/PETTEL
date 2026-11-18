"use client";

import { ApiError } from "@/components/ui/api-error";
import { write } from "@/lib/api/request";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useState } from "react";

export function ReportForm({ target }: { target: { listing_id?: string; booking_id?: string } }) {
  const { user } = useSession();
  const [error, setError] = useState<unknown>();
  const [sent, setSent] = useState(false);
  const [busy, setBusy] = useState(false);
  return (
    <details className="app-card">
      <summary>Báo cáo vấn đề</summary>
      {!user ? (
        <Link href="/login">Đăng nhập để báo cáo</Link>
      ) : sent ? (
        <p role="status">Đã gửi báo cáo để quản trị viên xem xét.</p>
      ) : (
        <form
          className="app-form"
          onSubmit={async (event) => {
            event.preventDefault();
            const form = new FormData(event.currentTarget);
            setBusy(true);
            setError(undefined);
            try {
              await write("post", "/api/reports", {
                ...target,
                reason_code: form.get("reason_code"),
                description: form.get("description"),
              });
              setSent(true);
            } catch (failure) {
              setError(failure);
            } finally {
              setBusy(false);
            }
          }}
        >
          <label>
            Lý do
            <select name="reason_code">
              <option value="INACCURATE_INFORMATION">Thông tin không chính xác</option>
              <option value="SERVICE_ISSUE">Vấn đề dịch vụ</option>
              <option value="OTHER">Khác</option>
            </select>
          </label>
          <label>
            Mô tả
            <textarea name="description" required maxLength={2000} />
          </label>
          <ApiError error={error} />
          <button className="app-button" disabled={busy}>
            Gửi báo cáo
          </button>
        </form>
      )}
    </details>
  );
}
