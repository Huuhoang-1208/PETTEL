import type { Metadata } from "next";
import { Suspense } from "react";
import { LiveChat } from "@/components/pettel/live-chat";

export const metadata: Metadata = { title: "Chat | PETTEL" };

export default function Page() {
  return <Suspense fallback={<p>Đang tải hội thoại...</p>}><LiveChat /></Suspense>;
}
