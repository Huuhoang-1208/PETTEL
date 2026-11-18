"use client";

import { ApiError } from "@/components/ui/api-error";
import { ModerationCard } from "@/features/admin/components/moderation-card";
import { Change } from "@/features/admin/types";
import { useApi } from "@/hooks/use-api";
import { type Listing, type Page } from "@/lib/api/types";
import { apiUrl } from "@/lib/format";
import { useSession } from "@/providers/session-provider";
import { useState } from "react";

export function ModerationDashboard() {
  const { user } = useSession();
  const allowed = user?.roles?.some((role) => role.role === "ADMIN");
  const [status, setStatus] = useState("PENDING_REVIEW");
  const [page, setPage] = useState(1);
  const [changePage, setChangePage] = useState(1);
  const listings = useApi<Page<Listing>>(
    allowed ? `/api/admin/listings?status=${status}&page=${page}` : null,
  );
  const changes = useApi<Page<Change>>(
    allowed ? `/api/admin/listing-change-requests?page=${changePage}` : null,
  );
  if (!allowed) return <p className="app-notice">Tài khoản hiện tại không có quyền quản trị.</p>;
  return (
    <>
      <p>
        <a href={apiUrl("/admin/reports")} target="_blank" rel="noopener noreferrer">
          Mở công cụ quản lý báo cáo và kiểm duyệt nội dung
        </a>
      </p>
      <label>
        Lọc dịch vụ{" "}
        <select
          value={status}
          onChange={(event) => {
            setStatus(event.target.value);
            setPage(1);
          }}
        >
          {[
            ["PENDING_REVIEW", "Chờ duyệt"],
            ["ACTIVE", "Đang hoạt động"],
            ["SUSPENDED", "Tạm ngưng"],
            ["ALL", "Tất cả"],
          ].map(([value, label]) => (
            <option key={value} value={value}>
              {label}
            </option>
          ))}
        </select>
      </label>
      <ApiError error={listings.error} />
      {listings.loading && <p>Đang tải hàng đợi...</p>}
      {listings.data?.data.map((listing) => (
        <ModerationCard
          key={`${listing.id}-${listing.status}`}
          listing={listing}
          refresh={listings.reload}
        />
      ))}
      {listings.data?.data.length === 0 && <p>Không có dịch vụ trong hàng đợi này.</p>}
      <div className="app-actions">
        <button disabled={page <= 1} onClick={() => setPage((value) => value - 1)}>
          Trước
        </button>
        <span>Trang {page}</span>
        <button
          disabled={page >= (listings.data?.meta?.last_page ?? 1)}
          onClick={() => setPage((value) => value + 1)}
        >
          Sau
        </button>
      </div>
      <h2>Đề xuất chỉnh sửa chờ duyệt</h2>
      <ApiError error={changes.error} />
      {changes.data?.data.map((change) => (
        <ModerationCard
          key={change.id}
          listing={change.proposed_data_json.data}
          path={`/api/admin/listings/${change.listing_id}/change-requests/${change.id}`}
          refresh={() => {
            changes.reload();
            listings.reload();
          }}
        />
      ))}
      {changes.data?.data.length === 0 && <p>Chưa có đề xuất chỉnh sửa chờ duyệt.</p>}
      <div className="app-actions">
        <button disabled={changePage <= 1} onClick={() => setChangePage((value) => value - 1)}>
          Trước
        </button>
        <span>Trang {changePage}</span>
        <button
          disabled={changePage >= (changes.data?.meta?.last_page ?? 1)}
          onClick={() => setChangePage((value) => value + 1)}
        >
          Sau
        </button>
      </div>
    </>
  );
}
