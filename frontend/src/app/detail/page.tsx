import type { Metadata } from "next";
import { Suspense } from "react";
import { LiveDetail } from "@/components/pettel/live-detail";

export const metadata: Metadata = { title: "Chi tiết Sitter | PETTEL" };

export default function Page() {
  return <Suspense fallback={<p>Đang tải...</p>}><LiveDetail /></Suspense>;
}
