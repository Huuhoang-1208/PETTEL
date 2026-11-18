"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { CaregiverDashboard } from "@/features/caregiver/components/caregiver-dashboard";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useState } from "react";
import styles from "./caregiver-page.module.css";

const incomeScenarios = [
  {
    id: "part-time",
    label: "Bán thời gian",
    title: "Giờ rảnh & Cuối tuần",
    description: "Dành cho nhân viên văn phòng, sinh viên chăm 1 bé vào cuối tuần hoặc dắt dạo 2 buổi/tuần.",
    days: "5 – 8 ngày/tháng",
    services: "1 mèo hoặc 1 cún nhỏ",
    rate: "180k – 220k/ngày",
    net: "2.800.000đ",
    range: "4.200.000đ",
    icon: "clock" as const,
    tone: "partTime",
  },
  {
    id: "freelance",
    label: "Tại gia / WFH",
    title: "Freelancer & Làm tại nhà",
    description: "Nhận chăm 1–2 bé đều đặn trong tuần mà không ảnh hưởng tới tiến độ công việc chính.",
    days: "12 – 16 ngày/tháng",
    services: "1–2 bé mèo/cún ngoan",
    rate: "220k – 260k/ngày",
    net: "6.500.000đ",
    range: "10.800.000đ",
    icon: "home" as const,
    popular: true,
    tone: "freelance",
  },
  {
    id: "pro",
    label: "Toàn thời gian",
    title: "Chuyên gia Sitter Pro",
    description: "Không gian rộng, sẵn sàng riêng, nhận 2–3 bé nội trú + dịch vụ tắm sấy, chải chuốt cơ bản.",
    days: "20 – 24 ngày/tháng",
    services: "Boarding & Daycare trọn gói",
    rate: "250k – 300k/ngày",
    net: "14.500.000đ",
    range: "22.000.000đ",
    icon: "award" as const,
    tone: "pro",
  },
];

const sitterBenefits = [
  { icon: "sliders" as const, tone: "teal", title: "Linh hoạt tuyệt đối", body: "Tự cài đặt lịch nhận theo tuần. Chọn loại thú cưng tương thích với không gian sống của bạn." },
  { icon: "shield" as const, tone: "orange", title: "PETTEL Guarantee", body: "Bạn không bao giờ phải gánh rủi ro viện phí. Quỹ bảo lãnh y tế thú cưng lên tới 500 triệu đồng chi trả trực tiếp cho các phòng khám thú y liên kết." },
  { icon: "user" as const, tone: "blue", title: "Khách hàng xác thực", body: "Tiếp cận phụ huynh thú cưng đã xác minh eKYC và số điện thoại. Tiền gửi được tạm giữ an toàn, thanh toán sòng phẳng." },
  { icon: "plus" as const, tone: "purple", title: "Hỗ trợ y tế 24/7", body: "Bác sĩ thú y trực tuyến trực chiến 24/7. Hỗ trợ đào tạo sơ cứu và hướng dẫn xử lý tình huống cho cún mèo." },
];

const sitterSteps = [
  { number: "1", tag: "5 phút", title: "Đăng ký & Nộp hồ sơ", body: "Điền thông tin kinh nghiệm nuôi pet, loại nhà ở (chung cư, nhà phố có sân) và các dịch vụ bạn muốn nhận chăm sóc." },
  { number: "2", tag: "Xác minh eKYC", title: "Duyệt danh tính & Không gian", body: "Chụp ảnh CCCD và video tour ngắn góc phòng nghỉ, rào chắn ban công để PETTEL đánh giá độ an toàn cho các bé." },
  { number: "3", tag: "Miễn phí 100%", title: "Đào tạo an toàn cơ bản", body: "Tham gia buổi hướng dẫn online 45 phút cùng bác sĩ thú y về dinh dưỡng, kỹ năng hòa nhập và sơ cấp cứu khẩn cấp." },
  { number: "4", tag: "Sẵn sàng", title: "Kích hoạt & Nhận đơn", body: "Hồ sơ hiển thị trên bản đồ PETTEL tại khu vực của bạn. Bắt đầu kết nối với phụ huynh và nhận thanh toán đầu tiên." },
];

