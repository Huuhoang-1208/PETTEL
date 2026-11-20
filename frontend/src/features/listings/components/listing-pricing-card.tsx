"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import { useApi } from "@/hooks/use-api";
import { type Listing, type Page } from "@/lib/api/types";
import styles from "./listing-pricing-card.module.css";

const pricePresentation: Record<string, { title: string; description: string; icon: "home" | "clock" }> = {
  BOARDING: {
    title: "Nội trú qua đêm (Pet Boarding)",
    description: "Bé nghỉ tại không gian của sitter, có thời gian ăn, nghỉ và cập nhật theo lịch chăm sóc.",
    icon: "home",
  },
  DAYCARE: {
    title: "Nhà trẻ ban ngày (Doggy Daycare)",
    description: "Chăm sóc bé trong ngày; gia đình thống nhất thời gian gửi và đón với sitter.",
    icon: "clock",
  },
};

function formatPrice(price: number): string {
  return `${price.toLocaleString("vi-VN")}đ`;
}

export function ListingPricingCard({ listing }: { listing: Listing }) {
  const response = useApi<Page<Listing>>(`/api/v1/listings/${listing.id}/services`);
  const offers = response.data?.data ?? [listing];
  const orderedOffers = [
    ...offers.filter((offer) => offer.service_type === "BOARDING"),
    ...offers.filter((offer) => offer.service_type === "DAYCARE"),
  ];

  return (
    <section className={styles.card} aria-labelledby="listing-pricing-title">
      <header className={styles.header}>
        <div>
          <h2 id="listing-pricing-title">
            <PettelIcon name="calendar" /> Dịch vụ &amp; Bảng giá minh bạch
          </h2>
          <p>Không phụ phí ẩn · Đã bao gồm bảo hiểm PETTEL Care</p>
        </div>
        <span className={styles.standard}>Niêm yết tiêu chuẩn</span>
      </header>

      <div className={styles.offers}>
        {orderedOffers.map((offer, index) => {
          const presentation = pricePresentation[offer.service_type];
          if (!presentation) return null;
          const isCurrent = offer.id === listing.id;
          const unit = offer.price_unit === "HOUR" ? "giờ" : "ngày (24h)";

          return (
            <article className={`${styles.offer} ${isCurrent ? styles.current : ""}`} key={offer.id}>
              <span className={`${styles.icon} ${offer.service_type === "DAYCARE" ? styles.daycare : ""}`}>
                <PettelIcon name={presentation.icon} />
              </span>
              <div className={styles.details}>
                <h3>
                  {index + 1}. {presentation.title}
                  {isCurrent && <span className={styles.popular}>Đang xem</span>}
                </h3>
                <p>{presentation.description}</p>
              </div>
              <div className={styles.price}>
                <strong>{formatPrice(offer.price_vnd)}</strong>
                <span>/ {unit}</span>
              </div>
            </article>
          );
        })}
        {listing.caregiver?.demo && (
          <article className={styles.offer}>
            <span className={`${styles.icon} ${styles.walking}`}>
              <PettelIcon name="walk" />
            </span>
            <div className={styles.details}>
              <h3>
                {orderedOffers.length + 1}. Dắt cún đi dạo chuyên nghiệp (Dog Walking)
                <span className={styles.reference}>Giá tham khảo</span>
              </h3>
              <p>Đi dạo khoảng 45 phút; lịch và phạm vi đón bé được xác nhận trước với sitter.</p>
            </div>
            <div className={styles.price}>
              <strong>120.000đ</strong>
              <span>/ lượt 45 phút</span>
            </div>
          </article>
        )}
      </div>

      <div className={styles.addons}>
        <strong><PettelIcon name="plus" /> Dịch vụ bổ trợ theo yêu cầu:</strong>
        <div className={styles.addonList}>
          <span>🚕 Pet Taxi đưa đón tận nơi · báo giá trước</span>
          <span>🛁 Tắm và chải lông · báo giá trước</span>
        </div>
      </div>
    </section>
  );
}
