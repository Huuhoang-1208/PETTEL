import { PettelIcon } from "@/components/ui/pettel-icon";
import { type Listing } from "@/lib/api/types";
import styles from "./listing-space-card.module.css";

const fallbackAmenities = [
  { title: "Không gian chăm sóc", description: "Trao đổi với sitter để xem ảnh và xác nhận bố trí thực tế." },
  { title: "Tiện nghi cho thú cưng", description: "Hỏi sitter về các tiện nghi đang có trong hồ sơ." },
  { title: "Khu vực sinh hoạt", description: "Thống nhất khu vực bé sẽ sử dụng trong thời gian lưu trú." },
  { title: "Đồ dùng và thức ăn", description: "Gia đình có thể gửi đồ dùng quen thuộc của bé." },
  { title: "Vệ sinh không gian", description: "Trao đổi lịch vệ sinh khu vực chăm sóc với sitter." },
  { title: "Hỗ trợ thú y lân cận", description: "Nhắn sitter để xác nhận cơ sở thú y gần nhất." },
];

const icons = ["home", "camera", "paw", "shield", "check", "pin"] as const;

export function ListingSpaceCard({ listing }: { listing: Listing }) {
  const amenities = listing.caregiver?.demo?.space_amenities?.length
    ? listing.caregiver.demo.space_amenities.slice(0, 6)
    : fallbackAmenities;
  const homeType = listing.caregiver?.demo?.home_type?.toLowerCase();

  return (
    <section className={styles.card} aria-labelledby="listing-space-title">
      <h2 id="listing-space-title">
        <PettelIcon name="home" /> Không gian {homeType ?? "chăm sóc"} &amp; Tiện ích an toàn
      </h2>
      <div className={styles.grid}>
        {amenities.map((amenity, index) => (
          <article className={styles.amenity} key={`${amenity.title}-${index}`}>
            <PettelIcon name={icons[index] ?? "check"} />
            <div>
              <h3>{amenity.title}</h3>
              <p>{amenity.description}</p>
            </div>
          </article>
        ))}
      </div>
    </section>
  );
}
