import Image from "next/image";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { type Listing } from "@/lib/api/types";
import styles from "./listing-location-card.module.css";

const areaNames: Record<string, string> = {
  DN_CENTER: "Trung tâm Đà Nẵng",
  DN_NORTH: "Bắc Đà Nẵng",
  DN_SOUTH: "Nam Đà Nẵng",
  DN_EAST: "Đông Đà Nẵng",
  DN_WEST: "Tây Đà Nẵng",
};

export function ListingLocationCard({ listing }: { listing: Listing }) {
  const area = areaNames[listing.area_code] ?? "Đà Nẵng";
  const sitter = listing.caregiver?.display_name ?? "Người chăm sóc";

  return (
    <section className={styles.card} aria-labelledby="listing-location-title">
      <header className={styles.header}>
        <div>
          <h2 id="listing-location-title">
            <PettelIcon name="pin" /> Vị trí &amp; Khu vực lân cận
          </h2>
          <p>Khu vực phục vụ: {area}, Đà Nẵng</p>
        </div>
        <span className={styles.radius}>Bản đồ khu vực · ~1,2 km</span>
      </header>

      <div className={styles.map}>
        <Image
          src="/images/danang_map.png"
          alt={`Bản đồ minh họa khu vực ${area}, Đà Nẵng`}
          fill
          sizes="(max-width: 760px) 100vw, 700px"
          className={styles.mapImage}
        />
        <div className={styles.marker}>
          <PettelIcon name="paw" />
          <span>{sitter}</span>
        </div>
        <span className={`${styles.nearby} ${styles.park}`}>
          <i /> Khu vực ven sông Hàn
        </span>
        <span className={`${styles.nearby} ${styles.vet}`}>
          <i /> Cơ sở thú y lân cận
        </span>
        <span className={styles.disclaimer}>
          Vị trí hiển thị ở mức khu vực; địa chỉ cụ thể do sitter xác nhận.
        </span>
      </div>
    </section>
  );
}
