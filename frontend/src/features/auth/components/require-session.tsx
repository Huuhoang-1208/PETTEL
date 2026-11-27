"use client";

import { errorText } from "@/lib/api/errors";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, type ReactNode } from "react";

export function RequireSession({
  children,
  next = "/",
  redirectToLogin = false,
}: {
  children: ReactNode;
  next?: string;
  redirectToLogin?: boolean;
}) {
  const { user, loading, error, refresh } = useSession();
  const router = useRouter();

  useEffect(() => {
    if (redirectToLogin && !loading && !error && !user) {
      router.replace(`/login?next=${encodeURIComponent(next)}`);
    }
  }, [error, loading, next, redirectToLogin, router, user]);

  if (loading) return <p className="app-notice">Đang kiểm tra phiên đăng nhập...</p>;
  if (error)
    return (
      <div className="app-notice" role="alert">
        {errorText(error)} <button onClick={refresh}>Thử lại</button>
      </div>
    );
  if (!user)
    if (redirectToLogin) return <p className="app-notice">Đang chuyển đến trang đăng nhập...</p>;
  if (!user)
    return (
      <div className="app-notice">
        <p>Đăng nhập để quản lý thú cưng, đơn chăm sóc và tin nhắn.</p>
        <Link className="app-button" href={`/login?next=${encodeURIComponent(next)}`}>
          Đăng nhập / Đăng ký
        </Link>
      </div>
    );
  return children;
}
