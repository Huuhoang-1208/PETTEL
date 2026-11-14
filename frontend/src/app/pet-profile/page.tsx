import type { Metadata } from "next";
import { LivePets } from "@/components/pettel/live-pets";

export const metadata: Metadata = { title: "Hồ sơ thú cưng | PETTEL" };

export default function Page() {
  return <LivePets />;
}
