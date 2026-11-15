import Link from "next/link";
import { PettelIcon } from "@/components/ui/pettel-icon";
import styles from "./guest-pet-profile.module.css";

const benefits = [
  {
    icon: "heart" as const,
    tone: "teal",
    title: "Sitter hiểu bé ngay từ ngày đầu",
    body: "Chia sẻ tính cách, thói quen và lưu ý chăm sóc để người chăm chuẩn bị chu đáo hơn cho từng bé.",
    note: "Chăm sóc theo thói quen riêng",
  },
  {
    icon: "shield" as const,
    tone: "orange",
    title: "Lưu thông tin sức khỏe tiện lợi",
    body: "Ghi lại thông tin tiêm chủng và những lưu ý quan trọng để bạn dễ dàng trao đổi khi đặt dịch vụ.",
    note: "Thông tin của bé luôn trong tầm tay",
  },
  {
    icon: "calendar" as const,
    tone: "purple",
    title: "Tìm dịch vụ phù hợp hơn",
    body: "Hồ sơ đầy đủ giúp bạn chia sẻ nhu cầu chăm sóc rõ ràng khi tìm và đặt lịch với Sitter.",
    note: "Bắt đầu tìm Sitter phù hợp",
  },
];

const steps = [
  { number: "01", title: "Đăng nhập tài khoản", body: "Đăng nhập hoặc tạo tài khoản PETTEL để bắt đầu quản lý thông tin của bé." },
  { number: "02", title: "Thêm thông tin thú cưng", body: "Lưu ảnh, giống loài, thói quen và các lưu ý sức khỏe của bé." },
  { number: "03", title: "Sẵn sàng tìm Sitter", body: "Chọn dịch vụ phù hợp và chia sẻ hồ sơ với người chăm khi cần." },
];

export function GuestPetProfile() {
  return (
    <main className={styles.page}>
      <section className={styles.hero}>
        <div className={styles.heroInner}>
          <div className={styles.breadcrumb}><Link href="/">Trang chủ</Link><span>›</span><strong>Hồ sơ thú cưng</strong><span className={styles.guestBadge}>CHẾ ĐỘ KHÁCH</span></div>
          <span className={styles.eyebrow}><PettelIcon name="paw" /> HỒ SƠ RIÊNG CHO BÉ CƯNG</span>
          <h1>Tạo hồ sơ thú cưng — bước đầu để bé được <em>chăm sóc chu đáo</em></h1>
          <p>Lưu lại thói quen sinh hoạt, thông tin sức khỏe và ghi chú ăn uống để Sitter hiểu bé hơn và chăm sóc phù hợp khi bạn vắng nhà.</p>
          <div className={styles.heroActions}>
            <Link className={styles.primaryButton} href="/login?next=%2Fpet-profile"><PettelIcon name="paw" /> Đăng nhập / Đăng ký ngay</Link>
            <Link className={styles.secondaryButton} href="/services"><PettelIcon name="search" /> Khám phá Sitter gần bạn</Link>
          </div>
          <div className={styles.promises}>
            <span><PettelIcon name="check" /> Tạo hồ sơ miễn phí</span>
            <span><PettelIcon name="shield" /> Thông tin được bảo vệ</span>
            <span><PettelIcon name="heart" /> Lưu trữ hồ sơ online</span>
          </div>
        </div>
      </section>

      <section className={styles.benefitsSection}>
        <div className={styles.sectionIntro}><span>LỢI ÍCH DÀNH CHO BÉ</span><h2>Tại sao nên tạo hồ sơ thú cưng trên PETTEL?</h2><p>Thông tin của bé giúp bạn chia sẻ nhu cầu chăm sóc dễ dàng và rõ ràng hơn.</p></div>
        <div className={styles.benefitGrid}>
          {benefits.map((benefit) => <article className={`${styles.benefitCard} ${styles[benefit.tone]}`} key={benefit.title}>
            <span className={styles.benefitIcon}><PettelIcon name={benefit.icon} /></span>
            <h3>{benefit.title}</h3>
            <p>{benefit.body}</p>
            <small><PettelIcon name={benefit.icon} /> {benefit.note}</small>
          </article>)}
        </div>
      </section>

      <section className={styles.stepsSection}>
        <div className={styles.sectionIntro}><span>BẮT ĐẦU THẬT DỄ DÀNG</span><h2>3 bước để hoàn thiện hồ sơ</h2><p>Bạn có thể cập nhật thông tin của bé bất cứ lúc nào.</p></div>
        <div className={styles.stepsGrid}>
          {steps.map((step) => <article className={styles.stepCard} key={step.number}><span>{step.number}</span><h3>{step.title}</h3><p>{step.body}</p></article>)}
        </div>
      </section>

      <section className={styles.joinSection}>
        <div className={styles.joinInner}>
          <div className={styles.joinCopy}>
            <span className={styles.joinEyebrow}><PettelIcon name="heart" /> ĐỒNG HÀNH CÙNG GIA ĐÌNH PETTEL</span>
            <h2>Mỗi bé đều xứng đáng có một hồ sơ chăm sóc riêng</h2>
            <p>Tạo tài khoản miễn phí để lưu thông tin của bé, tìm Sitter và quản lý các đơn chăm sóc tại một nơi.</p>
            <div className={styles.joinCheck}><PettelIcon name="check" /> Bắt đầu tạo hồ sơ chỉ với vài thông tin cơ bản</div>
          </div>
          <div className={styles.joinCard}>
            <span className={styles.joinIcon}><PettelIcon name="paw" /></span>
            <h3>Đăng nhập hoặc đăng ký</h3>
            <p>Tiếp tục để tạo hồ sơ thú cưng của bạn.</p>
            <Link className={styles.primaryButton} href="/login?next=%2Fpet-profile">Đăng nhập PETTEL <PettelIcon name="arrow" /></Link>
            <div className={styles.divider}><span>hoặc</span></div>
            <p className={styles.registerLine}>Chưa có tài khoản? <Link href="/register?next=%2Fpet-profile">Đăng ký tài khoản mới</Link></p>
          </div>
        </div>
      </section>

    </main>
  );
}
