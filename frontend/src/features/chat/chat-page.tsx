"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { RequireSession } from "@/features/auth/components/require-session";
import { Conversation } from "@/features/chat/components/conversation";
import Link from "next/link";
import { useSearchParams } from "next/navigation";

export function ChatPage() {
  const bookingId = useSearchParams().get("booking");
  return (
    <>
      <SiteHeader />
      <RequireSession next={bookingId ? `/chat?booking=${bookingId}` : "/chat"}>
        {bookingId && /^\d+$/.test(bookingId) ? (
          <Conversation key={bookingId} id={bookingId} />
        ) : (
          <div className="app-notice">
            Chat được gắn với từng đơn chăm sóc.{" "}
            <Link href="/bookings">Chọn một đơn để nhắn tin</Link>.
          </div>
        )}
      </RequireSession>
    </>
  );
}