const sitterStories = [
  {
    name: "Bảo Trâm",
    role: "UI/UX Freelancer • Hải Châu, Đà Nẵng",
    badge: "6 tháng tại PETTEL",
    quote: "Mình vừa làm việc từ xa vừa nhận chăm 1–2 bé mèo mỗi tuần. Thu nhập ổn định hơn 11 triệu đồng/tháng mà không gian làm việc tại nhà lúc nào cũng ngập tràn tiếng rừ rừ ấm áp. Quản lý lịch trên PETTEL cực kỳ tiện lợi!",
    note: "34 lượt chăm hoàn thành",
    trust: "Danh tính & Nhà đã duyệt",
    image: "/images/auth-pet-family.png",
    alt: "Bảo Trâm chăm sóc thú cưng tại nhà",
  },
  {
    name: "Minh Tuấn",
    role: "Huấn luyện viên Gym • Sơn Trà, Đà Nẵng",
    badge: "Top Sitter 2024",
    quote: "Mình mê vận động nên đăng ký thêm dịch vụ dắt cún đi dạo dọc biển Mỹ Khê vào sáng sớm và chiều tà. Hệ thống bảo hiểm y tế PETTEL Guarantee giúp mình an tâm 100%, không phải lo lắng về sự cố ngoài ý muốn.",
    note: "52 lượt dắt cún",
    trust: "5.0 Đánh giá tuyệt đối",
    image: "/images/pet-sitters/Labrador-retriever-sitting-on-the-couch-next-to-a-man-giving-a-high-five.jpg",
    alt: "Minh Tuấn vui chơi cùng một chú chó",
  },
];

