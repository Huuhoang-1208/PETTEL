import type { Booking, Inquiry, Listing, Message } from "@/lib/api/types";
import { apiUrl } from "@/lib/format";

export type ChatKind = "inquiries" | "bookings";
export type ChatThread = { id: string; kind: ChatKind; title: string; subtitle: string; listingId: string; petName?: string | null; status?: string };
export type ChatRecord = Partial<Booking & Inquiry>;
export type CareContext = NonNullable<Message["context"]>;
export function bookingDetailHref(record?: ChatRecord) {
  return record?.participant_role === "CAREGIVER" && record.id
    ? `/caregiver/manage?tab=bookings&open=${encodeURIComponent(record.id)}`
    : "/owner-profile#history";
}
export const dong = (value: number) => `${value.toLocaleString("vi-VN")}đ`;
export function listingPhoto(listing?: Listing, avatar = false) {
  const photo = listing?.caregiver?.demo?.photo_file;
  if (avatar && photo) return `/images/pet-sitters/${encodeURIComponent(photo)}`;
  return listing?.media?.[0] ? apiUrl(listing.media[0].image_url) : photo ? `/images/pet-sitters/${encodeURIComponent(photo)}` : null;
}
export function contextFor(record: ChatRecord | undefined, messages: Message[]): CareContext | null {
  const saved = [...messages].reverse().find((message) => message.context)?.context;
  if (saved) return saved;
  if (!record?.start_at) return null;
  return { pet_name: record.pet_name ?? null, pet_breed: null, pet_weight_kg: null, service_type: "", start_at: record.start_at, end_at: record.end_at ?? null };
}
export function careEstimate(record: ChatRecord | undefined, context: CareContext | null, listing?: Listing) {
  const start = context?.start_at;
  const end = context?.end_at;
  const unit = listing?.price_unit === "HOUR" ? "giờ" : "đêm";
  const duration = start && end ? new Date(end).getTime() - new Date(start).getTime() : 0;
  const quantity = duration > 0 && listing ? Math.ceil(duration / (listing.price_unit === "HOUR" ? 3600000 : 86400000)) : null;
  const total = record?.price_snapshot_vnd ?? (quantity && listing ? quantity * listing.price_vnd : null);
  return { unit, quantity, total };
}
