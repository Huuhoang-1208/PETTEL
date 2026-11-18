"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import styles from "@/features/home/styles/about.module.css";

export function HomeAbout() {
  return (
    <section id="ve-pettel" className={styles.about} aria-labelledby="about-title">
      <div className={styles.heading}>
        <span className={styles.eyebrow}>QUY TRÌNH AN TÂM</span>
        <h2 id="about-title">Gửi gắm bé yêu chỉ với 4 bước minh bạch</h2>
        <p className={styles.sectionIntro}>
          Trải nghiệm liền mạch, đảm bảo bé cưng luôn an toàn và bạn được cập nhật từng khoảnh khắc.
        </p>
      </div>
      <div className={styles.steps}>
        {[
          {
            title: "Tìm & Lọc Sitter",
            text: "Tra cứu sitter theo bán kính vị trí, xem ảnh nhà thực tế, đánh giá cộng đồng và chứng chỉ chăm sóc đã kiểm duyệt.",
            tone: "cyan",
          },
          {
            title: "Meet & Greet Miễn phí",
            text: "Nhắn tin trao đổi thói quen của bé và hẹn gặp mặt làm quen trực tiếp trước khi quyết định gửi gắm.",
            tone: "orange",
          },
          {
            title: "Đặt lịch & Ký gửi an toàn",
            text: "Thanh toán qua ví bảo chứng PETTEL Escrow. Tiền chỉ chuyển cho Sitter khi dịch vụ hoàn tất chu đáo 100%.",
            tone: "purple",
          },
          {
            title: "Cập nhật ảnh & video",
            text: "Nhận thông báo, ảnh bé ăn uống, vui chơi và báo cáo định vị trực tiếp lên ứng dụng mỗi 2 đến 4 tiếng.",
            tone: "teal",
          },
        ].map((step, index) => (
          <article className={styles.step} key={step.title}>
            <span className={`${styles.stepNumber} ${styles[step.tone]}`}>0{index + 1}</span>
            <h3>{step.title}</h3>
            <p>{step.text}</p>
          </article>
        ))}
      </div>
      <div className={styles.trustPanel}>
        <div className={styles.trustCopy}>
          <span className={styles.trustEyebrow}>
            <PettelIcon name="shield" /> QUY TRÌNH KIỂM DUYỆT GẮT GAO
          </span>
          <h3>Chỉ 12% ứng viên vượt qua kỳ sát hạch PETTEL Pro</h3>
          <p>
            Để trở thành Sitter trên nền tảng, mọi ứng viên phải trải qua: Xác minh căn cước công
            dân sinh trắc học, kiểm tra điều kiện an toàn căn hộ, bài thi kỹ năng chăm sóc sơ cứu y
            tế động vật cơ bản, cùng buổi phỏng vấn trực tiếp từ chuyên gia dành riêng cho thú cưng.
          </p>
          <div className={styles.trustChecks}>
            <span>
              <PettelIcon name="check" /> Xác thực CCCD
            </span>
            <span>
              <PettelIcon name="home" /> Khảo sát nơi ở
            </span>
            <span>
              <PettelIcon name="plus" /> Test sơ cứu Vet
            </span>
            <span>
              <PettelIcon name="shield" /> Bảo hiểm 20tr
            </span>
          </div>
        </div>
        <aside className={styles.guarantee} aria-label="PETTEL Care Guarantee">
          <span className={styles.guaranteeIcon}>
            <PettelIcon name="star" />
          </span>
          <h4>PETTEL Care Guarantee</h4>
          <p>
            Mọi đơn gửi pet đều tự động kích hoạt gói bảo trợ rủi ro y tế và hỗ trợ khẩn cấp 24/7
            toàn quốc.
          </p>
          <strong>20.000.000 VNĐ</strong>
        </aside>
      </div>
    </section>
  );
}
