import Image from "next/image";
import Link from "next/link";
import { SiteHeader } from "@/components/layout/site-header";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { pillars, values } from "./about-content";
import styles from "./about.module.css";

export function AboutPage() {
  return <div className={styles.page}>
    <SiteHeader />
    <main>
      <div className={styles.container}>
        <section className={styles.hero} aria-labelledby="about-heading">
          <span className={styles.eyebrow}><i /> VỀ PETTEL • CÂU CHUYỆN CỦA CHÚNG TÔI</span>
          <h1 id="about-heading">Nơi thú cưng được chăm sóc chu đáo như tại chính ngôi nhà của mình.</h1>
          <p>PETTEL là nền tảng kết nối những người yêu thú cưng và phụ huynh nuôi chó mèo bận rộn với mạng lưới Pet Sitter tận tâm, đáng tin cậy đã qua xác minh tại Việt Nam.</p>
          <span className={styles.heroLine} />
          <figure className={styles.heroPhoto}>
            <Image src="/images/auth-pet-family.png" width={1200} height={896} alt="Người chăm quây quần cùng chó và mèo trong ngôi nhà ấm áp" priority sizes="(max-width: 700px) 100vw, (max-width: 1100px) 90vw, 1000px" />
            <figcaption><PettelIcon name="heart" /> Không lồng nhốt • Yêu thương tự nhiên</figcaption>
          </figure>
        </section>

        <article className={styles.storyCard}>
          <section className={styles.origin} aria-labelledby="origin-heading">
            <p className={styles.sectionLabel}><PettelIcon name="heart" /> KHỞI NGUỒN TỪ TÌNH YÊU THƯƠNG</p>
            <h2 id="origin-heading">Câu chuyện ra đời từ sự trăn trở của những người làm cha mẹ thú cưng</h2>
            <p>Mỗi lần nhận lịch công tác đột xuất hay lên kế hoạch cho một kỳ nghỉ dài ngày, cảm giác đầu tiên xuất hiện trong tâm trí chúng tôi không phải là háo hức, mà là sự lo âu dành cho những người bạn bốn chân ở nhà.</p>
            <p>Những ngày tháng chứng kiến bé cưng bị nhốt trong những ô chuồng sắt chật hẹp, ẩm thấp tại các điểm lưu trú truyền thống, nghe tiếng sủa căng thẳng của đàn thú cưng xa lạ, hay chứng kiến ánh mắt hoang mang khi đón bé về đã thôi thúc chúng tôi suy nghĩ: <strong>Liệu có một giải pháp tử tế hơn, nơi thú cưng thực sự được tôn trọng và tận hưởng trọn vẹn sự êm ấm?</strong></p>
            <p>Đó là lý do <strong className={styles.brandText}>PETTEL</strong> ra đời. Chúng tôi tin rằng mỗi chú chó hay cô mèo đều xứng đáng có một người bạn đồng hành ân cần, được chạy nhảy tự do trên sàn gỗ ấm áp, ngủ trên nệm êm và nhận được cái vuốt ve yêu thương ngay cả khi ba mẹ vắng nhà.</p>
            <blockquote className={styles.belief}>“Với chúng tôi, thú cưng không chỉ là vật nuôi. Mỗi bé là một thành viên trong gia đình, có tâm hồn, thói quen và sự nhạy cảm rất riêng.”</blockquote>
          </section>

          <section id="su-menh" className={styles.mission} aria-labelledby="mission-heading">
            <p className={styles.sectionLabel}><PettelIcon name="shield" /> SỨ MỆNH CỦA PETTEL</p>
            <h2 id="mission-heading">Ba trụ cột cốt lõi định hình tiêu chuẩn chăm sóc mới</h2>
            <p className={styles.sectionIntro}>Chúng tôi xây dựng từng quy trình dựa trên sự cẩn trọng tuyệt đối, lấy phúc lợi của thú cưng làm thước đo cao nhất.</p>
            <div className={styles.pillars}>{pillars.map((pillar) => <section className={`${styles.pillar} ${styles[pillar.tone]}`} key={pillar.title}>
              <span className={styles.pillarIcon}><PettelIcon name={pillar.icon} /></span>
              <h3>{pillar.title}</h3><p>{pillar.body}</p>
              <Link href={pillar.href}>{pillar.link} <PettelIcon name="arrow" /></Link>
            </section>)}</div>
          </section>

          <section id="gia-tri" className={styles.values} aria-labelledby="values-heading">
            <p className={styles.sectionLabel}><PettelIcon name="heart" /> GIÁ TRỊ CHÚNG TÔI KIÊN ĐỊNH GÌN GIỮ</p>
            <h2 id="values-heading">Nguyên tắc dẫn lối cho từng quyết định tại PETTEL</h2>
            <ul>{values.map((value) => <li key={value.title}><span className={styles.checkIcon}><PettelIcon name="check" /></span><div><h3>{value.title}</h3><p>{value.body}</p></div></li>)}</ul>
          </section>

          <figure className={styles.founders}>
            <span className={styles.founderAvatar}><Image src="/images/auth-pet-family.png" width={100} height={100} alt="Tình yêu dành cho thú cưng của đội ngũ PETTEL" /></span>
            <div><blockquote>“Cảm ơn bạn đã trao cho PETTEL cơ hội được đồng hành cùng bé yêu. Mỗi tin nhắn gửi pet, mỗi cái vẫy đuôi mừng rỡ khi đón về chính là nguồn động lực lớn nhất để chúng tôi tiếp tục hoàn thiện mỗi ngày.”</blockquote><figcaption><strong>Đội ngũ sáng lập PETTEL Việt Nam</strong><span>Yêu thương từ những điều nhỏ nhất <PettelIcon name="paw" /></span></figcaption></div>
          </figure>
        </article>
      </div>

      <section className={styles.cta} aria-labelledby="about-cta-heading"><div className={styles.ctaInner}>
        <div><h2 id="about-cta-heading">Đồng hành cùng cộng đồng PETTEL</h2><p>Bạn muốn tìm người chăm sóc cho bé hay muốn trở thành một Pet Sitter tận tâm?</p></div>
        <div className={styles.ctaActions}><Link className={styles.primary} href="/services"><PettelIcon name="search" /> Khám phá dịch vụ Sitter</Link><Link className={styles.secondary} href="/caregiver/manage"><PettelIcon name="heart" /> Trở thành đối tác Sitter</Link></div>
      </div></section>
    </main>
  </div>;
}
