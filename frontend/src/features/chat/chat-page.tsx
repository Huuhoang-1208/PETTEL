"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { RequireSession } from "@/features/auth/components/require-session";
import { ChatWorkspace } from "@/features/chat/components/chat-workspace";
import { useSearchParams } from "next/navigation";

export function ChatPage() {
  const params = useSearchParams();
  const bookingId = params.get("booking");
  const inquiryId = params.get("inquiry");
  return (
    <>
      <SiteHeader />
      <RequireSession
        next={
          inquiryId
            ? `/chat?inquiry=${inquiryId}`
            : bookingId
              ? `/chat?booking=${bookingId}`
              : "/chat"
        }
        redirectToLogin
      >
        <ChatWorkspace inquiryId={inquiryId} bookingId={bookingId} />
      </RequireSession>
    </>
  );
}
