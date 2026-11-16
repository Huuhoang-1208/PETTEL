"use client";

import { ApiError } from "@/components/ui/api-error";
import { PettelIcon } from "@/components/ui/pettel-icon";
import { useApi } from "@/hooks/use-api";
import { write } from "@/lib/api/request";
import { type Booking, type Page } from "@/lib/api/types";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { usePathname } from "next/navigation";
import { useRef, useState } from "react";

export function SiteHeader() {
  const { user, refresh } = useSession();
  const pathname = usePathname();
  const menu = useRef<HTMLDetailsElement>(null);
  const mobileMenu = useRef<HTMLDetailsElement>(null);
  const [logoutError, setLogoutError] = useState<unknown>();
  const [loggingOut, setLoggingOut] = useState(false);
  const isCaregiver = user?.roles?.some((role) => role.role === "CAREGIVER") ?? false;
  const bookings = useApi<Page<Booking>>(user ? "/api/bookings?per_page=20" : null, 60000);
  const needsAttention = bookings.data?.data.some((booking) =>
    booking.participant_role === "OWNER"
      ? ["AWAITING_PAYMENT", "AWAITING_CONFIRMATION"].includes(booking.status)
      : booking.status === "REQUESTED",
  );
  const links = [
    { href: "/", label: "Khám phá", active: pathname === "/" },
    {
      href: "/services",
      label: "Dịch vụ",
      active: pathname === "/services" || pathname === "/detail",
    },
    {
      href: isCaregiver ? "/caregiver/manage" : "/caregiver",
      label: "Trở thành Sitter",
      active: pathname === "/caregiver" || pathname.startsWith("/caregiver/manage"),
    },
    { href: "/about", label: "Về PETTEL", active: pathname === "/about" },
    {
      href: "/pet-profile",
      label: "Hồ sơ thú cưng",
      active: pathname === "/pet-profile",
    },
  ];
  const closeMenus = () => {
    if (menu.current) menu.current.open = false;
    if (mobileMenu.current) mobileMenu.current.open = false;
  };

  async function logout() {
    setLoggingOut(true);
    setLogoutError(undefined);
    try {
      await write("post", "/logout");
      closeMenus();
      refresh();
    } catch (error) {
      setLogoutError(error);
    } finally {
      setLoggingOut(false);
    }
  }

  return (
    <header className="pettel-header">
      <Link href="/" className="pettel-brand" aria-label="PETTEL — Trang chủ" onClick={closeMenus}>
        <span className="pettel-brand-mark">
          <PettelIcon name="paw" />
        </span>
        <span>PETTEL</span>
      </Link>
      <nav className="pettel-nav" aria-label="Điều hướng chính">
        {links.map((link) => (
          <Link
            key={link.href}
            href={link.href}
            className={link.active ? "is-active" : undefined}
            aria-current={link.active ? "page" : undefined}
          >
            {link.label}
          </Link>
        ))}
      </nav>
      <div className="pettel-header-actions">
        <Link className="pettel-book-link" href="/services">
          <PettelIcon name="plus" />
          <span>Đăng ký gửi pet</span>
        </Link>
        <span className="pettel-language" aria-label="Ngôn ngữ: Tiếng Việt">
          <PettelIcon name="globe" />
          <span>VN</span>
        </span>
        {user && (
          <>
            <Link
              className="pettel-icon-link pettel-message-link"
              href="/chat"
              aria-label="Mở tin nhắn"
              title="Tin nhắn"
              aria-current={pathname === "/chat" ? "page" : undefined}
              onClick={closeMenus}
            >
              <PettelIcon name="message" />
            </Link>
            <Link
              className="pettel-icon-link pettel-bookings-link"
              href="/bookings"
              aria-label={needsAttention ? "Đơn chăm sóc có yêu cầu cần xử lý" : "Xem đơn chăm sóc"}
            >
              <PettelIcon name="bell" />
              {needsAttention && <span className="pettel-attention-dot" />}
            </Link>
          </>
        )}
        {user ? (
          <details
            className="pettel-account"
            ref={menu}
            onKeyDown={(event) => {
              if (event.key === "Escape") closeMenus();
            }}
          >
            <summary aria-label={`Tài khoản ${user.name}`}>
              <span className="pettel-avatar">
                <PettelIcon name="user" />
              </span>
              <PettelIcon name="chevron" />
            </summary>
            <div className="pettel-account-menu">
              <div className="pettel-account-info">
                <strong>{user.name}</strong>
                <span>{user.email}</span>
              </div>
              <Link href="/owner-profile" onClick={closeMenus} aria-current={pathname === "/owner-profile" ? "page" : undefined}>
                Hồ sơ chủ nuôi
              </Link>
              <Link href="/bookings" onClick={closeMenus}>
                Đơn chăm sóc của tôi
              </Link>
              <Link href="/pet-profile" onClick={closeMenus}>
                Hồ sơ thú cưng
              </Link>
              {isCaregiver ? (
                <Link href="/caregiver/manage" onClick={closeMenus} aria-current={pathname.startsWith("/caregiver/manage") ? "page" : undefined}>
                  Quản lý Sitter
                </Link>
              ) : (
                <Link href="/caregiver" onClick={closeMenus}>
                  Khu vực người chăm
                </Link>
              )}
              {user.roles?.some((role) => role.role === "ADMIN") && (
                <Link href="/admin" onClick={closeMenus}>
                  Quản trị dịch vụ
                </Link>
              )}
              <Link href="/contact-support" onClick={closeMenus} aria-current={pathname === "/contact-support" ? "page" : undefined}>
                Liên hệ và Hỗ trợ
              </Link>
              <ApiError error={logoutError} />
              <button disabled={loggingOut} onClick={() => void logout()}>
                {loggingOut ? "Đang đăng xuất..." : "Đăng xuất"}
              </button>
            </div>
          </details>
        ) : (
          <Link className="pettel-login-link" href="/login" aria-label="Đăng nhập" onClick={closeMenus}>
            <PettelIcon name="user" />
            <span>Đăng nhập</span>
          </Link>
        )}
        <details
          className="pettel-mobile-nav"
          ref={mobileMenu}
          onKeyDown={(event) => {
            if (event.key === "Escape") closeMenus();
          }}
        >
          <summary aria-label="Mở menu điều hướng">
            <PettelIcon name="menu" />
          </summary>
          <nav aria-label="Điều hướng trên điện thoại">
            {links.map((link) => (
              <Link
                key={link.href}
                href={link.href}
                aria-current={link.active ? "page" : undefined}
                onClick={closeMenus}
              >
                {link.label}
              </Link>
            ))}
          </nav>
        </details>
      </div>
    </header>
  );
}
