"use client";

import { PettelIcon, type PettelIconName } from "@/components/ui/pettel-icon";
import styles from "@/features/home/styles/about.module.css";

export function HomeAbout() {
  return (
    <section id="ve-pettel" className={styles.about} aria-labelledby="about-title">
      <span className={styles.eyebrow}>GỬI GẮM YÊU THƯƠNG, ĐÓN VỀ AN TÂM</span>
      <h2 id="about-title">Chăm sóc bé cưng, dễ dàng hơn mỗi ngày</h2>
      <p className={styles.sectionIntro}>
        Từ lần tìm kiếm đầu tiên đến khi đón bé về, mọi thông tin được kết nối trong tài khoản
        PETTEL của bạn.
      </p>
      <div className={styles.steps}>
        {[
          {
            icon: "search",
            title: "Tìm người chăm phù hợp",
            text: "Chọn khu vực, dịch vụ và hồ sơ thú cưng để tìm lựa chọn phù hợp với bé.",
          },
          {
            icon: "calendar",
            title: "Đặt lịch rõ ràng, thuận tiện",
            text: "Kiểm tra lịch trống và báo giá, gửi yêu cầu rồi trao đổi với người chăm ngay trong đơn.",
          },
          {
            icon: "paw",
            title: "Theo dõi những ngày của bé",
            text: "Giữ liên lạc qua tin nhắn và xem nhật ký, hình ảnh chăm sóc được người chăm cập nhật.",
          },
        ].map((step, index) => (
          <article className={styles.step} key={step.title}>
            <div className={styles.stepTop}>
              <span className={styles.stepIcon}>
                <PettelIcon name={step.icon as PettelIconName} />
              </span>
              <span className={styles.stepNumber}>0{index + 1}</span>
            </div>
            <h3>{step.title}</h3>
            <p>{step.text}</p>
          </article>
        ))}
      </div>
    </section>
  );
}
