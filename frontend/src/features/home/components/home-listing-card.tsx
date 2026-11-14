/* eslint-disable @next/next/no-img-element -- Listing photos are served by the backend media endpoint. */
import { type Listing } from "@/lib/api/types";
import { areas, services, species } from "@/lib/constants";
import { apiUrl, money } from "@/lib/format";
import Link from "next/link";
import styles from "@/features/home/styles/home-listings.module.css";

export function HomeListingCard({ item }: { item: Listing }) {
  const photo = item.media?.[0];

  return (
    <Link className={styles.card} href={`/detail?id=${item.id}`}>
      <div className={styles.photo}>
        <img
          src={photo ? apiUrl(photo.thumbnail_url) : "/images/pet_hotel_building.png"}
          alt={photo ? item.title : "Không gian chăm sóc thú cưng"}
        />
        <span className={styles.verified}>✓ Đã xác minh</span>
        <span className={styles.favorite} aria-hidden="true">
          ♡
        </span>
      </div>
      <div className={styles.cardContent}>
        <div className={styles.cardEyebrow}>
          {services[item.service_type] ?? "Dịch vụ chăm sóc"} · {areas[item.area_code] ?? "Đà Nẵng"}
        </div>
        <h3>{item.title}</h3>
        <p className={styles.description}>{item.description}</p>
        <p className={styles.acceptance}>
          🐾{" "}
          {item.acceptance_rules_json.species.map((value) => species[value] ?? value).join(" · ")}
        </p>
        <div className={styles.cardBottom}>
          <span className={styles.rating}>
            <strong>{item.rating_average?.toFixed(1) ?? "Mới"}</strong>
            <span aria-hidden="true">★</span> ({item.review_count} đánh giá)
          </span>
          <strong className={styles.price}>
            {money(item.price_vnd)} <small>/ {item.price_unit === "HOUR" ? "giờ" : "đêm"}</small>
          </strong>
        </div>
      </div>
    </Link>
  );
}
