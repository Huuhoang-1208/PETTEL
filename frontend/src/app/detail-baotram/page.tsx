import type { Metadata } from "next";
import { redirect } from "next/navigation";

export const metadata: Metadata = { title: "Căn hộ Bảo Trâm | PETTEL" };

export default function Page() {
  redirect("/services");
}
