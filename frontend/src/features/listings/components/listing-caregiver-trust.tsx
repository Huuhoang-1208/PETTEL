/* eslint-disable @next/next/no-img-element -- Use local caregiver demo portraits. */
import { PettelIcon } from "@/components/ui/pettel-icon";
import { type Listing } from "@/lib/api/types";
import { areas, services, sizes, species } from "@/lib/constants";
import styles from "./listing-caregiver-trust.module.css";

export function ListingCaregiverTrust({ listing }: { listing: Listing }) {
  const caregiver = listing.caregiver;
  const demo = caregiver?.demo;
  const caregiverName = caregiver?.display_name ?? "Người chăm sóc PETTEL";
  const avatar = demo?.photo_file
    ? `/images/pet-sitters/${encodeURIComponent(demo.photo_file)}`
    : "/images/pet-sitters/portrait-young-asian-woman-holding-600nw-2344067699.webp";
  const area = areas[listing.area_code] ?? "Đà Nẵng";
  const acceptedPets = listing.acceptance_rules_json.species.map((value) => species[value]).join(" · ");
  const acceptedSizes = listing.acceptance_rules_json.sizes.map((value) => sizes[value]).join(" · ");
  const amenities = demo?.amenities?.slice(0, 3).join(" · ");
  const verifiedCount = demo?.verified_details
    ? Object.values(demo.verified_details).filter(Boolean).length
    : 0;

  return (
    <section className={styles.card} aria-label="Hồ sơ và bảo chứng người chăm sóc">
      <header className={styles.profile}>
        <div className={styles.avatarWrap}>
          <img
            className={styles.avatar}
            src={avatar}
            alt={`Ảnh đại diện ${caregiverName}`}
            onError={(event) => {
              event.currentTarget.onerror = null;
              event.currentTarget.src = "/images/pet-sitters/portrait-young-asian-woman-holding-600nw-2344067699.webp";
            }}
          />
          <span className={styles.avatarCheck}><PettelIcon name="check" /></span>
        </div>
        <div className={styles.profileInfo}>
          <h2>Chăm sóc bởi {caregiverName}</h2>
          <p>Người chăm sóc PETTEL · Khu vực {area}</p>
          <div className={styles.profileMeta}>
            <span><PettelIcon name="clock" /> Hồ sơ hoạt động trên PETTEL</span>
            <i>•</i>
            <span><PettelIcon name="heart" /> {listing.review_count} gia đình đã đánh giá</span>
          </div>
        </div>
        {demo?.trust_level === "pro" && (
          <span className={styles.proBadge}><PettelIcon name="award" /> PETTEL Pro</span>
        )}
      </header>

      <div className={styles.features}>
        <article className={styles.feature}>
          <span className={styles.featureIcon}><PettelIcon name="paw" /></span>
          <div>
            <h3>Nhận chăm sóc {acceptedPets || "thú cưng"}</h3>
            <p>Kích thước phù hợp: {acceptedSizes || "trao đổi với người chăm sóc"}.</p>
          </div>
        </article>
        <article className={styles.feature}>
          <span className={styles.featureIcon}><PettelIcon name="camera" /></span>
          <div>
            <h3>Cập nhật trong ứng dụng</h3>
            <p>Theo dõi trao đổi và thông tin chăm sóc trong suốt lịch đặt.</p>
          </div>
        </article>
        <article className={styles.feature}>
          <span className={styles.featureIcon}><PettelIcon name="shield" /></span>
          <div>
            <h3>Hồ sơ đã xác thực</h3>
            <p>{verifiedCount || 5} hạng mục xác minh{demo?.confidence_score != null ? ` · Confidence Score ${demo.confidence_score}/100` : ""}.</p>
          </div>
        </article>
        <article className={styles.feature}>
          <span className={styles.featureIcon}><PettelIcon name="home" /></span>
          <div>
            <h3>{services[listing.service_type] ?? "Dịch vụ chăm sóc"}</h3>
            <p>{amenities || demo?.home_type || "Thông tin tiện nghi được chia sẻ trong hồ sơ dịch vụ."}</p>
          </div>
        </article>
      </div>

      <div className={styles.guarantee}>
        <span className={styles.guaranteeIcon}><PettelIcon name="shield" /></span>
        <div className={styles.guaranteeCopy}>
          <h3>Bảo chứng PETTEL Guarantee <small>Mặc định miễn phí</small></h3>
          <p>Bảo hiểm chi trả y tế cấp cứu tới <strong>20.000.000đ</strong> cùng đường dây nóng hỗ trợ thú y 24/7 trong suốt kỳ lưu trú.</p>
        </div>
        <details className={styles.guaranteeDetails}>
          <summary>Chi tiết bảo trợ <span aria-hidden="true">→</span></summary>
          <p>PETTEL hỗ trợ kết nối và hướng dẫn xử lý khi phát sinh tình huống cần chăm sóc thú y trong thời gian đặt dịch vụ.</p>
        </details>
      </div>
    </section>
  );
}
