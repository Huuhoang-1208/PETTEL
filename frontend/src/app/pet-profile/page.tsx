import { PetsPage } from "@/features/pets/pets-page";
import type { Metadata } from "next";

export const metadata: Metadata = { title: "Hồ sơ thú cưng | PETTEL" };

export default function Page() {
  return <PetsPage />;
}
