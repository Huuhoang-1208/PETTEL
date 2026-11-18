"use client";

import { ApiError } from "@/components/ui/api-error";
import { write } from "@/lib/api/request";
import { useState } from "react";

export function ReviewForm({ id }: { id: string }) {
  const [sent, setSent] = useState(false);
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  return (
    <details className="app-card">
      <summary>Đánh giá dịch vụ</summary>
      {sent ? (
        <p role="status">Đã gửi đánh giá. Cảm ơn bạn!</p>
      ) : (
        <form
          className="app-form"
          onSubmit={async (event) => {
            event.preventDefault();
            const data = new FormData(event.currentTarget);
            setBusy(true);
            setError(undefined);
            try {
              await write("post", `/api/bookings/${id}/reviews`, {
                rating: Number(data.get("rating")),
                body: data.get("body"),
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
            Điểm đánh giá
            <select name="rating" defaultValue="5">
              {[5, 4, 3, 2, 1].map((rating) => (
                <option key={rating} value={rating}>
                  {rating} sao
                </option>
              ))}
            </select>
          </label>
          <label>
            Nhận xét
            <textarea name="body" maxLength={1000} />
          </label>
          <ApiError error={error} />
          <button disabled={busy} className="app-button">
            Gửi đánh giá
          </button>
        </form>
      )}
    </details>
  );
}
