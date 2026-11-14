import { PettelIcon, type PettelIconName } from "@/components/ui/pettel-icon";
import styles from "@/features/home/styles/home-services.module.css";
import Link from "next/link";

const services: {
  title: string;
  description: string;
  price: string;
  icon: PettelIconName;
  tone: string;
  href?: string;
}[] = [
  {
    title: "Gửi gắm qua đêm",
    description:
      "Bé ở lại ngôi nhà ấm cúng của Sitter, được chăm sóc cá nhân hoá như một thành viên trong gia đình.",
    price: "Từ 200.000đ/đêm",
    icon: "home",
    tone: "cyan",
    href: "/services?service_type=BOARDING",
  },
  {
    title: "Chăm sóc tại gia",
    description:
      "Sitter tới ở hoặc chăm sóc tại căn hộ của bạn, giúp bé giữ nguyên nhịp sống quen thuộc không bị stress.",
    price: "Từ 250.000đ/ngày",
    icon: "pin",
    tone: "orange",
  },
  {
    title: "Dắt cún đi dạo",
    description:
      "Các buổi vận động 30 đến 60 phút có lộ trình GPS trực tiếp, nước uống và vệ sinh cẩn thận dọc đường.",
    price: "Từ 80.000đ/buổi",
    icon: "walk",
    tone: "purple",
  },
  {
    title: "Ghé thăm định kỳ",
    description:
      "Ghé thăm 30–45 phút: cho ăn theo khẩu phần, thay nước, dọn khay cát cho mèo, chải lông và ôm ấp.",
    price: "Từ 95.000đ/lần",
    icon: "clock",
    tone: "blue",
  },
];

export function HomeServices() {
  return (
    <section className={styles.section} aria-labelledby="home-services-title">
      <div className={styles.container}>
        <div className={styles.heading}>
          <div>
            <p className={styles.eyebrow}>HỆ SINH THÁI TẬN TÂM</p>
            <h2 id="home-services-title">Dịch vụ phù hợp với từng thói quen của pet</h2>
          </div>
          <p className={styles.intro}>
            Linh hoạt theo nhu cầu: từ kỳ nghỉ dài ngày đến vài vòng dạo công viên trong tuần.
          </p>
        </div>
        <div className={styles.grid}>
          {services.map((service) => {
            const content = (
              <>
                <div className={styles.cardTop}>
                  <span className={`${styles.icon} ${styles[service.tone]}`}>
                    <PettelIcon name={service.icon} />
                  </span>
                  {!service.href && <span className={styles.comingSoon}>Sắp ra mắt</span>}
                </div>
                <h3>{service.title}</h3>
                <p className={styles.description}>{service.description}</p>
                <div className={`${styles.cardFooter} ${styles[service.tone]}`}>
                  <span>{service.price}</span>
                  <PettelIcon name="arrow" />
                </div>
              </>
            );
            return service.href ? (
              <Link
                key={service.title}
                href={service.href}
                className={`${styles.card} ${styles.available}`}
                aria-label={`${service.title} — tìm dịch vụ`}
              >
                {content}
              </Link>
            ) : (
              <article key={service.title} className={styles.card}>
                {content}
              </article>
            );
          })}
        </div>
        <p className={styles.note}>
          Mức giá tham khảo. Giá đặt lịch được hiển thị theo từng người chăm sóc.
        </p>
      </div>
    </section>
  );
}
