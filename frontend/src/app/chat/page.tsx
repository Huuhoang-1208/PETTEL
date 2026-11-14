import { ChatPage } from "@/features/chat/chat-page";
import type { Metadata } from "next";
import { Suspense } from "react";

export const metadata: Metadata = { title: "Chat | PETTEL" };

export default function Page() {
  return (
    <Suspense fallback={<p>Đang tải hội thoại...</p>}>
      <ChatPage />
    </Suspense>
  );
}
