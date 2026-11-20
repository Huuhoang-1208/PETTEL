"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import { Review } from "@/features/listings/types";
import { useApi } from "@/hooks/use-api";
import { type Listing, type Page } from "@/lib/api/types";
import { dateTime } from "@/lib/format";
import { useState } from "react";
import styles from "./listing-reviews-card.module.css";

type ReviewPage = Page<Review> & {
  rating_average: number | null;
  review_count: number;
  rating_distribution: Record<string, number>;
};

const starLabel: Record<string, string> = {
  DOG: "Gia đình nuôi chó",
  CAT: "Gia đình nuôi mèo",
  OTHER: "Gia đình nuôi thú cưng",
};

function initials(name: string): string {
  return name.split(/\s+/).map((part) => part.replace(".", "")[0]).join("").slice(0, 2).toUpperCase();
}

export function ListingReviewsCard({ listing }: { listing: Listing }) {
  const [page, setPage] = useState(1);
  const [previousItems, setPreviousItems] = useState<Review[]>([]);
  const reviews = useApi<ReviewPage>(`/api/v1/listings/${listing.id}/reviews?page=${page}&per_page=3`);

  const items = previousItems.concat(reviews.data?.data ?? []);

  const distribution = reviews.data?.rating_distribution ?? {};
  const distributionTotal = Object.values(distribution).reduce((sum, count) => sum + count, 0);
  const total = reviews.data?.review_count || listing.caregiver?.demo?.review_count || 0;
  const average = reviews.data?.rating_average ?? listing.caregiver?.demo?.rating_average ?? null;
  const hasMore = page < (reviews.data?.meta?.last_page ?? 1);

  return (
    <section className={styles.card} aria-labelledby="listing-reviews-title">
      <div className={styles.summary}>
        <div className={styles.overall}>
          <h2 id="listing-reviews-title">
            <PettelIcon name="star" /> {average?.toFixed(1) ?? "—"} / 5.0
            <span>({total.toLocaleString("vi-VN")} đánh giá xác thực)</span>
          </h2>
          <p>
            Đánh giá chỉ được gửi sau khi đơn chăm sóc đã hoàn thành.
            {listing.caregiver?.demo && " Điểm tổng hợp lấy từ hồ sơ mẫu; nhận xét có nhãn demo là dữ liệu minh họa."}
          </p>
        </div>
        <div className={styles.distribution} aria-label="Phân bố số sao đánh giá">
          {[5, 4, 3, 2, 1].map((rating) => {
            const count = distribution[String(rating)] ?? 0;
            const percent = distributionTotal > 0 ? Math.round((count / distributionTotal) * 100) : 0;
            return (
              <div className={styles.distributionRow} key={rating}>
                <span>{rating} <PettelIcon name="star" /></span>
                <div className={styles.track}>
                  <span style={{ width: `${percent}%` }} />
                </div>
                <small>{count}</small>
              </div>
            );
          })}
        </div>
      </div>

      <div className={styles.reviewList}>
        {reviews.loading && items.length === 0 && <p className={styles.empty}>Đang tải đánh giá...</p>}
        {Boolean(reviews.error) && <p className={styles.empty}>Chưa tải được đánh giá. Vui lòng thử lại sau.</p>}
        {!reviews.loading && !reviews.error && items.length === 0 && (
          <p className={styles.empty}>Chưa có đánh giá từ đơn đã hoàn thành.</p>
        )}
        {items.map((review) => (
          <article className={styles.review} key={review.id}>
            <div className={styles.reviewHeader}>
              <span className={styles.avatar} aria-hidden="true">{initials(review.reviewer_name)}</span>
              <div className={styles.author}>
                <strong>{review.reviewer_name}</strong>
                <span>{review.pet_species ? starLabel[review.pet_species] : "Khách hàng đã đặt dịch vụ"}</span>
              </div>
              <div className={styles.stars} aria-label={`${review.rating} trên 5 sao`}>
                {Array.from({ length: 5 }, (_, index) => <span key={index}>{index < review.rating ? "★" : "☆"}</span>)}
              </div>
            </div>
            {review.body && <p className={styles.body}>“{review.body}”</p>}
            <p className={styles.date}>
              Ngày gửi: {dateTime(review.created_at)} · Đơn đã hoàn thành
              {review.is_demo && <span> · Dữ liệu minh họa</span>}
            </p>
          </article>
        ))}
      </div>

      {hasMore && (
        <button className={styles.more} type="button" disabled={reviews.loading} onClick={() => {
          setPreviousItems(items);
          setPage(page + 1);
        }}>
          {reviews.loading ? "Đang tải..." : `Xem thêm ${Math.max(0, total - items.length)} đánh giá khác`}
        </button>
      )}
    </section>
  );
}
