import Image from "next/image";
import { PettelIcon } from "@/components/ui/pettel-icon";
import styles from "../styles/showcase.module.css";
export function AuthShowcase() {
  return (
    <aside className={styles.showcase} aria-label="Chăm sóc thú cưng cùng PETTEL">
      <span className={styles.communityBadge}>
        <PettelIcon name="shield" /> KẾT NỐI YÊU THƯƠNG CÙNG PETTEL
      </span>
      <h1>
        Ngôi nhà thứ hai
        <br />
        <em>ấm áp & an tâm</em> cho bé cưng
      </h1>
      <p className={styles.showcaseIntro}>
        Tìm người chăm phù hợp để bé luôn được yêu thương, ngay cả khi bạn bận rộn hay vắng nhà.
      </p>
      <div className={styles.photo}>
        <Image
          src="/images/auth-pet-family.png"
          alt="Người chăm vui đùa cùng chú chó Golden và mèo trong ngôi nhà ấm cúng"
          fill
          priority
          sizes="(max-width: 760px) 100vw, 480px"
        />
        <span className={styles.photoBadge}>
          <span /> Nhật ký chăm sóc & hình ảnh
        </span>
        <div className={styles.photoCaption}>
          <span className={styles.captionIcon}>
            <PettelIcon name="paw" />
          </span>
          <div>
            <strong>Chăm sóc từ những điều nhỏ nhất</strong>
            <span>Mỗi bé cưng, một sự quan tâm riêng.</span>
          </div>
          <PettelIcon name="heart" />
        </div>
      </div>
      <div className={styles.benefits}>
        <div>
          <span>
            <PettelIcon name="shield" />
          </span>
          <p>
            <strong>Đặt lịch rõ ràng</strong>
            <small>Xem báo giá trước khi đặt</small>
          </p>
        </div>
        <div>
          <span>
            <PettelIcon name="home" />
          </span>
          <p>
            <strong>Chăm sóc phù hợp</strong>
            <small>Theo nhu cầu của từng bé</small>
          </p>
        </div>
      </div>
      <div className={styles.showcaseBottom}>
        <div className={styles.petMarks}>
          <span>
            <PettelIcon name="paw" />
          </span>
          <span>
            <PettelIcon name="heart" />
          </span>
          <span>
            <PettelIcon name="shield" />
          </span>
        </div>
        <p>
          Yêu thương bé,
          <br />
          <strong>an tâm mỗi ngày.</strong>
        </p>
      </div>
    </aside>
  );
}
