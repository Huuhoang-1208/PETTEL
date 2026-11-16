import { PettelIcon } from "@/components/ui/pettel-icon";
import Link from "next/link";
import styles from "./site-footer.module.css";

const parentLinks = [
  { label: "Tìm Sitter gần bạn", href: "/services" },
  { label: "Gửi gắm qua đêm", href: "/services?service_type=BOARDING" },
  { label: "Chăm sóc ban ngày", href: "/services?service_type=DAYCARE" },
  { label: "Dắt cún đi dạo", comingSoon: true },
  { label: "Chăm sóc tại nhà", comingSoon: true },
];

const sitterLinks = [
  { label: "Đăng ký làm Sitter", href: "/caregiver" },
  { label: "Tạo hồ sơ dịch vụ", href: "/caregiver" },
  { label: "Quản lý lịch nhận chăm", href: "/caregiver" },
  { label: "Quản lý đơn chăm sóc", href: "/bookings" },
];

const safetyLinks = [
  { label: "Tạo tài khoản PETTEL", href: "/login" },
  { label: "Theo dõi trạng thái đơn", href: "/bookings" },
  { label: "Trao đổi với người chăm", href: "/bookings" },
  { label: "Xem thông tin dịch vụ", href: "/services" },
];

const serviceAreas = [
  { label: "Trung tâm", code: "DN_CENTER" },
  { label: "Bắc Đà Nẵng", code: "DN_NORTH" },
  { label: "Nam Đà Nẵng", code: "DN_SOUTH" },
  { label: "Tây Đà Nẵng", code: "DN_WEST" },
  { label: "Đông Đà Nẵng", code: "DN_EAST" },
];

export function SiteFooter() {
  return (
    <footer className={styles.footer}>
      <div className={styles.columns}>
        <section className={styles.brandColumn} aria-label="Về PETTEL">
          <Link href="/" className={styles.brand}>
            <span className={styles.brandIcon}>
              <PettelIcon name="paw" />
            </span>
            <span>PETTEL</span>
          </Link>
          <p className={styles.description}>
            Nền tảng kết nối người nuôi thú cưng với người chăm tại Đà Nẵng, giúp bạn tìm dịch vụ,
            đặt lịch và theo dõi quá trình chăm sóc.
          </p>
          <div className={styles.promise}>
            <PettelIcon name="shield" />
            <p>
              <strong>Đặt lịch an tâm</strong>
              <span>Xem thông tin và trạng thái đơn ngay trong tài khoản PETTEL.</span>
            </p>
          </div>
        </section>

        <FooterLinkColumn title="Dành cho Pet Parents" links={parentLinks} />
        <FooterLinkColumn title="Dành cho Pet Sitter" links={sitterLinks} />
        <FooterLinkColumn title="Tin cậy & An toàn" links={safetyLinks} />

        <section className={styles.areaColumn} aria-labelledby="service-areas-title">
          <h2 id="service-areas-title">Khu vực phục vụ</h2>
          <p>Tìm người chăm tại các khu vực ở Đà Nẵng.</p>
          <div className={styles.areaLinks}>
            {serviceAreas.map((area) => (
              <Link key={area.code} href={`/services?area_code=${area.code}`}>
                {area.label}
              </Link>
            ))}
          </div>
        </section>
      </div>

      <div className={styles.bottom}>
        <span>© PETTEL Việt Nam. Đồng hành cùng bé cưng mỗi ngày.</span>
        <nav aria-label="Liên kết nhanh">
          <Link href="/services">Tìm dịch vụ</Link>
          <Link href="/owner-profile#history">Hỗ trợ đơn đặt</Link>
          <Link href="/caregiver">Trở thành Sitter</Link>
        </nav>
      </div>
    </footer>
  );
}

function FooterLinkColumn({
  title,
  links,
}: {
  title: string;
  links: { label: string; href?: string; comingSoon?: boolean }[];
}) {
  return (
    <section className={styles.linkColumn}>
      <h2>{title}</h2>
      <ul>
        {links.map((link) => (
          <li key={link.label}>
            {link.href ? (
              <Link href={link.href}>{link.label}</Link>
            ) : (
              <span>
                {link.label} 
              </span>
            )}
          </li>
        ))}
      </ul>
    </section>
  );
}
