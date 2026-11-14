"use client";

import Link from "next/link";
import { usePathname } from "next/navigation";
import { createContext, useContext, useRef, useState, type ReactNode } from "react";
import { useApi, type User, type Booking, type Page, errorText, unauthorized, write } from "@/lib/api/pettel";
import { PettelIcon } from "./pettel-icon";

const Session = createContext<{ user: User | null; loading: boolean; error: unknown; refresh: () => void }>({ user: null, loading: true, error: null, refresh: () => {} });

export function SessionProvider({ children }: { children: ReactNode }) {
  const resource = useApi<User>("/api/user");
  return <Session.Provider value={{ user: resource.data ?? null, loading: resource.loading, error: unauthorized(resource.error) ? null : resource.error, refresh: resource.reload }}>{children}</Session.Provider>;
}
export const useSession = () => useContext(Session);

export function SiteHeader() {
  const { user, refresh } = useSession();
  const pathname = usePathname();
  const menu = useRef<HTMLDetailsElement>(null);
  const mobileMenu = useRef<HTMLDetailsElement>(null);
  const [logoutError, setLogoutError] = useState<unknown>();
  const [loggingOut, setLoggingOut] = useState(false);
  const bookings = useApi<Page<Booking>>(user ? "/api/bookings?per_page=20" : null, 60000);
  const needsAttention = bookings.data?.data.some((booking) => booking.participant_role === "OWNER"
    ? ["AWAITING_PAYMENT", "AWAITING_CONFIRMATION"].includes(booking.status)
    : booking.status === "REQUESTED");
  const links = [
    { href: "/", label: "Khám phá", active: pathname === "/" },
    { href: "/services", label: "Dịch vụ", active: pathname === "/services" || pathname === "/detail" },
    { href: "/caregiver", label: "Trở thành Sitter", active: pathname === "/caregiver" },
    { href: "/#ve-pettel", label: "Về PETTEL", active: false },
    { href: "/#cong-dong", label: "Cộng đồng", active: false },
    { href: "/pet-profile", label: "Hồ sơ thú cưng", active: pathname === "/pet-profile" },
  ];
  const closeMenus = () => {
    if (menu.current) menu.current.open = false;
    if (mobileMenu.current) mobileMenu.current.open = false;
  };

  async function logout() {
    setLoggingOut(true); setLogoutError(undefined);
    try { await write("post", "/logout"); closeMenus(); refresh(); }
    catch (error) { setLogoutError(error); }
    finally { setLoggingOut(false); }
  }

  return <header className="pettel-header">
    <Link href="/" className="pettel-brand" aria-label="PETTEL — Trang chủ" onClick={closeMenus}>
      <span className="pettel-brand-mark"><PettelIcon name="paw" /></span><span>PETTEL</span>
    </Link>
    <nav className="pettel-nav" aria-label="Điều hướng chính">
      {links.map((link) => <Link key={link.href} href={link.href} className={link.active ? "is-active" : undefined} aria-current={link.active ? "page" : undefined}>{link.label}</Link>)}
    </nav>
    <div className="pettel-header-actions">
      <Link className="pettel-book-link" href="/services"><PettelIcon name="plus" /><span>Đăng ký gửi pet</span></Link>
      <span className="pettel-language" aria-label="Ngôn ngữ: Tiếng Việt"><PettelIcon name="globe" /><span>VN</span></span>
      <Link className="pettel-icon-link pettel-bookings-link" href="/bookings" aria-label={needsAttention ? "Đơn chăm sóc có yêu cầu cần xử lý" : "Xem đơn chăm sóc"}>
        <PettelIcon name="bell" />{needsAttention && <span className="pettel-attention-dot" />}
      </Link>
      <details className="pettel-account" ref={menu} onKeyDown={(event) => { if (event.key === "Escape") closeMenus(); }}>
        <summary aria-label={user ? `Tài khoản ${user.name}` : "Tài khoản"}><span className="pettel-avatar"><PettelIcon name="user" /></span><PettelIcon name="chevron" /></summary>
        <div className="pettel-account-menu">
          {user ? <>
            <div className="pettel-account-info"><strong>{user.name}</strong><span>{user.email}</span></div>
            <Link href="/bookings" onClick={closeMenus}>Đơn chăm sóc của tôi</Link>
            <Link href="/pet-profile" onClick={closeMenus}>Hồ sơ thú cưng</Link>
            <Link href="/caregiver" onClick={closeMenus}>Khu vực người chăm</Link>
            {user.roles?.some((role) => role.role === "ADMIN") && <Link href="/admin" onClick={closeMenus}>Quản trị dịch vụ</Link>}
            <ApiError error={logoutError} />
            <button disabled={loggingOut} onClick={() => void logout()}>{loggingOut ? "Đang đăng xuất..." : "Đăng xuất"}</button>
          </> : <><Link href="/login" onClick={closeMenus}>Đăng nhập / Đăng ký</Link><Link href="/bookings" onClick={closeMenus}>Đơn chăm sóc của tôi</Link></>}
        </div>
      </details>
      <details className="pettel-mobile-nav" ref={mobileMenu} onKeyDown={(event) => { if (event.key === "Escape") closeMenus(); }}>
        <summary aria-label="Mở menu điều hướng"><PettelIcon name="menu" /></summary>
        <nav aria-label="Điều hướng trên điện thoại">{links.map((link) => <Link key={link.href} href={link.href} aria-current={link.active ? "page" : undefined} onClick={closeMenus}>{link.label}</Link>)}</nav>
      </details>
    </div>
  </header>;
}

export function RequireSession({ children, next = "/" }: { children: ReactNode; next?: string }) {
  const { user, loading, error, refresh } = useSession();
  if (loading) return <p className="app-notice">Đang kiểm tra phiên đăng nhập...</p>;
  if (error) return <div className="app-notice" role="alert">{errorText(error)} <button onClick={refresh}>Thử lại</button></div>;
  if (!user) return <div className="app-notice"><p>Đăng nhập để quản lý thú cưng, đơn chăm sóc và tin nhắn.</p><Link className="app-button" href={`/login?next=${encodeURIComponent(next)}`}>Đăng nhập / Đăng ký</Link></div>;
  return children;
}

export function ApiError({ error }: { error: unknown }) {
  return error ? <p className="app-error" role="alert">{errorText(error)}</p> : null;
}
