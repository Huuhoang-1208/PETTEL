"use client";

import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useId, useRef, useState, type FormEvent, type ReactNode } from "react";
import { useApi, type Page, type Pet } from "@/lib/api/pettel";
import { ApiError, SiteHeader, useSession } from "./session";
import { PettelIcon, type PettelIconName } from "./pettel-icon";
import styles from "./landing-home.module.css";

type Field = "area" | "service" | "dates" | "pet";

const locations: Record<string, string> = {
  "": "Tất cả Đà Nẵng",
  DN_CENTER: "Trung tâm, Đà Nẵng",
  DN_NORTH: "Bắc Đà Nẵng",
  DN_SOUTH: "Nam Đà Nẵng",
  DN_WEST: "Tây Đà Nẵng",
  DN_EAST: "Đông Đà Nẵng",
};
const careServices: Record<string, string> = {
  BOARDING: "Gửi gắm qua đêm",
  DAYCARE: "Chăm sóc ban ngày",
};
const petTypes: Record<string, string> = { DOG: "Cún", CAT: "Mèo", OTHER: "Bé cưng" };
const petSizes: Record<string, string> = { SMALL: "nhỏ", MEDIUM: "vừa", LARGE: "lớn" };

export function LandingHome() {
  return <div className={styles.page}>
    <SiteHeader />
    <main>
      <section className={styles.hero} aria-labelledby="home-title">
        <div className={styles.heroContent}>
          <div className={styles.assurance}>
            <PettelIcon name="shield" />
            <strong>Nền tảng chăm sóc thú cưng</strong>
            <span className={styles.assuranceDot}>•</span>
            <span>Đồng hành cùng bé mỗi ngày</span>
          </div>
          <h1 id="home-title" className={styles.title}>
            Yên tâm gửi gắm bé cưng cho<br className={styles.titleBreak} />
            {" "}<span className={styles.highlight}>người chăm sóc tận tâm</span> gần bạn
          </h1>
          <p className={styles.subtitle}>
            Kết nối với người chăm sóc thú cưng tại Đà Nẵng. Tìm dịch vụ phù hợp,<br className={styles.subtitleBreak} />
            đặt lịch và theo dõi nhật ký chăm sóc của bé mỗi ngày.
          </p>
          <HomeSearch />
          <ul className={styles.trustStrip} aria-label="Trải nghiệm cùng PETTEL">
            <li><PettelIcon name="star" /><span>Đánh giá từ đơn đã hoàn thành</span></li>
            <li><PettelIcon name="shield" /><span>Thông tin đặt lịch minh bạch</span></li>
            <li><PettelIcon name="camera" /><span>Cập nhật nhật ký & hình ảnh</span></li>
            <li><PettelIcon name="heart" /><span>Đồng hành cùng người chăm</span></li>
          </ul>
        </div>
      </section>

      <section id="ve-pettel" className={styles.about} aria-labelledby="about-title">
        <span className={styles.eyebrow}>GỬI GẮM YÊU THƯƠNG, ĐÓN VỀ AN TÂM</span>
        <h2 id="about-title">Chăm sóc bé cưng, dễ dàng hơn mỗi ngày</h2>
        <p className={styles.sectionIntro}>Từ lần tìm kiếm đầu tiên đến khi đón bé về, mọi thông tin được kết nối trong tài khoản PETTEL của bạn.</p>
        <div className={styles.steps}>
          {[
            { icon: "search", title: "Tìm người chăm phù hợp", text: "Chọn khu vực, dịch vụ và hồ sơ thú cưng để tìm lựa chọn phù hợp với bé." },
            { icon: "calendar", title: "Đặt lịch rõ ràng, thuận tiện", text: "Kiểm tra lịch trống và báo giá, gửi yêu cầu rồi trao đổi với người chăm ngay trong đơn." },
            { icon: "paw", title: "Theo dõi những ngày của bé", text: "Giữ liên lạc qua tin nhắn và xem nhật ký, hình ảnh chăm sóc được người chăm cập nhật." },
          ].map((step, index) => <article className={styles.step} key={step.title}>
            <div className={styles.stepTop}><span className={styles.stepIcon}><PettelIcon name={step.icon as PettelIconName} /></span><span className={styles.stepNumber}>0{index + 1}</span></div>
            <h3>{step.title}</h3><p>{step.text}</p>
          </article>)}
        </div>
      </section>

      <section id="cong-dong" className={styles.community} aria-labelledby="community-title">
        <span className={styles.communityPaw}><PettelIcon name="paw" /></span>
        <div><span className={styles.eyebrow}>CÙNG NHAU CHĂM SÓC TỐT HƠN</span><h2 id="community-title">Một cộng đồng, chung tình yêu thú cưng.</h2><p>Bạn có kinh nghiệm chăm sóc và một không gian dành cho các bé? Kết nối với những người nuôi thú cưng cùng PETTEL.</p></div>
        <Link href="/caregiver" className={styles.communityButton}>Trở thành Sitter <PettelIcon name="arrow" /></Link>
      </section>
    </main>
    <footer className={styles.footer}><Link href="/" className={styles.footerBrand}><PettelIcon name="paw" /> PETTEL</Link><span>Yêu thương bé, an tâm mỗi ngày.</span><Link href="/services">Khám phá dịch vụ <PettelIcon name="arrow" /></Link></footer>
  </div>;
}

