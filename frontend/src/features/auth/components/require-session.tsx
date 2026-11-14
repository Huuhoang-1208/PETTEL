"use client";

import { errorText } from "@/lib/api/errors";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { type ReactNode } from "react";

export function RequireSession({ children, next = "/" }: { children: ReactNode; next?: string }) {
  const { user, loading, error, refresh } = useSession();
  if (loading) return <p className="app-notice">Đang kiểm tra phiên đăng nhập...</p>;
  if (error)
    return (
      <div className="app-notice" role="alert">
        {errorText(error)} <button onClick={refresh}>Thử lại</button>
      </div>
    );
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
