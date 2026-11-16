"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { AvailabilityManager } from "@/features/caregiver/components/availability-manager";
import { ListingManager } from "@/features/caregiver/components/listing-manager";
import { ProfileForm } from "@/features/caregiver/components/profile-form";
import { type ManagedListing, type Profile } from "@/features/caregiver/types";
import { useApi } from "@/hooks/use-api";
import { type Booking, type Page } from "@/lib/api/types";
import { statuses } from "@/lib/constants";
import { money } from "@/lib/format";
import { write } from "@/lib/api/request";
import Link from "next/link";
import { useMemo, useState } from "react";
import styles from "../sitter-management.module.css";

const navItems = [
  { id: "overview", label: "Tổng quan", icon: "sliders" as const },
  { id: "profile", label: "Hồ sơ Sitter", icon: "user" as const },
  { id: "services", label: "Dịch vụ", icon: "home" as const },
  { id: "schedule", label: "Lịch nhận chăm", icon: "calendar" as const },
  { id: "bookings", label: "Đơn chăm sóc", icon: "message" as const },
  { id: "verification", label: "Xác minh & an toàn", icon: "shield" as const },
  { id: "support", label: "Trợ giúp & Hỗ trợ", icon: "mail" as const },
] as const;

type DashboardTab = (typeof navItems)[number]["id"];

const tabTitles: Record<DashboardTab, string> = {
  overview: "Tổng quan hoạt động",
  profile: "Hồ sơ Sitter",
  services: "Dịch vụ của tôi",
  schedule: "Lịch nhận chăm",
  bookings: "Đơn chăm sóc",
  verification: "Xác minh & an toàn",
  support: "Trợ giúp & Hỗ trợ",
};

function dateKey(value: Date) {
  return new Intl.DateTimeFormat("sv-SE", {
    timeZone: "Asia/Ho_Chi_Minh",
    year: "numeric",
    month: "2-digit",
    day: "2-digit",
  }).format(value);
}

function timeRange(start: string, end: string) {
  const format = (value: string) => new Intl.DateTimeFormat("vi-VN", {
    timeZone: "Asia/Ho_Chi_Minh",
    hour: "2-digit",
    minute: "2-digit",
  }).format(new Date(value));
  return `${format(start)} – ${format(end)}`;
}

function statusTone(status: string) {
  if (status === "REQUESTED") return styles.warning;
  if (status === "IN_PROGRESS") return styles.live;
  if (status === "COMPLETED") return styles.done;
  return styles.neutral;
}

