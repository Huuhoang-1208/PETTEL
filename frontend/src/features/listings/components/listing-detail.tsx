"use client";

/* eslint-disable @next/next/no-img-element -- Preserve existing API and demo image rendering. */
import { ApiError } from "@/components/ui/api-error";
import { RequireSession } from "@/features/auth/components/require-session";
import { BookingForm } from "@/features/listings/components/booking-form";
import { Review } from "@/features/listings/types";
import { ReportForm } from "@/features/reports/components/report-form";
import { useApi } from "@/hooks/use-api";
import { type Listing, type Page } from "@/lib/api/types";
import { areas, services, sizes, species } from "@/lib/constants";
import { apiUrl, dateTime, money } from "@/lib/format";
import { useState } from "react";

export function ListingDetail({ listing }: { listing: Listing }) {
  const [reviewPage, setReviewPage] = useState(1);
  const reviews = useApi<Page<Review>>(`/api/listings/${listing.id}/reviews?page=${reviewPage}`);
  const [notice, setNotice] = useState("");
  return (
    <>
      <img
        className="app-hero"
        src={
          listing.media?.[0] ? apiUrl(listing.media[0].image_url) : "/images/pet_hotel_building.png"
        }
        alt={listing.media?.[0] ? listing.title : "Ảnh minh họa dịch vụ"}
      />
      <div className="app-detail-grid">
        <article>
          <h1>{listing.title}</h1>
          <p>
            {services[listing.service_type]} · {areas[listing.area_code]}
          </p>
          <p>
            ★ {listing.rating_average ?? "Chưa có đánh giá"} · {listing.review_count} đánh giá
          </p>
          <p className="app-description">{listing.description}</p>
          <h2>Nhận chăm sóc</h2>
          <p>{listing.acceptance_rules_json.species.map((value) => species[value]).join(" · ")}</p>
          <p>
            Kích thước:{" "}
            {listing.acceptance_rules_json.sizes.map((value) => sizes[value]).join(" · ")}
          </p>
          <div className="app-actions">
            <button
              onClick={async () => {
                try {
                  await navigator.clipboard.writeText(window.location.href);
                  setNotice("Đã sao chép liên kết dịch vụ.");
                } catch {
                  setNotice("Bạn có thể sao chép địa chỉ trang để chia sẻ.");
                }
              }}
            >
              Chia sẻ liên kết
            </button>
          </div>
          <p role="status">{notice}</p>
          <h2>Đánh giá từ đơn đã hoàn thành</h2>
          <ApiError error={reviews.error} />
          {reviews.loading && <p>Đang tải đánh giá...</p>}
          {reviews.data?.data.map((review) => (
            <div className="app-card" key={review.id}>
              <strong>{review.rating} ★</strong> · {dateTime(review.created_at)}
              {review.is_demo && <span> · Dữ liệu minh họa</span>}
              <p>{review.body || "Không có nội dung đánh giá."}</p>
            </div>
          ))}
          {reviews.data?.data.length === 0 && <p>Chưa có đánh giá.</p>}
          <div className="app-actions">
            <button disabled={reviewPage <= 1} onClick={() => setReviewPage((value) => value - 1)}>
              Trước
            </button>
            <span>Trang {reviewPage}</span>
            <button
              disabled={reviewPage >= (reviews.data?.meta?.last_page ?? 1)}
              onClick={() => setReviewPage((value) => value + 1)}
            >
              Sau
            </button>
          </div>
          <ReportForm target={{ listing_id: listing.id }} />
        </article>
        <aside className="app-card app-booking">
          <h2>
            {money(listing.price_vnd)} / {listing.price_unit === "HOUR" ? "giờ" : "đêm"}
          </h2>
          <RequireSession next={`/detail?id=${listing.id}`}>
            <BookingForm listing={listing} />
          </RequireSession>
        </aside>
      </div>
    </>
  );
}