export function CaregiverPage() {
  const { user, loading } = useSession();
  const [selectedScenario, setSelectedScenario] = useState("freelance");

  if (!loading && !user) {
    return (
      <>
        <SiteHeader />
        <main className={styles.publicPage}>
          <section className={styles.hero} aria-labelledby="caregiver-hero-title">
            <div className={styles.heroCopy}>
              <span className={styles.badge}><PettelIcon name="award" /> Mạng lưới Pet Sitter #1 tại Đà Nẵng &amp; Việt Nam</span>
              <h1 id="caregiver-hero-title">Biến tình yêu thú cưng thành <em>thu nhập linh hoạt</em> cùng PETTEL</h1>
              <p>Kiếm thêm từ <strong>8.000.000đ – 25.000.000đ/tháng</strong> bằng cách trông giữ bé cún, mèo tại căn hộ quen hoặc dắt cún đi dạo theo giờ rảnh rỗi. Được quỹ bảo lãnh y tế thú cưng đồng hành và đội ngũ chuyên gia hỗ trợ 24/7.</p>
              <div className={styles.actions}>
                <Link className={styles.primaryAction} href="/register?next=%2Fcaregiver%2Fmanage"><PettelIcon name="user" /> Đăng ký làm Sitter ngay</Link>
                <a className={styles.secondaryAction} href="#income-estimator"><PettelIcon name="sliders" /> Ước tính thu nhập của bạn</a>
              </div>
              <div className={styles.perks}>
                <div><span><PettelIcon name="clock" /></span><p><small>THỜI GIAN</small><strong>Tự chủ 100%</strong></p></div>
                <div><span><PettelIcon name="star" /></span><p><small>THANH TOÁN</small><strong>Hàng tuần</strong></p></div>
                <div><span><PettelIcon name="shield" /></span><p><small>BẢO HIỂM</small><strong>Đến 500 triệu</strong></p></div>
                <div><span><PettelIcon name="heart" /></span><p><small>CỘNG ĐỒNG</small><strong>160+ Sitter Pro</strong></p></div>
              </div>
            </div>
            <div className={styles.heroVisual}>
              <img src="/images/pet-sitters/Labrador-retriever-sitting-on-the-couch-next-to-a-man-giving-a-high-five.jpg" alt="Người chăm sóc vui vẻ chơi đùa và đập tay cùng chú chó" />
              <span className={styles.proBadge}><PettelIcon name="star" /> PETTEL Pro Sitter</span>
              <div className={styles.incomeCard}>
                <span className={styles.walletIcon} aria-hidden="true"><svg viewBox="0 0 24 24" fill="none"><path d="M4 7.5A2.5 2.5 0 0 1 6.5 5H19a1 1 0 0 1 1 1v2H6.5A2.5 2.5 0 0 1 4 5.5v12A2.5 2.5 0 0 0 6.5 20H20V9H6.5A2.5 2.5 0 0 1 4 6.5" stroke="currentColor" strokeWidth="1.8" strokeLinejoin="round"/><path d="M20 11h-4a2 2 0 0 0 0 4h4v-4Z" fill="currentColor"/><circle cx="16" cy="13" r=".65" fill="#fff"/></svg></span>
                <p><small>THU NHẬP TRUNG BÌNH THÁNG TRƯỚC</small><strong>16.850.000đ <small>/ tháng</small></strong></p>
                <span className={styles.growth}><svg viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M2 11.5 6 7.5l2.5 2.5L13 5.5M9.5 5.5H13v3.5" stroke="currentColor" strokeWidth="1.8" strokeLinecap="round" strokeLinejoin="round"/></svg> +18%</span>
              </div>
            </div>
          </section>
          <section className={styles.estimateSection} id="income-estimator" aria-labelledby="income-estimator-title">
            <div className={styles.estimateIntro}>
              <span>ƯỚC TÍNH TIỀM NĂNG</span>
              <h2 id="income-estimator-title">Bạn có thể kiếm được bao nhiêu với PETTEL?</h2>
              <p>Chọn lịch chăm sóc phù hợp để xem mức thu nhập ước tính mỗi tháng.</p>
            </div>
            <div className={styles.estimateCard}>
              <div className={styles.estimateHeader}>
                <div><h3>Mô phỏng thu nhập thực tế theo lối sống của bạn</h3><p>Dựa trên đơn giá tham khảo và lịch nhận chăm sóc phổ biến tại Đà Nẵng.</p></div>
                <span className={styles.transparency}><PettelIcon name="shield" /> Số liệu minh họa</span>
              </div>
              <div className={styles.scenarioGrid} role="group" aria-label="Chọn lịch chăm sóc">
                {incomeScenarios.map((scenario) => <button
                  className={`${styles.scenario} ${styles[scenario.tone]} ${selectedScenario === scenario.id ? styles.selected : ""}`}
                  type="button"
                  key={scenario.id}
                  aria-pressed={selectedScenario === scenario.id}
                  onClick={() => setSelectedScenario(scenario.id)}
                >
                  <span className={styles.scenarioTop}><small>{scenario.label}</small><PettelIcon name={scenario.icon} /></span>
                  {scenario.popular && <span className={styles.popular}>Phổ biến nhất</span>}
                  <strong className={styles.scenarioTitle}>{scenario.title}</strong>
                  <span className={styles.scenarioDescription}>{scenario.description}</span>
                  <span className={styles.scenarioDetails}><span>Thời gian:</span><b>{scenario.days}</b></span>
                  <span className={styles.scenarioDetails}><span>Dịch vụ:</span><b>{scenario.services}</b></span>
                  <span className={styles.scenarioDetails}><span>Đơn giá trung bình:</span><b>{scenario.rate}</b></span>
                  <span className={styles.netLabel}>THỰC NHẬN SAU PHÍ 15%:</span>
                  <span className={styles.netIncome}>{scenario.net}<small> – {scenario.range}</small></span>
                </button>)}
              </div>
              <div className={styles.estimateFooter}>
                <p><PettelIcon name="shield" /><span><strong>Phí dịch vụ 15% bao gồm những gì?</strong><small>Bảo hiểm thú cưng PETTEL Care, kết nối khách hàng đã xác minh và đội ngũ hỗ trợ đồng hành. Thu nhập thực tế thay đổi theo lịch nhận chăm sóc.</small></span></p>
                <Link href="/register?next=%2Fcaregiver%2Fmanage">Bắt đầu tạo hồ sơ Sitter <PettelIcon name="arrow" /></Link>
              </div>
            </div>
          </section>
          <section className={styles.benefitsSection} aria-labelledby="sitter-benefits-title">
            <div className={styles.followupIntro}>
              <span>ĐẶC QUYỀN SITTER</span>
              <h2 id="sitter-benefits-title">Vì sao hàng trăm người yêu pet chọn đồng hành cùng PETTEL?</h2>
            </div>
            <div className={styles.benefitCards}>
              {sitterBenefits.map((benefit) => <article className={`${styles.benefitCard} ${styles[benefit.tone]}`} key={benefit.title}>
                <span className={styles.benefitIcon}><PettelIcon name={benefit.icon} /></span>
                <h3>{benefit.title}</h3>
                <p>{benefit.body}</p>
              </article>)}
            </div>
          </section>
          <section className={styles.journeySection} aria-labelledby="sitter-journey-title">
            <div className={styles.followupIntro}>
              <span>LỘ TRÌNH GIA NHẬP</span>
              <h2 id="sitter-journey-title">4 bước đơn giản để trở thành Sitter Pro</h2>
              <p>Quy trình tuyển chọn minh bạch, tôn trọng và bảo đảm tiêu chuẩn an toàn cho thú cưng.</p>
            </div>
            <div className={styles.journeyCards}>
              {sitterSteps.map((step) => <article className={styles.journeyCard} key={step.number}>
                <div className={styles.journeyTop}><span>{step.number}</span><small>{step.tag}</small></div>
                <h3>{step.title}</h3>
                <p>{step.body}</p>
              </article>)}
            </div>
          </section>
          <section className={styles.storiesSection} aria-labelledby="sitter-stories-title">
            <div className={styles.storiesHeading}>
              <div className={styles.followupIntro}>
                <span>CÂU CHUYỆN NGƯỜI THẬT VIỆC THẬT</span>
                <h2 id="sitter-stories-title">Cảm nhận từ những người bạn Sitter đồng hành</h2>
              </div>
              <p className={styles.aggregateRating}><span aria-hidden="true">★★★★★</span><strong>4.96/5.0</strong> (từ 1.200+ đánh giá)</p>
            </div>
            <div className={styles.storyCards}>
              {sitterStories.map((story) => <article className={styles.storyCard} key={story.name}>
                <img src={story.image} alt={story.alt} />
                <div className={styles.storyContent}>
                  <div className={styles.storyAuthor}>
                    <div><h3>{story.name}</h3><p>{story.role}</p></div>
                    <span>{story.badge}</span>
                  </div>
                  <blockquote>“{story.quote}”</blockquote>
                  <div className={styles.storyStats}><span><PettelIcon name="paw" /> {story.note}</span><span><PettelIcon name="shield" /> {story.trust}</span></div>
                </div>
              </article>)}
            </div>
          </section>
          <section className={styles.applicationSection} aria-labelledby="sitter-application-title">
            <div className={styles.applicationCard}>
              <span className={styles.applicationIcon}><PettelIcon name="paw" /></span>
              <h2 id="sitter-application-title">Sẵn sàng khởi đầu hành trình cùng PETTEL?</h2>
              <p className={styles.applicationLead}>Tạo tài khoản miễn phí chỉ trong 60 giây. Đội ngũ điều phối PETTEL sẽ liên hệ tư vấn và khảo sát hồ sơ của bạn trong vòng 24 giờ.</p>
              <form className={styles.applicationForm} onSubmit={(event) => { event.preventDefault(); window.location.assign("/register?next=%2Fcaregiver%2Fmanage"); }}>
                <label>Họ và tên của bạn <b>*</b><input name="name" required maxLength={100} autoComplete="name" placeholder="Ví dụ: Nguyễn Văn An" /></label>
                <label>Số điện thoại liên hệ <b>*</b><input name="phone" required type="tel" autoComplete="tel" pattern="[0-9+(). -]{9,16}" placeholder="Ví dụ: 0905 123 456" /></label>
                <label>Khu vực bạn sinh sống <b>*</b><select name="area" required defaultValue=""><option value="" disabled>Chọn Quận/Huyện tại Đà Nẵng</option><option>Hải Châu</option><option>Sơn Trà</option><option>Thanh Khê</option><option>Ngũ Hành Sơn</option><option>Cẩm Lệ</option><option>Liên Chiểu</option><option>Hòa Vang</option></select></label>
                <label>Dịch vụ bạn quan tâm nhất<select name="service" defaultValue="all"><option value="all">Tất cả dịch vụ (Trông qua đêm, Daycare, Dắt dạo)</option><option value="boarding">Trông giữ qua đêm</option><option value="daycare">Daycare</option><option value="walk">Dắt chó đi dạo</option></select></label>
                <button type="submit"><PettelIcon name="arrow" /> Gửi hồ sơ đăng ký Sitter</button>
              </form>
              <p className={styles.applicationLogin}>Đã có tài khoản PETTEL? <Link href="/login?next=%2Fcaregiver%2Fmanage">Đăng nhập để liên kết ngay</Link></p>
            </div>
          </section>
        </main>
      </>
    );
  }

  return (
    <>
      <SiteHeader />
      <main className="app-container">
        <h1>Dành cho người chăm thú cưng</h1>
        {loading ? <p className="app-notice">Đang kiểm tra phiên đăng nhập...</p> : <CaregiverDashboard />}
      </main>
    </>
  );
}
