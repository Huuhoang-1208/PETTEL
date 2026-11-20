"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { RequireSession } from "@/features/auth/components/require-session";
import { ChangeRequestReviewCard } from "@/features/admin/components/change-request-review-card";
import { type Change } from "@/features/admin/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Listing, type Page } from "@/lib/api/types";
import { apiUrl, money } from "@/lib/format";
import { useSession } from "@/providers/session-provider";
import Image from "next/image";
import Link from "next/link";
import { useMemo, useState } from "react";
import styles from "./admin-page.module.css";

type BookingOperations = {
  data: {
    overdue_bookings: { booking_id: string; status: string; awaiting_since: string }[];
    release_operations_for_review: {
      operation_id: string;
      booking_id: string;
      reason_code: string;
      last_error_code: string | null;
      created_at: string;
    }[];
  };
};

type CancellationOperation = {
  id: string;
  booking_id: string;
  operation_type: string;
  reason_code: string;
  attempt_count: number;
  last_error_code: string | null;
  created_at: string;
};

type AdminReport = {
  id: string;
  target: { type: string; id: string } | null;
  reason_code: string;
  status: string;
  created_at: string;
};

const navigation = [
  { id: "overview", label: "Tổng quan", icon: "▦" },
  { id: "sitters", label: "Duyệt Sitter", icon: "⬡" },
  { id: "bookings", label: "Đơn chăm sóc", icon: "▤" },
  { id: "reports", label: "Báo cáo & An toàn", icon: "◈" },
  { id: "tools", label: "Báo cáo & công cụ", icon: "↗" },
 ] as const;
type AdminSection = (typeof navigation)[number]["id"];
const sectionTitles: Record<AdminSection, string> = {
  overview: "Tổng Quan Vận Hành Hệ Thống PETTEL",
  sitters: "Duyệt hồ sơ Sitter",
  bookings: "Quản lý đơn chăm sóc",
  reports: "Báo cáo & An toàn",
  tools: "Trung tâm báo cáo & công cụ",
};
const sectionDescriptions: Record<AdminSection, string> = {
  overview: "Tóm tắt hoạt động và các chỉ số vận hành hiện tại.",
  sitters: "Kiểm tra hồ sơ và thông tin dịch vụ trước khi phê duyệt.",
  bookings: "Theo dõi các đơn và tình huống cần đội ngũ quản trị xử lý.",
  reports: "Rà soát báo cáo người dùng và tình trạng hệ thống.",
  tools: "Mở báo cáo PETTEL và các công cụ đang được cấu hình cho môi trường phát triển.",
};

export function AdminPage() {
  return (
    <RequireSession next="/admin">
      <AdminConsole />
    </RequireSession>
  );
}