export function SitterManagementDashboard({ profile }: { profile: Profile }) {
  const listings = useApi<Page<ManagedListing>>("/api/caregiver/listings?status=ALL&per_page=50", 60000);
  const bookings = useApi<Page<Booking>>("/api/bookings?per_page=50", 15000);
  const [actionError, setActionError] = useState<unknown>();
  const [busyBooking, setBusyBooking] = useState<string | null>(null);
  const [activeTab, setActiveTab] = useState<DashboardTab>("overview");
  const [selectedScheduleDate, setSelectedScheduleDate] = useState(() => dateKey(new Date()));

  const sitterBookings = useMemo(
    () => (bookings.data?.data ?? []).filter((booking) => booking.participant_role === "CAREGIVER"),
    [bookings.data],
  );
  const sitterListings = listings.data?.data ?? [];
  const requested = sitterBookings.filter((booking) => booking.status === "REQUESTED");
  const activeListings = sitterListings.filter((listing) => listing.status === "ACTIVE");
  const pendingListings = sitterListings.filter((listing) => listing.status === "PENDING_REVIEW");
  const now = new Date();
  const today = dateKey(now);
  const weekLimit = new Date(now.getTime() + 7 * 24 * 60 * 60 * 1000);
  const scheduled = sitterBookings.filter((booking) =>
    ["REQUESTED", "AWAITING_PAYMENT", "CONFIRMED", "IN_PROGRESS"].includes(booking.status)
    && new Date(booking.start_at) >= now
    && new Date(booking.start_at) <= weekLimit,
  );
  const scheduleDays = Array.from({ length: 7 }, (_, index) => {
    const date = new Date(now.getFullYear(), now.getMonth(), now.getDate() + index);
    return { key: dateKey(date), date };
  });
  const weekBookings = sitterBookings.filter((booking) =>
    ["REQUESTED", "AWAITING_PAYMENT", "CONFIRMED", "IN_PROGRESS"].includes(booking.status)
    && new Date(booking.start_at) >= now
    && dateKey(new Date(booking.start_at)) <= scheduleDays[6].key,
  ).sort((a, b) => a.start_at.localeCompare(b.start_at));
  const selectedDayBookings = weekBookings.filter((booking) => dateKey(new Date(booking.start_at)) === selectedScheduleDate);
  const todaysBookings = sitterBookings.filter((booking) =>
    dateKey(new Date(booking.start_at)) === today
    && !["CANCELLED", "REJECTED", "EXPIRED", "COMPLETED"].includes(booking.status),
  ).sort((a, b) => a.start_at.localeCompare(b.start_at));
  const featuredBookings = [...sitterBookings]
    .filter((booking) => ["REQUESTED", "CONFIRMED", "IN_PROGRESS", "AWAITING_PAYMENT"].includes(booking.status))
    .sort((a, b) => {
      if (a.status === "REQUESTED" && b.status !== "REQUESTED") return -1;
      if (b.status === "REQUESTED" && a.status !== "REQUESTED") return 1;
      return a.start_at.localeCompare(b.start_at);
    })
    .slice(0, 3);
  const monthKey = dateKey(now).slice(0, 7);
  const completedValue = sitterBookings
    .filter((booking) => booking.status === "COMPLETED" && dateKey(new Date(booking.end_at)).slice(0, 7) === monthKey)
    .reduce((sum, booking) => sum + Number(booking.price_snapshot_vnd), 0);
  const applicationData = profile.application_data_json ?? {};
  const profileCompletionItems = [
    profile.display_name,
    profile.bio,
    profile.area_code,
    applicationData.private_address,
    applicationData.contact_phone,
    applicationData.contact_email,
    applicationData.experience_species?.length,
    applicationData.care_skills?.length,
    applicationData.housing_type,
    applicationData.amenities?.length,
    applicationData.safety_plan,
    applicationData.kyc_front_media_id,
    applicationData.kyc_back_media_id,
  ];
  const profilePercent = Math.round(profileCompletionItems.filter(Boolean).length / profileCompletionItems.length * 100);
  const publicListing = activeListings[0];

  function selectTab(tab: DashboardTab) {
    setActiveTab(tab);
    window.scrollTo({ top: 0, behavior: "smooth" });
  }

  async function bookingAction(id: string, action: "accept" | "reject") {
    setBusyBooking(id);
    setActionError(undefined);
    try {
      await write("post", `/api/bookings/${id}/${action}`, {});
      bookings.reload();
    } catch (error) {
      setActionError(error);
    } finally {
      setBusyBooking(null);
    }
  }

  const renderBooking = (booking: Booking) => <article className={styles.bookingCard} key={booking.id}>
    <span className={styles.petAvatar}><PettelIcon name="paw" /></span>
    <div className={styles.bookingInfo}><div><strong>{booking.pet_name ?? `Đơn chăm sóc #${booking.id}`}</strong><span className={`${styles.status} ${statusTone(booking.status)}`}>{statuses[booking.status] ?? booking.status}</span></div><p>{booking.listing_title ?? "Dịch vụ Sitter"} · {timeRange(booking.start_at, booking.end_at)}</p><small>{new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "2-digit", month: "2-digit", year: "numeric" }).format(new Date(booking.start_at))}</small></div>
    <div className={styles.bookingPrice}>{money(booking.price_snapshot_vnd)}<small>giá trị đơn</small></div>
    {booking.status === "REQUESTED" ? <div className={styles.bookingActions}><button disabled={busyBooking === booking.id} onClick={() => void bookingAction(booking.id, "reject")}>Từ chối</button><button disabled={busyBooking === booking.id} onClick={() => void bookingAction(booking.id, "accept")}><PettelIcon name="check" /> Xác nhận</button></div> : <Link className={styles.detailsLink} href={`/bookings?open=${booking.id}`}>Chi tiết</Link>}
  </article>;

  const todaySchedule = <section className={`${styles.panel} ${styles.schedule}`}>
    <div className={styles.panelHeading}><div><h2>Lịch trình hôm nay</h2><span>{new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", weekday: "long", day: "2-digit", month: "2-digit" }).format(now)}</span></div><button type="button" onClick={() => selectTab("bookings")}>Mở đơn <PettelIcon name="arrow" /></button></div>
    {bookings.loading && <p className={styles.empty}>Đang tải lịch...</p>}
    {!bookings.loading && todaysBookings.length === 0 && <p className={styles.empty}>Hôm nay chưa có lịch chăm sóc.</p>}
    <div className={styles.todayList}>{todaysBookings.slice(0, 4).map((booking) => <article key={booking.id}>
      <strong>{new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", hour: "2-digit", minute: "2-digit" }).format(new Date(booking.start_at))}</strong>
      <div><b>{booking.pet_name ?? `Đơn #${booking.id}`}</b><small>{booking.listing_title ?? "Dịch vụ Sitter"} · {timeRange(booking.start_at, booking.end_at)}</small></div>
      <span className={`${styles.status} ${statusTone(booking.status)}`}>{statuses[booking.status] ?? booking.status}</span>
    </article>)}</div>
  </section>;

  return (
    <div className={styles.shell}>
      <aside className={styles.sidebar}>
        <Link className={styles.brand} href="/">
          <span className={styles.brandIcon}><PettelIcon name="paw" /></span>
          <span><strong>PETTEL</strong><small>PARTNER PORTAL</small></span>
          <b>PRO</b>
        </Link>
        <nav aria-label="Điều hướng quản lý Sitter">
          {navItems.map((item) => <button key={item.id} type="button" className={activeTab === item.id ? styles.selectedNav : undefined} aria-current={activeTab === item.id ? "page" : undefined} onClick={() => selectTab(item.id)}>
            <PettelIcon name={item.icon} /><span>{item.label}</span>
          </button>)}
        </nav>
        <div className={styles.sidebarProfile}>
          <span className={styles.miniAvatar}><PettelIcon name="user" /></span>
          <span><strong>{profile.display_name}</strong><small>Hồ sơ Sitter</small></span>
          <i />
          <p>Trạng thái dịch vụ <b>{activeListings.length ? "Đang hoạt động" : "Chưa hoạt động"}</b></p>
        </div>
      </aside>

      <div className={styles.mainColumn}>
        <main className={styles.content}>
          <header className={styles.pageHeading}>
            <div><h1>{tabTitles[activeTab]}</h1><p>{activeTab === "overview" ? <>Chào mừng trở lại, <strong>{profile.display_name}</strong>. Đây là tóm tắt hoạt động và các tác vụ cần xử lý hôm nay.</> : `Quản lý ${tabTitles[activeTab].toLocaleLowerCase("vi-VN")} trên PETTEL.`}</p></div>
            <div className={styles.headingStatus}><span><i /> {activeListings.length ? "Sẵn sàng nhận khách" : "Hoàn tất duyệt dịch vụ để nhận khách"}</span>{publicListing && <Link href={`/detail?id=${publicListing.id}`}><PettelIcon name="eye" /> Xem trang công khai</Link>}</div>
          </header>

          {activeTab === "overview" && requested[0] && <section className={styles.alert}>
            <span className={styles.alertIcon}><PettelIcon name="bell" /></span>
            <div><small>CẦN PHẢN HỒI · ĐƠN #{requested[0].id}</small><strong>Yêu cầu chăm sóc {requested[0].pet_name ? `cho ${requested[0].pet_name}` : "mới"} · {timeRange(requested[0].start_at, requested[0].end_at)}</strong></div>
            <button type="button" onClick={() => selectTab("bookings")}>Xem đơn <PettelIcon name="arrow" /></button>
          </section>}

          <ApiError error={listings.error ?? bookings.error ?? actionError} />

          {activeTab === "overview" && <section className={styles.stats} aria-label="Chỉ số hoạt động">
            <article><div><span>Yêu cầu cần xử lý</span><b className={styles.statIcon}><PettelIcon name="clock" /></b></div><strong>{bookings.loading ? "—" : String(requested.length).padStart(2, "0")}</strong><small>{requested.length ? "Đơn đang chờ phản hồi" : "Chưa có yêu cầu mới"}</small></article>
            <article><div><span>Lịch chăm sóc 7 ngày tới</span><b className={`${styles.statIcon} ${styles.purple}`}><PettelIcon name="calendar" /></b></div><strong>{bookings.loading ? "—" : String(scheduled.length).padStart(2, "0")}</strong><small>{todaysBookings.length} lịch trong hôm nay</small></article>
            <article><div><span>Dịch vụ đang hoạt động</span><b className={`${styles.statIcon} ${styles.cyan}`}><PettelIcon name="home" /></b></div><strong>{listings.loading ? "—" : String(activeListings.length).padStart(2, "0")}</strong><small>{pendingListings.length} dịch vụ chờ duyệt</small></article>
            <article><div><span>Giá trị đơn hoàn thành tháng này</span><b className={`${styles.statIcon} ${styles.green}`}><PettelIcon name="award" /></b></div><strong className={styles.money}>{bookings.loading ? "—" : money(completedValue)}</strong><small>Tổng giá trị đơn, không phải tiền thực nhận</small></article>
          </section>}

          {activeTab === "overview" && <div className={styles.columns}>
            <section className={styles.panel}>
              <div className={styles.panelHeading}><div><h2>Đơn chăm sóc nổi bật</h2><span>{featuredBookings.length} đơn cần theo dõi</span></div><button type="button" onClick={() => selectTab("bookings")}>Xem tất cả <PettelIcon name="arrow" /></button></div>
              {bookings.loading && <p className={styles.empty}>Đang tải đơn chăm sóc...</p>}
              {!bookings.loading && featuredBookings.length === 0 && <p className={styles.empty}>Chưa có đơn chăm sóc cần theo dõi.</p>}
              <div className={styles.bookingList}>
                {featuredBookings.map((booking) => <article className={styles.bookingCard} key={booking.id}>
                  <span className={styles.petAvatar}><PettelIcon name="paw" /></span>
                  <div className={styles.bookingInfo}><div><strong>{booking.pet_name ?? `Đơn chăm sóc #${booking.id}`}</strong><span className={`${styles.status} ${statusTone(booking.status)}`}>{statuses[booking.status] ?? booking.status}</span></div><p>{booking.listing_title ?? "Dịch vụ Sitter"} · {timeRange(booking.start_at, booking.end_at)}</p><small>{new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "2-digit", month: "2-digit", year: "numeric" }).format(new Date(booking.start_at))}</small></div>
                  <div className={styles.bookingPrice}>{money(booking.price_snapshot_vnd)}<small>giá trị đơn</small></div>
                  {booking.status === "REQUESTED" ? <div className={styles.bookingActions}><button disabled={busyBooking === booking.id} onClick={() => void bookingAction(booking.id, "reject")}>Từ chối</button><button disabled={busyBooking === booking.id} onClick={() => void bookingAction(booking.id, "accept")}><PettelIcon name="check" /> Xác nhận</button></div> : <Link className={styles.detailsLink} href={`/bookings?open=${booking.id}`}>Chi tiết</Link>}
                </article>)}
              </div>
            </section>

            {todaySchedule}
          </div>}

          {activeTab === "overview" && <div className={styles.columns}>
            <section className={styles.panel}>
              <div className={styles.panelHeading}><div><h2>Dịch vụ của tôi</h2><span>{activeListings.length} hoạt động · {pendingListings.length} chờ duyệt</span></div><button type="button" onClick={() => selectTab("services")}><PettelIcon name="plus" /> Thêm dịch vụ</button></div>
              <div className={styles.serviceSummary}>
                {sitterListings.slice(0, 4).map((listing) => <article key={listing.id}><span><PettelIcon name={listing.service_type === "BOARDING" ? "home" : "calendar"} /></span><div><strong>{listing.title}</strong><small>{money(listing.price_vnd)} · {listing.price_unit === "NIGHT" ? "đêm" : "giờ"}</small></div><b className={`${styles.status} ${listing.status === "ACTIVE" ? styles.live : listing.status === "PENDING_REVIEW" ? styles.warning : styles.neutral}`}>{listing.status === "ACTIVE" ? "Đang hoạt động" : listing.status === "PENDING_REVIEW" ? "Chờ duyệt" : listing.status === "DRAFT" ? "Bản nháp" : listing.status}</b></article>)}
                {!listings.loading && sitterListings.length === 0 && <p className={styles.empty}>Bạn chưa tạo dịch vụ. Hãy thêm dịch vụ đầu tiên để bắt đầu nhận khách.</p>}
              </div>
            </section>

            <section className={styles.panel}>
              <div className={styles.panelHeading}><div><h2>Hoàn thiện hồ sơ Sitter</h2><span>Thông tin cơ bản</span></div><strong className={styles.completion}>{profilePercent}%</strong></div>
              <div className={styles.progressTrack}><i style={{ width: `${profilePercent}%` }} /></div>
              <ul className={styles.checklist}><li className={profile.display_name ? styles.checked : ""}><PettelIcon name="check" /> Tên hiển thị</li><li className={profile.bio && profile.area_code ? styles.checked : ""}><PettelIcon name="check" /> Giới thiệu & khu vực</li><li className={applicationData.private_address && applicationData.contact_phone && applicationData.contact_email ? styles.checked : ""}><PettelIcon name="check" /> Thông tin liên hệ</li><li className={applicationData.experience_species?.length && applicationData.care_skills?.length && applicationData.housing_type && applicationData.amenities?.length ? styles.checked : ""}><PettelIcon name="check" /> Kinh nghiệm & không gian</li><li className={applicationData.safety_plan ? styles.checked : ""}><PettelIcon name="check" /> Kế hoạch an toàn</li><li className={applicationData.kyc_front_media_id && applicationData.kyc_back_media_id ? styles.checked : ""}><PettelIcon name="check" /> Ảnh xác minh CCCD</li></ul>
              <div className={styles.quickLinks}><button type="button" onClick={() => selectTab("services")}><PettelIcon name="plus" /> Thêm dịch vụ</button><button type="button" onClick={() => selectTab("schedule")}><PettelIcon name="calendar" /> Lịch nhận chăm</button><button type="button" onClick={() => selectTab("support")}><PettelIcon name="message" /> Hỗ trợ</button></div>
            </section>
          </div>}

          {activeTab === "profile" && <ProfileForm />}
          {activeTab === "services" && <ListingManager includeAvailability={false} />}
          {activeTab === "schedule" && <div className={styles.scheduleWorkspace}>
            <section className={styles.scheduleIntro}>
              <div><span className={styles.scheduleEyebrow}><PettelIcon name="calendar" /> LỊCH NHẬN CHĂM SÓC</span><h2>Sắp xếp thời gian nhận khách</h2><p>Theo dõi lịch hẹn trong tuần và mở các khung giờ bạn có thể nhận chăm.</p></div>
              <span className={styles.scheduleRange}><PettelIcon name="clock" /> {new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "2-digit", month: "2-digit" }).format(now)} – {new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "2-digit", month: "2-digit" }).format(scheduleDays[6].date)}</span>
            </section>

            <section className={styles.scheduleStats} aria-label="Tóm tắt lịch nhận chăm">
              <article><span className={styles.scheduleStatIcon}><PettelIcon name="calendar" /></span><div><small>Lịch hẹn 7 ngày tới</small><strong>{bookings.loading ? "—" : weekBookings.length}</strong></div></article>
              <article><span className={`${styles.scheduleStatIcon} ${styles.scheduleAmber}`}><PettelIcon name="clock" /></span><div><small>Lịch hẹn hôm nay</small><strong>{bookings.loading ? "—" : todaysBookings.length}</strong></div></article>
              <article><span className={`${styles.scheduleStatIcon} ${styles.scheduleGreen}`}><PettelIcon name="home" /></span><div><small>Dịch vụ có thể nhận khách</small><strong>{listings.loading ? "—" : activeListings.length}</strong></div></article>
            </section>

            <section className={styles.schedulePanel}>
              <div className={styles.schedulePanelHeading}><div><h3>Lịch trong tuần</h3><p>Chọn một ngày để xem các lịch hẹn trong ngày đó.</p></div><button type="button" onClick={() => selectTab("bookings")}>Quản lý đơn chăm sóc <PettelIcon name="arrow" /></button></div>
              <div className={styles.weekStrip}>
                {scheduleDays.map(({ key, date }) => {
                  const count = weekBookings.filter((booking) => dateKey(new Date(booking.start_at)) === key).length;
                  const isSelected = selectedScheduleDate === key;
                  const isToday = key === today;
                  return <button type="button" key={key} className={`${styles.dayButton} ${isSelected ? styles.selectedDay : ""}`} aria-pressed={isSelected} onClick={() => setSelectedScheduleDate(key)}>
                    <small>{isToday ? "Hôm nay" : new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", weekday: "short" }).format(date)}</small>
                    <strong>{new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", day: "2-digit" }).format(date)}</strong>
                    <span>{count ? `${count} lịch` : "Trống"}</span>
                  </button>;
                })}
              </div>
              <div className={styles.agendaHeading}><div><h3>{new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", weekday: "long", day: "2-digit", month: "long" }).format(scheduleDays.find((day) => day.key === selectedScheduleDate)?.date ?? now)}</h3><span>{selectedDayBookings.length} lịch hẹn</span></div></div>
              {bookings.loading && <p className={styles.empty}>Đang tải lịch hẹn...</p>}
              {!bookings.loading && selectedDayBookings.length === 0 && <div className={styles.noSchedule}><span><PettelIcon name="calendar" /></span><div><strong>Chưa có lịch hẹn trong ngày này</strong><p>Lịch đã xác nhận và yêu cầu mới sẽ xuất hiện tại đây.</p></div></div>}
              <div className={styles.agendaList}>{selectedDayBookings.map((booking) => <article key={booking.id}>
                <div className={styles.agendaTime}>{new Intl.DateTimeFormat("vi-VN", { timeZone: "Asia/Ho_Chi_Minh", hour: "2-digit", minute: "2-digit" }).format(new Date(booking.start_at))}<small>{timeRange(booking.start_at, booking.end_at)}</small></div>
                <span className={styles.agendaLine} />
                <div className={styles.agendaInfo}><strong>{booking.pet_name ?? `Đơn chăm sóc #${booking.id}`}</strong><span>{booking.listing_title ?? "Dịch vụ Sitter"}</span><small>Mã đơn #{booking.id}</small></div>
                <span className={`${styles.status} ${statusTone(booking.status)}`}>{statuses[booking.status] ?? booking.status}</span>
                <Link className={styles.agendaAction} href={`/bookings?open=${booking.id}`}>Chi tiết <PettelIcon name="arrow" /></Link>
              </article>)}</div>
            </section>

            <section className={styles.availabilitySection}>
              <div className={styles.schedulePanelHeading}><div><h3>Khung giờ nhận chăm</h3><p>Tạo thời gian trống riêng cho từng dịch vụ. Khung giờ sẽ được dùng khi khách đặt lịch.</p></div></div>
              {listings.loading && <p className={styles.empty}>Đang tải dịch vụ...</p>}
              {!listings.loading && sitterListings.length === 0 && <div className={styles.noSchedule}><span><PettelIcon name="home" /></span><div><strong>Chưa có dịch vụ để thiết lập lịch</strong><p>Tạo dịch vụ trước, sau đó thêm những khung giờ bạn có thể nhận chăm.</p></div><button type="button" onClick={() => selectTab("services")}>Đến dịch vụ <PettelIcon name="arrow" /></button></div>}
              {!listings.loading && sitterListings.length > 0 && <AvailabilityManager listings={sitterListings} />}
            </section>
          </div>}
          {activeTab === "bookings" && <section className={styles.panel}><div className={styles.panelHeading}><div><h2>Tất cả đơn nhận chăm sóc</h2><span>{sitterBookings.length} đơn</span></div></div>{bookings.loading && <p className={styles.empty}>Đang tải đơn chăm sóc...</p>}{!bookings.loading && sitterBookings.length === 0 && <p className={styles.empty}>Chưa có đơn chăm sóc dành cho bạn.</p>}<div className={styles.bookingList}>{sitterBookings.map(renderBooking)}</div></section>}
          {activeTab === "verification" && <section className={styles.verificationNotice}><PettelIcon name="shield" /><div><strong>Xác minh & an toàn</strong><p>Ảnh CCCD và chứng chỉ (nếu có) được lưu riêng tư trong hồ sơ của bạn. Quy trình nhân viên PETTEL xem xét và cập nhật trạng thái xác minh chưa được kết nối.</p></div><Link href="/contact-support">Liên hệ hỗ trợ</Link></section>}
          {activeTab === "support" && <section className={`${styles.panel} ${styles.supportPanel}`}><span><PettelIcon name="message" /></span><h2>Đội ngũ PETTEL sẵn sàng hỗ trợ bạn</h2><p>Nếu cần trợ giúp về hồ sơ, dịch vụ, lịch nhận chăm hoặc đơn chăm sóc, hãy gửi yêu cầu cho đội ngũ hỗ trợ.</p><Link href="/contact-support">Mở Trung tâm Liên hệ và Hỗ trợ <PettelIcon name="arrow" /></Link></section>}
        </main>
      </div>
    </div>
  );
}
