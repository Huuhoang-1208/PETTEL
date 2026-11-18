import styles from "@/features/home/styles/home-caregiver-cta.module.css";
import Image from "next/image";
import Link from "next/link";

export function HomeCaregiverCta() {
  return (
    <section className={styles.section} aria-labelledby="caregiver-cta-title">
      <div className={styles.banner}>
        <div className={styles.content}>
          <span className={styles.eyebrow}>GIA NHẬP CỘNG ĐỒNG PETTEL</span>
          <h2 id="caregiver-cta-title">Yêu động vật & muốn có thêm thu nhập?</h2>
          <p>
            Trở thành Pet Sitter cùng PETTEL ngay hôm nay. Tự do sắp xếp thời gian làm việc, nhận
            bảo hiểm bảo vệ toàn diện và cơ hội thu nhập lên đến{" "}
            <strong>15.000.000 VNĐ/tháng</strong> từ niềm đam mê chăm sóc thú cưng.
          </p>
          <div className={styles.actions}>
            <Link className={styles.primaryAction} href="/caregiver/manage">
              Đăng ký làm Sitter
            </Link>
            <Link className={styles.secondaryAction} href="/caregiver/manage">
              Tìm hiểu quyền lợi & thu nhập <span aria-hidden="true">›</span>
            </Link>
          </div>
        </div>
        <div className={styles.imageWrap}>
          <Image
            src="/images/auth-pet-family.png"
            alt="Người chăm sóc vui vẻ bên cạnh chó và mèo trong ngôi nhà ấm cúng"
            fill
            sizes="(max-width: 720px) 100vw, 36vw"
          />
          <span className={styles.incomeBadge}>
            <span aria-hidden="true">🐾</span>
            Thu nhập tiềm năng <strong>8–15 triệu/tháng</strong>
          </span>
        </div>
      </div>
    </section>
  );
}