function AdminConsole() {
  const { user } = useSession();
  const isAdmin = user?.roles?.some((role) => role.role === "ADMIN") ?? false;
  const isSupport = user?.roles?.some((role) => role.role === "SUPPORT") ?? false;
  const isStaff = isAdmin || isSupport;
  const [activeSection, setActiveSection] = useState<AdminSection>("overview");
  const [search, setSearch] = useState("");
  const [previewListingId, setPreviewListingId] = useState<string | null>(null);
  const [reasonByListing, setReasonByListing] = useState<Record<string, string>>({});
  const [busyListing, setBusyListing] = useState<string | null>(null);
  const [actionError, setActionError] = useState<unknown>();

  const pendingListings = useApi<Page<Listing>>(isStaff ? "/api/v1/admin/listings?status=PENDING_REVIEW&per_page=3" : null, 60000);
  const activeListings = useApi<Page<Listing>>(isStaff ? "/api/v1/admin/listings?status=ACTIVE&per_page=1" : null, 60000);
  const pendingChanges = useApi<Page<Change>>(isStaff ? "/api/v1/admin/listing-change-requests?status=PENDING_REVIEW&per_page=50" : null, 15000);
  const completionReviews = useApi<BookingOperations>(isStaff ? "/api/v1/admin/booking-completion-reviews" : null, 60000);
  const cancellations = useApi<{ data: CancellationOperation[] }>(isStaff ? "/api/v1/admin/booking-cancellations" : null, 60000);
  const openReports = useApi<Page<AdminReport>>(isStaff ? "/api/v1/admin/reports?status=OPEN&per_page=3" : null, 60000);

  const filteredListings = useMemo(() => {
    const query = search.trim().toLocaleLowerCase("vi");
    const rows = pendingListings.data?.data ?? [];
    if (!query) return rows;
    return rows.filter((listing) => [
      listing.title,
      listing.caregiver?.display_name,
      listing.caregiver?.name,
      listing.area_code,
    ].some((value) => value?.toLocaleLowerCase("vi").includes(query)));
  }, [pendingListings.data, search]);
  const filteredChanges = useMemo(() => {
    const query = search.trim().toLocaleLowerCase("vi");
    const rows = pendingChanges.data?.data ?? [];
    if (!query) return rows;
    return rows.filter((change) => [
      change.listing_id,
      change.proposed_data_json.data.title,
      change.proposed_data_json.data.description,
      change.proposed_data_json.data.area_code,
    ].some((value) => value?.toLocaleLowerCase("vi").includes(query)));
  }, [pendingChanges.data, search]);

  async function reviewListing(listing: Listing, action: "approve" | "reject") {
    if (!isAdmin) return;
    const reason = reasonByListing[listing.id]?.trim() ?? "";
    if (action === "reject" && !reason) return;
    setBusyListing(listing.id);
    setActionError(undefined);
    try {
      await write("post", `/api/v1/admin/listings/${listing.id}/${action}`, reason ? { reason } : undefined);
      setReasonByListing((current) => ({ ...current, [listing.id]: "" }));
      pendingListings.reload();
      pendingChanges.reload();
    } catch (error) {
      setActionError(error);
    } finally {
      setBusyListing(null);
    }
  }

  if (!isStaff) {
    return <main className={styles.denied}><div><span>◈</span><h1>Khu vực dành cho đội ngũ nội bộ</h1><p>Tài khoản hiện tại chưa có quyền truy cập trang này.</p><Link href="/">Quay về PETTEL</Link></div></main>;
  }

  const pendingTotal = pendingListings.data?.meta?.total ?? 0;
  const pendingReviewTotal = pendingTotal + (pendingChanges.data?.meta?.total ?? 0);
  const activeTotal = activeListings.data?.meta?.total ?? 0;
  const changesTotal = pendingChanges.data?.meta?.total ?? 0;
  const completionTotal = (completionReviews.data?.data.overdue_bookings.length ?? 0) + (completionReviews.data?.data.release_operations_for_review.length ?? 0);
  const cancellationTotal = cancellations.data?.data.length ?? 0;
  const reportsTotal = openReports.data?.meta?.total ?? 0;
  const operationRows = [
    ...(completionReviews.data?.data.overdue_bookings ?? []).map((item) => ({
      key: `completion-${item.booking_id}`,
      booking: item.booking_id,
      type: "Chờ xác nhận hoàn tất",
      reason: item.status,
      time: item.awaiting_since,
      tone: "amber",
    })),
    ...(completionReviews.data?.data.release_operations_for_review ?? []).map((item) => ({
      key: `release-${item.operation_id}`,
      booking: item.booking_id,
      type: "Đối soát thanh toán",
      reason: item.last_error_code ?? item.reason_code,
      time: item.created_at,
      tone: "red",
    })),
    ...(cancellations.data?.data ?? []).map((item) => ({
      key: `cancel-${item.id}`,
      booking: item.booking_id,
      type: "Yêu cầu hủy / hoàn tiền",
      reason: item.last_error_code ?? item.reason_code,
      time: item.created_at,
      tone: "orange",
    })),
  ].slice(0, 6);

  return (
    <div className={styles.shell}>
      <aside className={styles.sidebar}>
        <Link className={styles.brand} href="/admin" aria-label="PETTEL Admin Portal">
          <span className={styles.brandMark}><PettelIcon name="paw" /></span>
          <span><strong>PETTEL</strong><small>ADMIN PORTAL</small></span>
          <span className={styles.collapseMark}>≡</span>
        </Link>
        <div className={styles.systemStatus}><span><i /> Hệ thống hoạt động</span><b>ĐANG CHẠY</b></div>
        <nav className={styles.sideNav} aria-label="Điều hướng quản trị">
          {navigation.map((item) => (
            <button
              type="button"
              key={item.id}
              className={activeSection === item.id ? styles.activeNav : undefined}
              onClick={() => setActiveSection(item.id)}
            >
              <span className={styles.navIcon}>{item.icon}</span><span>{item.label}</span>
              {item.id === "sitters" && pendingReviewTotal > 0 && <b>{pendingReviewTotal}</b>}
              {item.id === "reports" && reportsTotal > 0 && <b className={styles.redCount}>{reportsTotal}</b>}
            </button>
          ))}
        </nav>
        <div className={styles.sidebarAccount}>
          <span className={styles.userAvatar}><PettelIcon name="user" /></span>
          <span><strong>{user?.name ?? "Quản trị viên"}</strong><small>{isSupport ? "Support Team" : "Super Administrator"}</small></span>
          <Link href="/" aria-label="Rời trang quản trị">↗</Link>
        </div>
      </aside>

      <div className={styles.mainColumn}>
        <header className={styles.topbar}>
          <label className={styles.searchBox}><PettelIcon name="search" /><input value={search} onChange={(event) => setSearch(event.target.value)} placeholder="Tìm mã booking, sitter ID, số điện thoại chủ nuôi..." /></label>
          <span className={styles.serverStatus}><i /> Máy chủ đang hoạt động</span>
          <button type="button" className={styles.topIcon} onClick={() => setActiveSection("reports")} aria-label="Xem cảnh báo"><PettelIcon name="bell" />{reportsTotal > 0 && <i />}</button>
          <button type="button" className={styles.topIcon} onClick={() => setActiveSection("bookings")} aria-label="Xem công việc"><PettelIcon name="message" />{completionTotal + cancellationTotal > 0 && <i />}</button>
          <div className={styles.profile}><span className={styles.userAvatar}><PettelIcon name="user" /></span><span><strong>{user?.name}</strong><small>Khu vực VN • North</small></span><PettelIcon name="chevron" /></div>
        </header>

        <main className={styles.content}>
          <section className={styles.pageHeading}>
            <div><span className={styles.kicker}><i /> HỆ THỐNG VẬN HÀNH TẬP TRUNG</span><h1>{sectionTitles[activeSection]}</h1><p>{activeSection === "overview" ? `Chào buổi sáng, ${user?.name ?? "Quản trị viên"} · ${sectionDescriptions.overview}` : sectionDescriptions[activeSection]}{isSupport ? " · Chế độ hỗ trợ chỉ xem." : ""}</p></div>
            <div className={styles.headingActions}><span className={styles.datePill}><PettelIcon name="calendar" /> Theo dõi realtime</span><a className={styles.exportButton} href={apiUrl("/admin/reports")} target="_blank" rel="noreferrer">↗ Mở báo cáo</a></div>
          </section>

          <ApiError error={pendingListings.error ?? activeListings.error ?? pendingChanges.error ?? completionReviews.error ?? cancellations.error ?? openReports.error ?? actionError} />

          {activeSection === "overview" && <section className={styles.metrics} aria-label="Chỉ số vận hành">
            <MetricCard tone="teal" icon="▣" label="HỒ SƠ SITTER CHỜ DUYỆT" value={pendingListings.loading ? "—" : String(pendingTotal)} detail="Hồ sơ cần xem xét" foot={`${changesTotal} đề xuất cập nhật`} />
            <MetricCard tone="violet" icon="♟" label="MẠNG LƯỚI SITTER" value={activeListings.loading ? "—" : String(activeTotal)} detail="Hồ sơ đang hoạt động" foot="Dịch vụ đã được duyệt" />
            <MetricCard tone="amber" icon="▤" label="ĐƠN CẦN XỬ LÝ" value={completionReviews.loading || cancellations.loading ? "—" : String(completionTotal + cancellationTotal)} detail="Hoàn tất & thanh toán" foot="Các trường hợp cần admin rà soát" />
            <MetricCard tone="mint" icon="◈" label="BÁO CÁO ĐANG MỞ" value={openReports.loading ? "—" : String(reportsTotal)} detail="Chờ xem xét" foot="Báo cáo từ người dùng" />
          </section>}

          {activeSection === "sitters" && <section className={styles.dashboardGrid}>
            <section id="sitters" className={styles.panel}>
              <div className={styles.panelHeading}>
                <span className={`${styles.panelIcon} ${styles.amberIcon}`}><PettelIcon name="award" /></span>
                <div><h2>Hàng chờ duyệt Sitter &amp; dịch vụ</h2><p>Hồ sơ mới và đề xuất chỉnh sửa dịch vụ sẽ được duyệt tại đây.</p></div>
                <span className={styles.queueCount}>Cần xử lý: {pendingListings.loading || pendingChanges.loading ? "…" : pendingReviewTotal}</span>
              </div>
              <div className={styles.listingQueue}>
                {pendingListings.loading && <p className={styles.emptyState}>Đang tải hàng đợi duyệt…</p>}
                {!pendingListings.loading && filteredListings.map((listing) => (
                  <article key={listing.id} className={styles.sitterCard}>
                    <div className={styles.sitterAvatar}>{listing.media?.[0]?.thumbnail_url ? <Image src={apiUrl(listing.media[0].thumbnail_url)} alt="" width={68} height={80} unoptimized /> : <PettelIcon name="paw" />}</div>
                    <div className={styles.sitterInfo}>
                      <div className={styles.sitterTitle}><h3>{listing.caregiver?.display_name ?? listing.caregiver?.name ?? listing.title}</h3><span>{listing.service_type}</span></div>
                      <p>{listing.title} · {listing.caregiver?.area_code ?? listing.area_code}</p>
                      <p className={styles.listingDescription}>{listing.description}</p>
                      <small>{money(listing.price_vnd)} / {listing.price_unit}</small>
                    </div>
                    <div className={styles.sitterActions}>
                      <button type="button" onClick={() => setPreviewListingId(listing.id)}>Xem hồ sơ</button>
                      {isAdmin ? <button disabled={busyListing === listing.id} onClick={() => void reviewListing(listing, "approve")}>{busyListing === listing.id ? "Đang lưu…" : "Phê duyệt"}</button> : <span className={styles.readOnlyBadge}>Chỉ xem</span>}
                    </div>
                    {isAdmin && <div className={styles.rejectRow}><input aria-label={`Lý do từ chối ${listing.title}`} value={reasonByListing[listing.id] ?? ""} onChange={(event) => setReasonByListing((current) => ({ ...current, [listing.id]: event.target.value }))} placeholder="Ghi chú nếu cần yêu cầu bổ sung hoặc từ chối" maxLength={500} /><button disabled={!reasonByListing[listing.id]?.trim() || busyListing === listing.id} onClick={() => void reviewListing(listing, "reject")}>Yêu cầu bổ sung</button></div>}
                  </article>
                ))}
                {!pendingChanges.loading && filteredChanges.map((change) => {
                  return <ChangeRequestReviewCard
                    key={`change-${change.id}`}
                    change={change}
                    isAdmin={isAdmin}
                    refresh={() => { pendingChanges.reload(); pendingListings.reload(); }}
                  />;
                })}
                {!pendingListings.loading && !pendingChanges.loading && filteredListings.length + filteredChanges.length === 0 && <p className={styles.emptyState}>{search ? "Không tìm thấy mục phù hợp." : "Hiện không có hồ sơ hoặc dịch vụ chờ duyệt."}</p>}
              </div>
              <div className={styles.panelFooter}><span>Đang hiển thị {filteredListings.length + filteredChanges.length} trong {pendingReviewTotal} mục chờ duyệt</span><button type="button" onClick={() => { pendingListings.reload(); pendingChanges.reload(); }}>Làm mới hàng đợi <PettelIcon name="arrow" /></button></div>
            </section>

            <aside className={styles.panel}>
              <div className={styles.panelHeading}>
                <span className={`${styles.panelIcon} ${styles.tealIcon}`}><PettelIcon name="bell" /></span>
                <div><h2>Radar Giám Sát Vận Hành</h2><p>Ưu tiên cần chú ý từ các hàng đợi</p></div>
                <span className={styles.livePill}><i /> LIVE</span>
              </div>
              <div className={styles.radarSummary}>
                <div><span>Đề xuất sửa hồ sơ</span><b>{pendingChanges.loading ? "—" : changesTotal}</b><small>đang chờ duyệt</small></div>
                <div><span>Đơn chờ hoàn tất</span><b>{completionReviews.loading ? "—" : completionReviews.data?.data.overdue_bookings.length ?? 0}</b><small>cần kiểm tra</small></div>
                <div><span>Đối soát lỗi</span><b>{completionReviews.loading ? "—" : completionReviews.data?.data.release_operations_for_review.length ?? 0}</b><small>đang rà soát</small></div>
                <div><span>Yêu cầu hủy</span><b>{cancellations.loading ? "—" : cancellationTotal}</b><small>cần xử lý thủ công</small></div>
              </div>
              <div className={styles.radarNotice}><PettelIcon name="shield" /><div><strong>Kiểm soát quyền truy cập</strong><p>Các thao tác quản trị được lưu dấu theo tài khoản thực hiện.</p></div></div>
              <button type="button" className={styles.panelLink} onClick={() => setActiveSection("bookings")}>Mở hàng đợi vận hành <PettelIcon name="arrow" /></button>
            </aside>
          </section>}

          {activeSection === "bookings" && <section className={styles.panel}>
            <div className={styles.panelHeading}>
              <span className={`${styles.panelIcon} ${styles.violetIcon}`}><PettelIcon name="calendar" /></span>
              <div><h2>Quản Lý Đơn Chăm Sóc &amp; Giám Sát Chất Lượng</h2><p>Những trường hợp hệ thống đã đưa vào hàng chờ admin rà soát.</p></div>
              <span className={styles.totalPill}>{operationRows.length} mục cần xem</span>
            </div>
            <div className={styles.tableWrap}>
              <table className={styles.operationsTable}>
                <thead><tr><th>MÃ BOOKING</th><th>HÀNG ĐỢI</th><th>TRẠNG THÁI / LÝ DO</th><th>THỜI GIAN</th><th>THAO TÁC</th></tr></thead>
                <tbody>
                  {operationRows.map((row) => <tr key={row.key}>
                    <td><strong>#{row.booking}</strong><small>Đơn chăm sóc</small></td>
                    <td>{row.type}</td>
                    <td><span className={`${styles.statePill} ${styles[row.tone]}`}>{row.reason}</span></td>
                    <td>{formatDate(row.time)}</td>
                    <td><a href={apiUrl(`/admin/booking-completion-reviews`)} target="_blank" rel="noreferrer" aria-label={`Xem ${row.booking}`}>Xem hàng đợi</a></td>
                  </tr>)}
                  {!completionReviews.loading && !cancellations.loading && operationRows.length === 0 && <tr><td colSpan={5} className={styles.emptyCell}>Không có đơn nào đang chờ admin rà soát.</td></tr>}
                </tbody>
              </table>
            </div>
            <div className={styles.panelFooter}><span>{completionReviews.loading || cancellations.loading ? "Đang đồng bộ hàng đợi…" : `Hiển thị ${operationRows.length} mục gần nhất`}</span><a href={apiUrl("/admin/booking-cancellations")} target="_blank" rel="noreferrer">Chi tiết hàng đợi <PettelIcon name="arrow" /></a></div>
          </section>}

          {activeSection === "reports" && <section className={styles.bottomGrid}>
            <div className={styles.panel}>
              <div className={styles.panelHeading}>
                <span className={`${styles.panelIcon} ${styles.redIcon}`}><PettelIcon name="shield" /></span>
                <div><h2>Báo Cáo &amp; An Toàn Cộng Đồng</h2><p>Các báo cáo người dùng đang chờ phân loại.</p></div>
                <span className={styles.queueCount}>{reportsTotal} đang mở</span>
              </div>
              <div className={styles.reportList}>
                {openReports.data?.data.map((report) => <div key={report.id} className={styles.reportRow}><span className={styles.reportDot} /><div><strong>Báo cáo #{report.id} · {report.reason_code}</strong><small>{report.target?.type ?? "Nội dung"} · {formatDate(report.created_at)}</small></div><span className={styles.reportStatus}>Mới</span></div>)}
                {openReports.loading && <p className={styles.emptyState}>Đang tải báo cáo…</p>}
                {!openReports.loading && reportsTotal === 0 && <p className={styles.emptyState}>Không có báo cáo nào đang mở.</p>}
              </div>
              <a className={styles.panelLink} href={apiUrl("/admin/reports?status=OPEN")} target="_blank" rel="noreferrer">Mở danh sách báo cáo <PettelIcon name="arrow" /></a>
            </div>
            <div className={`${styles.panel} ${styles.healthPanel}`}>
              <div className={styles.panelHeading}><span className={`${styles.panelIcon} ${styles.mintIcon}`}><PettelIcon name="heart" /></span><div><h2>Tình Trạng Hệ Thống</h2><p>Trạng thái ứng dụng PETTEL</p></div></div>
              <div className={styles.healthRows}>
                <div><span><i /> Web &amp; giao diện</span><b>Đang mở</b></div>
                <div><span><i /> API &amp; dữ liệu</span><b>{[pendingListings, activeListings, openReports].some((resource) => resource.error) ? "Có lỗi kết nối" : [pendingListings, activeListings, openReports].some((resource) => resource.loading) ? "Đang kiểm tra" : "Đang phản hồi"}</b></div>
                <div><span><i /> Hàng đợi xử lý</span><b>{completionReviews.loading || cancellations.loading ? "Đang đồng bộ" : "Đã đồng bộ"}</b></div>
              </div>
              <p className={styles.healthNote}>Tình trạng API và hàng đợi được suy ra từ dữ liệu trả về cho phiên này. Các số liệu doanh thu chỉ hiển thị khi backend cung cấp dữ liệu.</p>
            </div>
          </section>}

          {activeSection === "tools" && <section className={styles.toolsSection} aria-label="Báo cáo và công cụ dự án">
            <div className={styles.toolsIntro}>
              <span className={`${styles.panelIcon} ${styles.tealIcon}`}><PettelIcon name="shield" /></span>
              <div><h2>Công cụ vận hành PETTEL</h2><p>Mỗi nút sẽ mở công cụ trong một thẻ mới. Một số trang chỉ dùng được khi ứng dụng đang chạy ở máy này.</p></div>
            </div>
            <div className={styles.toolsGrid}>
              <ToolCard title="Báo cáo PETTEL" description="Mở trang báo cáo trong Filament Admin." href={apiUrl("/admin/reports")} badge="Filament Admin" />
              <ToolCard title="Mailpit" description="Xem email thử nghiệm, chẳng hạn thư xác nhận và đặt lại mật khẩu." href="http://localhost:8025" badge="Email local" />
              <ToolCard title="Laravel Pulse" description="Theo dõi hoạt động, hiệu năng và các chỉ số của ứng dụng." href={apiUrl("/pulse")} badge="Giám sát" />
              <ToolCard title="Laravel Telescope" description="Xem request, exception, job và truy vấn trong môi trường local." href={apiUrl("/telescope")} badge="Debug local" />
              <ToolCard title="Tài liệu API" description="Tra cứu các endpoint và schema API được Scramble tạo tự động." href={apiUrl("/docs/api")} badge="Scramble" />
              <ToolCard title="Kiểm tra API" description="Xác nhận API backend đang phản hồi." href={apiUrl("/up")} badge="Health check" />
              <ToolCard title="Trang PETTEL" description="Mở giao diện website để kiểm tra các luồng phía người dùng." href="http://localhost:3000" badge="Web app" />
            </div>
            <div className={styles.toolsNote}><strong>Dịch vụ nền</strong><span>MySQL, Redis và Reverb đang chạy qua Docker; dự án chưa cài giao diện web riêng cho các dịch vụ này.</span></div>
          </section>}
          <footer className={styles.footer}>PETTEL Admin Portal <span>·</span> Bảng điều khiển quản trị nội bộ</footer>
        </main>
      </div>
      {(() => {
        const listing = pendingListings.data?.data.find((item) => item.id === previewListingId);
        if (!listing) return null;
        return <div className={styles.previewOverlay} role="presentation" onMouseDown={(event) => { if (event.target === event.currentTarget) setPreviewListingId(null); }}>
          <section className={styles.previewDialog} role="dialog" aria-modal="true" aria-labelledby="listing-preview-title">
            <header className={styles.previewHeader}>
              <div><span>HỒ SƠ DỊCH VỤ · {listing.status === "PENDING_REVIEW" ? "CHỜ DUYỆT" : listing.status}</span><h2 id="listing-preview-title">{listing.title}</h2></div>
              <button type="button" onClick={() => setPreviewListingId(null)} aria-label="Đóng bản xem trước">×</button>
            </header>
            <div className={styles.previewContent}>
              {!!listing.media?.length && <div className={styles.previewMedia}>{listing.media.map((media, index) => <Image key={media.image_url} src={apiUrl(media.image_url)} alt={`Ảnh dịch vụ ${index + 1}`} width={640} height={400} unoptimized />)}</div>}
              <div className={styles.previewFacts}>
                <div><small>Người chăm</small><strong>{listing.caregiver?.display_name ?? listing.caregiver?.name ?? "Sitter PETTEL"}</strong></div>
                <div><small>Loại dịch vụ</small><strong>{listing.service_type === "BOARDING" ? "Trông giữ qua đêm" : listing.service_type === "DAYCARE" ? "Chăm sóc ban ngày" : listing.service_type}</strong></div>
                <div><small>Khu vực</small><strong>{listing.area_code}</strong></div>
                <div><small>Mức giá</small><strong>{money(listing.price_vnd)} / {listing.price_unit === "HOUR" ? "giờ" : listing.price_unit === "NIGHT" ? "đêm" : listing.price_unit}</strong></div>
                <div><small>Sức chứa</small><strong>{listing.capacity ?? "—"} thú cưng</strong></div>
                <div><small>Mã hồ sơ</small><strong>#{listing.id}</strong></div>
              </div>
              <section className={styles.previewDescription}><h3>Giới thiệu dịch vụ</h3><p>{listing.description || "Chưa có nội dung giới thiệu."}</p></section>
              <section className={styles.previewDescription}><h3>Thú cưng có thể nhận</h3><p>{listing.acceptance_rules_json?.species?.length ? listing.acceptance_rules_json.species.join(", ") : "Chưa giới hạn theo loài"}{listing.acceptance_rules_json?.sizes?.length ? ` · Cỡ: ${listing.acceptance_rules_json.sizes.join(", ")}` : ""}</p></section>
            </div>
            <footer className={styles.previewFooter}><span>Thông tin lấy từ hồ sơ đang chờ xét duyệt.</span><button type="button" onClick={() => setPreviewListingId(null)}>Đóng</button></footer>
          </section>
        </div>;
      })()}
    </div>
  );
}

function ToolCard({ title, description, href, badge }: { title: string; description: string; href: string; badge: string }) {
  return <article className={styles.toolCard}>
    <div className={styles.toolCardTop}><span className={styles.toolBadge}><i />{badge}</span><span className={styles.toolArrow}>↗</span></div>
    <h3>{title}</h3>
    <p>{description}</p>
    <a href={href} target="_blank" rel="noreferrer">Mở công cụ <PettelIcon name="arrow" /></a>
  </article>;
}

function MetricCard({ tone, icon, label, value, detail, foot }: { tone: string; icon: string; label: string; value: string; detail: string; foot: string }) {
  return <article className={`${styles.metricCard} ${styles[tone]}`}><div className={styles.metricTop}><span>{label}</span><i>{icon}</i></div><strong>{value}</strong><p>{detail}</p><small>{foot}</small></article>;
}

function formatDate(value?: string | null) {
  if (!value) return "—";
  const date = new Date(value);
  if (Number.isNaN(date.getTime())) return "—";
  return new Intl.DateTimeFormat("vi-VN", { day: "2-digit", month: "2-digit", hour: "2-digit", minute: "2-digit" }).format(date);
}

