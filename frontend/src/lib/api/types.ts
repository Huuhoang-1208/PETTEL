export type User = {
  id: number | string;
  name: string;
  email: string;
  roles?: { role: string }[];
};

export type Pet = {
  id: string;
  name: string;
  species: string;
  sex: string | null;
  size: string | null;
  birth_date: string | null;
  age_note: string | null;
  diet_notes: string | null;
  vaccination_note: string | null;
  temperament: string | null;
  special_needs: string | null;
  status: string;
};

export type Listing = {
  id: string;
  title: string;
  description: string;
  service_type: string;
  area_code: string;
  price_vnd: number;
  price_unit: string;
  price_total_vnd: number | null;
  rating_average: number | null;
  review_count: number;
  acceptance_rules_json: { species: string[]; sizes: string[] };
  media?: { image_url: string; thumbnail_url: string }[];
  match_reasons?: string[];
  status?: string;
};

export type Booking = {
  id: string;
  status: string;
  pet_id: string;
  listing_id: string;
  start_at: string;
  end_at: string;
  price_snapshot_vnd: number;
  payment_due_at: string | null;
  participant_role: "OWNER" | "CAREGIVER";
  listing_title: string | null;
  pet_name: string | null;
};

export type Message = {
  id: string;
  sender_id: string;
  body: string | null;
  created_at: string;
  attachments: { id: string; url: string; mime_type: string }[];
};

export type Page<T> = {
  data: T[];
  meta?: { current_page: number; last_page: number; total: number };
  links?: { next: string | null };
};

export type CareUpdate = {
  id: string;
  body: string | null;
  created_at: string;
  sender: { name: string };
  image_url: string | null;
  redacted: boolean;
};
