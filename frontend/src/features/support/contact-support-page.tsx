"use client";

import Link from "next/link";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { SiteHeader } from "@/components/layout/site-header";
import styles from "./contact-support-page.module.css";

const questions = [
  {
    question: "Làm thế nào để yêu cầu hỗ trợ khi bé gặp sự cố?",
    answer: "Gọi hotline để được hướng dẫn khẩn cấp. Nếu bé đang ở cùng Sitter, hãy gọi trực tiếp cho Sitter và trao đổi qua cuộc trò chuyện của đơn đặt.",
  },
  {
    question: "Quy trình hủy đơn và hoàn tiền diễn ra như thế nào?",
    answer: "Mở mục Đơn chăm sóc của tôi để xem trạng thái đơn và gửi yêu cầu. Thời gian và khoản hoàn tiền phụ thuộc vào trạng thái đơn cùng chính sách áp dụng.",
  },
  {
    question: "Nếu Sitter chưa trả lời tin nhắn thì phải làm sao?",
    answer: "Bạn có thể gửi lại tin nhắn trong cuộc trò chuyện của đơn. Nếu cần hỗ trợ thêm, hãy liên hệ đội ngũ PETTEL qua biểu mẫu hoặc email.",
  },
  {
    question: "Tôi có thể cập nhật thông tin tài khoản ở đâu?",
    answer: "Vào Hồ sơ chủ nuôi trong menu tài khoản để kiểm tra và cập nhật thông tin cá nhân.",
  },
];

