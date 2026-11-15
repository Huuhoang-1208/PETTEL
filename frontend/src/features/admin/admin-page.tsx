"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { RequireSession } from "@/features/auth/components/require-session";
import { type Change } from "@/features/admin/types";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Listing, type Page } from "@/lib/api/types";
import { apiUrl, money } from "@/lib/format";
import { useSession } from "@/providers/session-provider";
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
];

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
  const [activeSection, setActiveSection] = useState("overview");
  const [search, setSearch] = useState("");
  const [reasonByListing, setReasonByListing] = useState<Record<string, string>>({});
  const [busyListing, setBusyListing] = useState<string | null>(null);
  const [actionError, setActionError] = useState<unknown>();

  const pendingListings = useApi<Page<Listing>>(isStaff ? "/api/admin/listings?status=PENDING_REVIEW&per_page=3" : null, 60000);
  const activeListings = useApi<Page<Listing>>(isStaff ? "/api/admin/listings?status=ACTIVE&per_page=1" : null, 60000);
  const pendingChanges = useApi<Page<Change>>(isStaff ? "/api/admin/listing-change-requests?per_page=1" : null, 60000);
  const completionReviews = useApi<BookingOperations>(isStaff ? "/api/admin/booking-completion-reviews" : null, 60000);
  const cancellations = useApi<{ data: CancellationOperation[] }>(isStaff ? "/api/admin/booking-cancellations" : null, 60000);
  const openReports = useApi<Page<AdminReport>>(isStaff ? "/api/admin/reports?status=OPEN&per_page=3" : null, 60000);

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

  async function reviewListing(listing: Listing, action: "approve" | "reject") {
    if (!isAdmin) return;
    const reason = reasonByListing[listing.id]?.trim() ?? "";
    if (action === "reject" && !reason) return;
    setBusyListing(listing.id);
    setActionError(undefined);
    try {
      await write("post", `/api/admin/listings/${listing.id}/${action}`, reason ? { reason } : undefined);
      setReasonByListing((current) => ({ ...current, [listing.id]: "" }));
      pendingListings.reload();
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
            <a
              key={item.id}
              href={`#${item.id}`}
              className={activeSection === item.id ? styles.activeNav : undefined}
              onClick={() => setActiveSection(item.id)}
            >
              <span className={styles.navIcon}>{item.icon}</span><span>{item.label}</span>
              {item.id === "sitters" && pendingTotal > 0 && <b>{pendingTotal}</b>}
              {item.id === "reports" && reportsTotal > 0 && <b className={styles.redCount}>{reportsTotal}</b>}
            </a>
          ))}
          <a href={`${apiUrl("/admin/reports")}`} target="_blank" rel="noreferrer">
            <span className={styles.navIcon}>↗</span><span>Mở trung tâm báo cáo</span>
          </a>
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
          <Link className={styles.topIcon} href="#reports" aria-label="Xem cảnh báo"><PettelIcon name="bell" />{reportsTotal > 0 && <i />}</Link>
          <Link className={styles.topIcon} href="#bookings" aria-label="Xem công việc"><PettelIcon name="message" />{completionTotal + cancellationTotal > 0 && <i />}</Link>
          <div className={styles.profile}><span className={styles.userAvatar}><PettelIcon name="user" /></span><span><strong>{user?.name}</strong><small>Khu vực VN • North</small></span><PettelIcon name="chevron" /></div>
        </header>

        <main className={styles.content}>
          <section id="overview" className={styles.pageHeading}>
            <div><span className={styles.kicker}><i /> HỆ THỐNG VẬN HÀNH TẬP TRUNG</span><h1>Tổng Quan Vận Hành Hệ Thống PETTEL</h1><p>Chào buổi sáng, {user?.name ?? "Quản trị viên"} · {isSupport ? "Chế độ hỗ trợ chỉ xem." : "Số liệu đồng bộ từ các hàng đợi vận hành hiện có."}</p></div>
            <div className={styles.headingActions}><span className={styles.datePill}><PettelIcon name="calendar" /> Theo dõi realtime</span><a className={styles.exportButton} href={apiUrl("/admin/reports")} target="_blank" rel="noreferrer">↗ Mở báo cáo</a></div>
          </section>

          <ApiError error={pendingListings.error ?? activeListings.error ?? pendingChanges.error ?? completionReviews.error ?? cancellations.error ?? openReports.error ?? actionError} />

          <section className={styles.metrics} aria-label="Chỉ số vận hành">
            <MetricCard tone="teal" icon="▣" label="HỒ SƠ SITTER CHỜ DUYỆT" value={pendingListings.loading ? "—" : String(pendingTotal)} detail="Hồ sơ cần xem xét" foot={`${changesTotal} đề xuất cập nhật`} />
            <MetricCard tone="violet" icon="♟" label="MẠNG LƯỚI SITTER" value={activeListings.loading ? "—" : String(activeTotal)} detail="Hồ sơ đang hoạt động" foot="Dịch vụ đã được duyệt" />
            <MetricCard tone="amber" icon="▤" label="ĐƠN CẦN XỬ LÝ" value={completionReviews.loading || cancellations.loading ? "—" : String(completionTotal + cancellationTotal)} detail="Hoàn tất & thanh toán" foot="Các trường hợp cần admin rà soát" />
            <MetricCard tone="mint" icon="◈" label="BÁO CÁO ĐANG MỞ" value={openReports.loading ? "—" : String(reportsTotal)} detail="Chờ xem xét" foot="Báo cáo từ người dùng" />
          </section>

          <section className={styles.dashboardGrid}>
            <section id="sitters" className={styles.panel}>
              <div className={styles.panelHeading}>
                <span className={`${styles.panelIcon} ${styles.amberIcon}`}><PettelIcon name="award" /></span>
                <div><h2>Hàng Chờ Duyệt Hồ Sơ Sitter Mới</h2><p>Kiểm tra hồ sơ và thông tin dịch vụ trước khi phê chuẩn.</p></div>
                <span className={styles.queueCount}>Cần xử lý: {pendingListings.loading ? "…" : pendingTotal}</span>
              </div>
              <div className={styles.listingQueue}>
                {pendingListings.loading && <p className={styles.emptyState}>Đang tải hàng đợi duyệt…</p>}
                {!pendingListings.loading && filteredListings.map((listing) => (
                  <article key={listing.id} className={styles.sitterCard}>
                    <div className={styles.sitterAvatar}>{listing.media?.[0]?.thumbnail_url ? <img src={apiUrl(listing.media[0].thumbnail_url)} alt="" /> : <PettelIcon name="paw" />}</div>
                    <div className={styles.sitterInfo}>
                      <div className={styles.sitterTitle}><h3>{listing.caregiver?.display_name ?? listing.caregiver?.name ?? listing.title}</h3><span>{listing.service_type}</span></div>
                      <p>{listing.title} · {listing.caregiver?.area_code ?? listing.area_code}</p>
                      <p className={styles.listingDescription}>{listing.description}</p>
                      <small>{money(listing.price_vnd)} / {listing.price_unit}</small>
                    </div>
                    <div className={styles.sitterActions}>
                      <Link href={`/detail?id=${listing.id}`}>Xem hồ sơ</Link>
                      {isAdmin ? <button disabled={busyListing === listing.id} onClick={() => void reviewListing(listing, "approve")}>{busyListing === listing.id ? "Đang lưu…" : "Phê duyệt"}</button> : <span className={styles.readOnlyBadge}>Chỉ xem</span>}
                    </div>
                    {isAdmin && <div className={styles.rejectRow}><input aria-label={`Lý do từ chối ${listing.title}`} value={reasonByListing[listing.id] ?? ""} onChange={(event) => setReasonByListing((current) => ({ ...current, [listing.id]: event.target.value }))} placeholder="Ghi chú nếu cần yêu cầu bổ sung hoặc từ chối" maxLength={500} /><button disabled={!reasonByListing[listing.id]?.trim() || busyListing === listing.id} onClick={() => void reviewListing(listing, "reject")}>Yêu cầu bổ sung</button></div>}
                  </article>
                ))}
                {!pendingListings.loading && filteredListings.length === 0 && <p className={styles.emptyState}>{search ? "Không tìm thấy hồ sơ phù hợp." : "Hiện không có hồ sơ Sitter chờ duyệt."}</p>}
              </div>
              <div className={styles.panelFooter}><span>Đang hiển thị {filteredListings.length} trong {pendingTotal} hồ sơ chờ duyệt</span><a href="#sitters" onClick={() => pendingListings.reload()}>Làm mới hàng đợi <PettelIcon name="arrow" /></a></div>
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
              <a className={styles.panelLink} href="#bookings" onClick={() => setActiveSection("bookings")}>Mở hàng đợi vận hành <PettelIcon name="arrow" /></a>
            </aside>
          </section>

          <section id="bookings" className={styles.panel}>
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
          </section>

          <section id="reports" className={styles.bottomGrid}>
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
          </section>
          <footer className={styles.footer}>PETTEL Admin Portal <span>·</span> Bảng điều khiển quản trị nội bộ</footer>
        </main>
      </div>
    </div>
  );
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