function HomeSearch() {
  const router = useRouter();
  const { user } = useSession();
  const hasPetsRole = user?.roles?.some((role) => role.role === "OWNER");
  const pets = useApi<Page<Pet>>(hasPetsRole ? "/api/pets" : null);
  const activePets = pets.data?.data.filter((pet) => pet.status === "ACTIVE") ?? [];
  const [area, setArea] = useState("DN_CENTER");
  const [service, setService] = useState("BOARDING");
  const [start, setStart] = useState("");
  const [end, setEnd] = useState("");
  const [petType, setPetType] = useState("DOG");
  const [petSize, setPetSize] = useState("SMALL");
  const [petId, setPetId] = useState("");
  const [open, setOpen] = useState<Field | null>(null);
  const [error, setError] = useState("");
  const form = useRef<HTMLFormElement>(null);
  const selectedPet = activePets.find((pet) => pet.id === petId);

  useEffect(() => {
    if (!open) return;
    const closeOutside = (event: PointerEvent) => {
      if (event.target instanceof Node && !form.current?.contains(event.target)) setOpen(null);
    };
    const closeOnEscape = (event: KeyboardEvent) => { if (event.key === "Escape") setOpen(null); };
    document.addEventListener("pointerdown", closeOutside);
    document.addEventListener("keydown", closeOnEscape);
    return () => {
      document.removeEventListener("pointerdown", closeOutside);
      document.removeEventListener("keydown", closeOnEscape);
    };
  }, [open]);

  function dateLabel() {
    if (!start || !end) return "Chọn ngày gửi";
    const format = (value: string) => `${value.slice(8, 10)}/${value.slice(5, 7)}`;
    return start.slice(0, 10) === end.slice(0, 10)
      ? `${format(start)}, ${start.slice(11)}–${end.slice(11)}`
      : `${format(start)} → ${format(end)}`;
  }

  function search(event: FormEvent<HTMLFormElement>) {
    event.preventDefault(); setError("");
    const params = new URLSearchParams();
    if (area) params.set("area_code", area);
    params.set("service_type", service);
    if (selectedPet) params.set("pet_id", selectedPet.id);
    else { params.set("species", petType); params.set("size", petSize); }
    if (start || end) {
      if (!start || !end) { setError("Bạn hãy chọn cả thời gian bắt đầu và kết thúc nhé."); setOpen("dates"); return; }
      const startDate = new Date(`${start}:00+07:00`);
      const endDate = new Date(`${end}:00+07:00`);
      if (!Number.isFinite(startDate.getTime()) || !Number.isFinite(endDate.getTime()) || startDate <= new Date() || endDate <= startDate) {
        setError("Chọn thời gian bắt đầu trong tương lai và kết thúc sau khi bắt đầu."); setOpen("dates"); return;
      }
      params.set("start_at", startDate.toISOString()); params.set("end_at", endDate.toISOString());
    }
    router.push(`/services?${params.toString()}`);
  }

  return <div className={styles.searchArea}>
    <form className={styles.searchBar} onSubmit={search} ref={form} aria-label="Tìm người chăm sóc thú cưng">
      <SearchField field="area" label="Khu vực / địa điểm" value={locations[area]} icon="pin" open={open} setOpen={setOpen} tone="blue">
        <label>Khu vực tìm sitter<select name="area_code" value={area} onChange={(event) => { setArea(event.target.value); setOpen(null); }}>
          {Object.entries(locations).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select></label>
      </SearchField>
      <SearchField field="service" label="Dịch vụ chăm sóc" value={careServices[service]} icon="home" open={open} setOpen={setOpen} tone="orange">
        <label>Dịch vụ chăm sóc<select name="service_type" value={service} onChange={(event) => { setService(event.target.value); setOpen(null); }}>
          {Object.entries(careServices).map(([value, label]) => <option key={value} value={value}>{label}</option>)}
        </select></label><p>Chăm ban ngày tính theo giờ; lưu trú qua đêm tính theo đêm.</p>
      </SearchField>
      <SearchField field="dates" label="Thời gian gửi" value={dateLabel()} icon="calendar" open={open} setOpen={setOpen} tone="purple">
        <label>Bắt đầu (giờ Việt Nam)<input type="datetime-local" name="start_at" value={start} onInput={(event) => setStart(event.currentTarget.value)} /></label>
        <label>Kết thúc (giờ Việt Nam)<input type="datetime-local" name="end_at" value={end} onInput={(event) => setEnd(event.currentTarget.value)} /></label>
        <p>{service === "DAYCARE" ? "Chăm ban ngày từ 1–12 giờ, trong cùng một ngày." : "Lưu trú từ 12 giờ đến 30 ngày."}</p>
        <div className={styles.panelActions}><button type="button" onClick={() => { setStart(""); setEnd(""); setError(""); setOpen(null); }}>Bỏ chọn</button><button type="button" onClick={() => setOpen(null)}>Xong</button></div>
      </SearchField>
      <SearchField field="pet" label="Bé cưng" value={selectedPet?.name ?? `1 ${petTypes[petType]} (${petSizes[petSize]})`} icon="paw" open={open} setOpen={setOpen} tone="teal" compact>
        {hasPetsRole && <><label>Hồ sơ thú cưng của tôi<select name="pet_id" value={selectedPet?.id ?? ""} onChange={(event) => setPetId(event.target.value)}><option value="">Tìm theo loài và kích thước</option>{activePets.map((pet) => <option key={pet.id} value={pet.id}>{pet.name}</option>)}</select></label><ApiError error={pets.error} />{pets.loading && <p>Đang tải hồ sơ...</p>}</>}
        {!selectedPet && <><label>Loài thú cưng<select name="species" value={petType} onChange={(event) => setPetType(event.target.value)}>{Object.entries(petTypes).map(([value, label]) => <option key={value} value={value}>{label}</option>)}</select></label><label>Kích thước<select name="size" value={petSize} onChange={(event) => setPetSize(event.target.value)}>{Object.entries(petSizes).map(([value, label]) => <option key={value} value={value}>{label.charAt(0).toUpperCase() + label.slice(1)}</option>)}</select></label></>}
        <div className={styles.panelActions}><Link href={user ? "/pet-profile" : "/login?next=%2Fpet-profile"}>{user ? "Quản lý hồ sơ thú cưng" : "Đăng nhập để dùng hồ sơ"}</Link><button type="button" onClick={() => setOpen(null)}>Xong</button></div>
      </SearchField>
      <button type="submit" className={styles.searchButton}><PettelIcon name="search" /><span>Tìm Sitter</span></button>
    </form>
    {error && <p className={styles.searchError} role="alert">{error}</p>}
  </div>;
}

function SearchField({ field, label, value, icon, open, setOpen, tone, compact, children }: {
  field: Field; label: string; value: string; icon: PettelIconName; open: Field | null;
  setOpen: (field: Field | null) => void; tone: "blue" | "orange" | "purple" | "teal";
  compact?: boolean; children: ReactNode;
}) {
  const id = useId();
  const expanded = open === field;
  return <div className={`${styles.field} ${compact ? styles.compactField : ""}`}>
    <button type="button" className={`${styles.fieldButton} ${expanded ? styles.fieldActive : ""}`} aria-expanded={expanded} aria-controls={id} onClick={() => setOpen(expanded ? null : field)}>
      <span className={`${styles.fieldIcon} ${styles[tone]}`}><PettelIcon name={icon} /></span>
      <span className={styles.fieldText}><span className={styles.fieldLabel}>{label}</span><span className={styles.fieldValue}>{value}</span></span>
      {!compact && field === "service" && <PettelIcon name="chevron" className={styles.fieldChevron} />}
    </button>
    {expanded && <div id={id} className={styles.fieldPanel} role="group" aria-label={label}>{children}</div>}
  </div>;
}