export function ContactSupportPage() {
  function submitRequest(event: React.FormEvent<HTMLFormElement>) {
    event.preventDefault();
    const form = new FormData(event.currentTarget);
    const subject = `[PETTEL] ${String(form.get("topic"))}`;
    const body = [
      `Họ tên: ${String(form.get("name"))}`,
      `Số điện thoại / Zalo: ${String(form.get("phone"))}`,
      `Email: ${String(form.get("email"))}`,
      `Chủ đề: ${String(form.get("topic"))}`,
      "",
      String(form.get("message")),
    ].join("\n");
    window.location.href = `mailto:hotro@pettel.vn?subject=${encodeURIComponent(subject)}&body=${encodeURIComponent(body)}`;
  }

  return (
    <div className={styles.page}>
      <SiteHeader />
      <main className={styles.main}>
        <div className={styles.container}>
          <header className={styles.hero}>
            <span className={styles.eyebrow}><PettelIcon name="message" /> TRUNG TÂM TRỢ GIÚP &amp; HỖ TRỢ 24/7</span>
            <h1>Chúng tôi luôn ở đây để đồng hành cùng bạn &amp; thú cưng</h1>
            <p>Mọi thắc mắc về đơn chăm sóc, sự cố y tế khẩn cấp hoặc cần hỗ trợ tài khoản — đội ngũ chuyên viên bảo trợ PETTEL luôn sẵn sàng phục vụ.</p>
            <div className={styles.trustBadges}>
              <span><i className={styles.onlineDot} /> Trực tổng đài: 100% Hoạt động</span>
              <span><PettelIcon name="shield" /> Bảo trợ y tế tự động: PETTEL Care Max</span>
            </div>
          </header>

          <section className={styles.channels} aria-label="Các kênh liên hệ hỗ trợ">
            <article className={`${styles.channelCard} ${styles.emergency}`}>
              <div className={styles.cardTop}><span className={styles.badge}>✣ ƯU TIÊN SỐ 1 • PHỤC VỤ 24/7/365</span><span className={styles.cardIcon}><PettelIcon name="bell" /></span></div>
              <h2>Cấp cứu &amp; Khẩn cấp Y tế 24/7</h2>
              <p>Dành riêng khi bé gặp sự cố chấn thương, sốt, nhiễm độc hoặc các tình huống cần xử lý ngay trong thời gian lưu trú tại Sitter.</p>
              <div className={styles.infoBox}>
                <div className={styles.infoHeading}><span>HOTLINE CẤP CỨU MIỄN CƯỚC</span><b>● Kết nối &lt;30s</b></div>
                <a className={styles.hotline} href="tel:19006868">1900 6868</a>
                <small>✣ Liên kết 45+ Bệnh viện thú y tại ĐN, HCM &amp; HN</small>
              </div>
              <a className={styles.primaryButton} href="tel:19006868"><span>☎</span> Gọi cấp cứu thú y ngay</a>
            </article>

            <article className={`${styles.channelCard} ${styles.liveChat}`}>
              <div className={styles.cardTop}><span className={styles.badge}>● ĐANG TRỰC TUYẾN • PHẢN HỒI &lt; 2 PHÚT</span><span className={styles.cardIcon}><PettelIcon name="message" /></span></div>
              <h2>Chat Trực Tuyến với Tổng Đài Viên</h2>
              <p>Trao đổi trực tiếp về thông tin đơn giữ pet, hướng dẫn thanh toán, đổi lịch gửi bé hoặc hỗ trợ tài khoản chủ nuôi &amp; Sitter.</p>
              <div className={styles.infoBox}>
                <div className={styles.infoHeading}><span>KÊNH HỖ TRỢ ƯU TIÊN</span><b>Hỗ trợ tức thời</b></div>
                <div className={styles.infoLine}><span className={styles.miniIcon}><PettelIcon name="message" /></span><span><strong>Live Chat Trực Tiếp trên App / Web</strong><small>Hoặc nhắn tin nhanh qua Zalo Official Account</small></span></div>
                <div className={styles.infoLine}><PettelIcon name="clock" /><span>Thời gian hoạt động: 07:00 - 23:30 mỗi ngày</span></div>
              </div>
              <Link className={styles.primaryButton} href="/chat"><span>➤</span> Bắt đầu chat hỗ trợ</Link>
            </article>

            <article className={`${styles.channelCard} ${styles.emailSupport}`}>
              <div className={styles.cardTop}><span className={styles.badge}>▣ VĂN BẢN CHÍNH THỨC • 2 - 4 GIỜ</span><span className={styles.cardIcon}><PettelIcon name="mail" /></span></div>
              <h2>Hộp Thư Hỗ Trợ &amp; Đối Tác</h2>
              <p>Dành cho khiếu nại dịch vụ, hồ sơ bồi thường bảo hiểm, xuất hóa đơn VAT, đề xuất hợp tác phòng khám thú y hoặc SitterPro.</p>
              <div className={styles.infoBox}>
                <div className={styles.infoHeading}><span>ĐỊA CHỈ HÒM THƯ TIẾP NHẬN</span><b>Cam kết SLA</b></div>
                <div className={styles.emailLine}><span>Chăm sóc &amp; Khiếu nại:</span><a href="mailto:hotro@pettel.vn">hotro@pettel.vn</a></div>
                <div className={styles.emailLine}><span>Hợp tác &amp; Phòng khám:</span><a href="mailto:contact@pettel.vn">contact@pettel.vn</a></div>
                <small>✓ Cấp mã Ticket theo dõi tiến độ qua email</small>
              </div>
              <a className={styles.secondaryButton} href="mailto:hotro@pettel.vn"><PettelIcon name="mail" /> Gửi yêu cầu qua Email</a>
            </article>
          </section>

          <section className={styles.lowerGrid}>
            <div className={styles.formCard}>
              <div className={styles.sectionHeading}><span className={styles.headingIcon}><PettelIcon name="message" /></span><span><h2>Gửi Tin Nhắn Nhanh Cho Đội Ngũ Hỗ Trợ</h2><p>Điền thông tin và yêu cầu của bạn, chúng tôi sẽ liên hệ lại trong 15–30 phút.</p></span></div>
              <form className={styles.form} onSubmit={submitRequest}>
                <label>Họ và tên của bạn <b>*</b><input name="name" autoComplete="name" placeholder="VD: Nguyễn Nhật Minh" required /></label>
                <label>Số điện thoại / Zalo <b>*</b><input name="phone" type="tel" autoComplete="tel" placeholder="0905 xxx xxx" required /></label>
                <label>Email nhận phản hồi <b>*</b><input name="email" type="email" autoComplete="email" placeholder="email@example.com" required /></label>
                <label>Chủ đề cần hỗ trợ <b>*</b><select name="topic" defaultValue="Sự cố đặt lịch / Hủy đơn chăm sóc"><option>Sự cố đặt lịch / Hủy đơn chăm sóc</option><option>Hỗ trợ tài khoản</option><option>Sự cố y tế khẩn cấp</option><option>Khiếu nại dịch vụ</option><option>Hợp tác và đối tác</option><option>Khác</option></select></label>
                <label className={styles.messageField}>Nội dung chi tiết <b>*</b><textarea name="message" rows={5} placeholder="Vui lòng cung cấp mã đơn chăm sóc (nếu có) và mô tả ngắn gọn tình huống của bạn..." required /></label>
                <button className={styles.submitButton} type="submit"><PettelIcon name="mail" /> Gửi yêu cầu hỗ trợ</button>
                <small className={styles.formNote}>Khi gửi, ứng dụng email của bạn sẽ mở sẵn nội dung để gửi đến đội ngũ PETTEL.</small>
              </form>
            </div>

            <aside className={styles.faqCard}>
              <div className={styles.sectionHeading}><span className={`${styles.headingIcon} ${styles.faqIcon}`}><PettelIcon name="shield" /></span><span><h2>Câu Hỏi Thường Gặp</h2><p>Giải đáp nhanh các thắc mắc cốt lõi nhất</p></span></div>
              <div className={styles.questions}>{questions.map((item) => <details key={item.question} className={styles.question}><summary>{item.question}<PettelIcon name="chevron" /></summary><p>{item.answer}</p></details>)}</div>
              <div className={styles.moreHelp}>Chưa tìm thấy câu trả lời? <Link href="/owner-profile#history">Xem đơn chăm sóc của tôi <PettelIcon name="arrow" /></Link></div>
            </aside>
          </section>
        </div>
      </main>
    </div>
  );
}
