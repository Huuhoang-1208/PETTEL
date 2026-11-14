import type { Metadata } from "next";
import { redirect } from "next/navigation";

export const metadata: Metadata = { title: "Nhà riêng Bảo Trâm | PETTEL" };

export default function Page() {
  redirect("/services");
}
