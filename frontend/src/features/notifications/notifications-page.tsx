"use client";

import { ApiError } from "@/components/ui/api-error";
import { SiteHeader } from "@/components/layout/site-header";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { RequireSession } from "@/features/auth/components/require-session";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type NotificationPage } from "@/lib/api/types";
import { dateTime } from "@/lib/format";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useState } from "react";
import styles from "./notifications-page.module.css";

function notificationCopy(type: string): { title: string; message: string } {
  switch (type) {
    case "BOOKING_COMPLETION_REMINDER":
      return {
        title: "Xác nhận đơn chăm sóc đã hoàn tất",
        message: "Đơn chăm sóc đã kết thúc. Hãy kiểm tra và xác nhận để PETTEL cập nhật trạng thái đơn.",
      };
    default:
      return { title: "Thông báo từ PETTEL", message: "Bạn có một cập nhật mới từ PETTEL." };
  }
}

export function NotificationsPage() {
  const { user } = useSession();
  const [page, setPage] = useState(1);
  const [busy, setBusy] = useState(false);
  const notifications = useApi<NotificationPage>(user ? `/api/notifications?page=${page}&per_page=20` : null, 30000);

  async function markAllRead() {
    setBusy(true);
    try {
      await write("post", "/api/notifications/read-all");
      notifications.reload();
    } catch {
      // The page-level API error will be refreshed by the next list request.
    } finally {
      setBusy(false);
    }
  }

  async function markRead(id: string) {
    try {
      await write("patch", `/api/notifications/${encodeURIComponent(id)}/read`);
      notifications.reload();
    } catch {
      // Keep navigation available if the read receipt cannot be saved.
    }
  }

  return (
    <>
      <SiteHeader />
      <main className={`app-container ${styles.page}`}>
        <header className={styles.heading}>
          <div>
            <span className={styles.eyebrow}><PettelIcon name="bell" /> CẬP NHẬT TỪ PETTEL</span>
            <h1>Thông báo</h1>
            <p>Các cập nhật về đơn chăm sóc và hoạt động trên tài khoản của bạn.</p>
          </div>
          <div className={styles.actions}>
            {notifications.data?.unread_count ? (
              <button type="button" disabled={busy} onClick={() => void markAllRead()}>
                {busy ? "Đang cập nhật..." : "Đánh dấu tất cả đã đọc"}
              </button>
            ) : null}
            <button type="button" onClick={notifications.reload}>Tải lại</button>
          </div>
        </header>

        <ApiError error={notifications.error} />
        <RequireSession next="/notifications" redirectToLogin>
          {notifications.loading ? <p role="status">Đang tải thông báo...</p> : null}
          <section className={styles.list} aria-label="Danh sách thông báo">
          {notifications.data?.data.map((notification) => {
            const copy = notificationCopy(notification.type);
            const href = "/owner-profile#history";

            return (
              <article className={`${styles.item} ${notification.read_at ? "" : styles.unread}`} key={notification.id}>
                <span className={styles.icon}><PettelIcon name="bell" /></span>
                <div className={styles.copy}>
                  <div className={styles.titleLine}>
                    <h2>{copy.title}</h2>
                    {!notification.read_at && <span>Chưa đọc</span>}
                  </div>
                  <p>{copy.message}</p>
                  <time dateTime={notification.created_at}>{dateTime(notification.created_at)}</time>
                </div>
                <Link href={href} onClick={() => { if (!notification.read_at) void markRead(notification.id); }}>
                  Xem chi tiết <span aria-hidden="true">→</span>
                </Link>
              </article>
            );
          })}
          {!notifications.loading && notifications.data?.data.length === 0 ? (
            <div className={styles.empty}>
              <span><PettelIcon name="bell" /></span>
              <h2>Bạn chưa có thông báo</h2>
              <p>Khi có cập nhật về đơn chăm sóc hoặc tài khoản, thông báo sẽ xuất hiện tại đây.</p>
              <Link href="/services">Khám phá dịch vụ</Link>
            </div>
          ) : null}
          </section>

          {notifications.data && notifications.data.meta && notifications.data.meta.last_page > 1 ? (
            <div className={styles.pagination}>
              <button type="button" disabled={page <= 1} onClick={() => setPage((current) => current - 1)}>Trang trước</button>
              <span>Trang {notifications.data.meta.current_page} / {notifications.data.meta.last_page}</span>
              <button type="button" disabled={page >= notifications.data.meta.last_page} onClick={() => setPage((current) => current + 1)}>Trang sau</button>
            </div>
          ) : null}
        </RequireSession>
      </main>
    </>
  );
}
