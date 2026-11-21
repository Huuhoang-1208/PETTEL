import { ListingPhoto } from "@/components/ui/listing-photo";
import { type Listing } from "@/lib/api/types";
import { areas, services, species } from "@/lib/constants";
import { apiUrl, money } from "@/lib/format";
import Link from "next/link";
import styles from "@/features/home/styles/home-listings.module.css";
import { PettelIcon } from "@/components/ui/pettel-icon";

export function HomeListingCard({ item }: { item: Listing }) {
  const photo = item.media?.[0];
  const demoPhoto = item.caregiver?.demo?.photo_file
    ? `/images/pet-sitters/${encodeURIComponent(item.caregiver.demo.photo_file)}`
    : null;

  return (
    <Link className={styles.card} href={`/detail?id=${item.id}`}>
      <div className={styles.photo}>
        <ListingPhoto
          sizes="(max-width: 700px) 100vw, (max-width: 1000px) 50vw, 33vw"
          src={
            demoPhoto ?? (photo ? apiUrl(photo.thumbnail_url) : "/images/pet_hotel_building.png")
          }
          alt={
            photo ? (item.caregiver?.display_name ?? item.title) : "Không gian chăm sóc thú cưng"
          }
        />
        <span className={styles.verified}>
          <PettelIcon name="shield" />{" "}
          {item.caregiver?.demo?.is_demo ? "Hồ sơ demo" : "Dịch vụ đã duyệt"}
        </span>
        <span className={styles.favorite} aria-hidden="true">
          <PettelIcon name="heart" />
        </span>
      </div>
      <div className={styles.cardContent}>
        <div className={styles.cardEyebrow}>
          {services[item.service_type] ?? "Dịch vụ chăm sóc"} · {areas[item.area_code] ?? "Đà Nẵng"}
        </div>
        <h3>{item.caregiver?.display_name ?? item.title}</h3>
        <p className={styles.description}>{item.description}</p>
        <p className={styles.acceptance}>
          {item.acceptance_rules_json.species.map((value) => (
            <span key={value}>
              <PettelIcon name={value === "DOG" ? "dog" : value === "CAT" ? "cat" : "paw"} />
              {species[value] ?? value}
            </span>
          ))}
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
