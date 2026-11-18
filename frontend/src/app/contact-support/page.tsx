import type { Metadata } from "next";
import { ContactSupportPage } from "@/features/support/contact-support-page";

export const metadata: Metadata = {
  title: "Liên hệ và Hỗ trợ | PETTEL",
  description: "Liên hệ đội ngũ PETTEL để được hỗ trợ về đơn chăm sóc, tài khoản và dịch vụ.",
};

export default function Page() {
  return <ContactSupportPage />;
}
