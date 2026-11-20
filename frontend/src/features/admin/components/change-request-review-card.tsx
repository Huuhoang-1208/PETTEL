"use client";

import { ApiError } from "@/components/ui/api-error";
import { ModerationCard } from "@/features/admin/components/moderation-card";
import { type Change } from "@/features/admin/types";
import { useApi } from "@/hooks/use-api";
import { type Listing } from "@/lib/api/types";
import { areas, services, sizes, species } from "@/lib/constants";
import { money } from "@/lib/format";
import styles from "../admin-page.module.css";

type ManagedListing = Omit<Listing, "media"> & {
  version: number;
  pending_change_request_id: string | null;
  media?: { media_object_id: string; image_url: string; thumbnail_url: string }[];
};

type ProposalData = Omit<Listing, "media" | "price_total_vnd" | "rating_average" | "review_count"> & {
  media?: { media_object_id: string; sort_order: number }[];
};

const labels: Record<string, string> = {
  title: "Tên dịch vụ",
  description: "Mô tả",
  service_type: "Loại dịch vụ",
  area_code: "Khu vực",
  price: "Giá dịch vụ",
  acceptance_species: "Loài thú cưng nhận chăm",
  acceptance_sizes: "Kích thước thú cưng nhận chăm",
  media: "Ảnh dịch vụ",
};

function describe(key: string, value: unknown): string {
  if (key === "service_type") return services[String(value)] ?? String(value ?? "—");
  if (key === "area_code") return areas[String(value)] ?? String(value ?? "—");
  if (key === "price_vnd") return money(Number(value));
  if (key === "price") {
    const price = value as { price_vnd: number; price_unit: string };
    return `${money(Number(price.price_vnd))} / ${describe("price_unit", price.price_unit)}`;
  }
  if (key === "price_unit") return value === "HOUR" ? "giờ" : value === "NIGHT" ? "đêm" : String(value ?? "—");
  if (key === "acceptance_species") return Array.isArray(value) ? value.map((item) => species[String(item)] ?? String(item)).join(", ") || "Không có" : "—";
  if (key === "acceptance_sizes") return Array.isArray(value) ? value.map((item) => sizes[String(item)] ?? String(item)).join(", ") || "Không có" : "—";
  if (key === "media") {
    const media = value as { count: number; added?: string[]; removed?: string[] };
    const details = [
      media.added?.length ? `thêm ảnh #${media.added.join(", #")}` : "",
      media.removed?.length ? `bỏ ảnh #${media.removed.join(", #")}` : "",
    ].filter(Boolean);
    return `${media.count} ảnh${details.length ? ` · ${details.join(" · ")}` : ""}`;
  }
  return typeof value === "string" && value.length ? value : "Chưa có nội dung";
}

export function ChangeRequestReviewCard({
  change,
  isAdmin,
  refresh,
}: {
  change: Change;
  isAdmin: boolean;
  refresh: () => void;
}) {
  const current = useApi<{ data: ManagedListing }>(`/api/v1/admin/listings/${change.listing_id}`);
  const before = current.data?.data;
  const proposal = change.proposed_data_json.data as ProposalData;
  const proposalListing: Listing = {
    ...proposal,
    media: undefined,
    id: change.listing_id,
    price_total_vnd: null,
    rating_average: null,
    review_count: 0,
    status: "PENDING_REVIEW",
  };
  const changes: { key: string; before: unknown; after: unknown }[] = [];

  if (before) {
    if (before.title !== proposal.title) changes.push({ key: "title", before: before.title, after: proposal.title });
    if (before.description !== proposal.description) changes.push({ key: "description", before: before.description, after: proposal.description });
    if (before.service_type !== proposal.service_type) changes.push({ key: "service_type", before: before.service_type, after: proposal.service_type });
    if (before.area_code !== proposal.area_code) changes.push({ key: "area_code", before: before.area_code, after: proposal.area_code });
    if (Number(before.price_vnd) !== Number(proposal.price_vnd) || before.price_unit !== proposal.price_unit) {
      changes.push({
        key: "price",
        before: { price_vnd: before.price_vnd, price_unit: before.price_unit },
        after: { price_vnd: proposal.price_vnd, price_unit: proposal.price_unit },
      });
    }

    const beforeRules = before.acceptance_rules_json ?? { species: [], sizes: [] };
    const afterRules = proposal.acceptance_rules_json ?? { species: [], sizes: [] };
    if (JSON.stringify(beforeRules.species ?? []) !== JSON.stringify(afterRules.species ?? [])) {
      changes.push({ key: "acceptance_species", before: beforeRules.species ?? [], after: afterRules.species ?? [] });
    }
    if (JSON.stringify(beforeRules.sizes ?? []) !== JSON.stringify(afterRules.sizes ?? [])) {
      changes.push({ key: "acceptance_sizes", before: beforeRules.sizes ?? [], after: afterRules.sizes ?? [] });
    }
    const beforeMedia = (before.media ?? []).map((media) => media.media_object_id);
    const afterMedia = (proposal.media ?? []).map((media) => media.media_object_id);
    if (JSON.stringify(beforeMedia) !== JSON.stringify(afterMedia)) changes.push({
      key: "media",
      before: { count: beforeMedia.length, removed: beforeMedia.filter((id) => !afterMedia.includes(id)) },
      after: { count: afterMedia.length, added: afterMedia.filter((id) => !beforeMedia.includes(id)) },
    });
  }

  return (
    <article className={styles.changeRequestCard}>
      <div className={styles.changeRequestHeading}>
        <span>ĐỀ XUẤT CHỈNH SỬA · DỊCH VỤ #{change.listing_id}</span>
        <small>Gửi lúc {change.requested_at ? new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }).format(new Date(change.requested_at)) : "—"}</small>
      </div>
      <section className={styles.changeDiff} aria-label="Nội dung thay đổi">
        <h3>Các thay đổi được đề xuất</h3>
        {current.loading && <p className={styles.changeDiffHint}>Đang tải thông tin dịch vụ hiện tại để đối chiếu…</p>}
        <ApiError error={current.error} />
        {before && changes.length > 0 && <div className={styles.changeDiffList}>
          {changes.map((item) => <div key={item.key} className={styles.changeDiffRow}>
            <strong>{labels[item.key]}</strong>
            <div><span className={styles.changeDiffBefore}><small>Trước</small><span>{describe(item.key, item.before)}</span></span>
              <span className={styles.changeDiffArrow} aria-hidden="true">→</span>
              <span className={styles.changeDiffAfter}><small>Đề xuất mới</small><span>{describe(item.key, item.after)}</span></span>
            </div>
          </div>)}
        </div>}
        {before && changes.length === 0 && <p className={styles.changeDiffHint}>Không phát hiện khác biệt giữa dữ liệu hiện tại và đề xuất.</p>}
        {!before && current.error !== undefined && <p className={styles.changeDiffHint}>Không tải được dữ liệu cũ; bên dưới là nội dung Sitter đề xuất.</p>}
      </section>
      {isAdmin
        ? <ModerationCard listing={proposalListing} path={`/api/v1/admin/listings/${change.listing_id}/change-requests/${change.id}`} refresh={refresh} />
        : <div className="app-card"><h3>{proposal.title}</h3><p>{proposal.description}</p><p>Support chỉ có quyền xem đề xuất này.</p></div>}
    </article>
  );
}
