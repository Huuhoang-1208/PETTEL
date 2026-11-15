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
  breed?: string | null;
  sex: string | null;
  size: string | null;
  birth_date: string | null;
  age_note: string | null;
  diet_notes: string | null;
  vaccination_note: string | null;
  temperament: string | null;
  special_needs: string | null;
  weight_kg?: number | string | null;
  microchip_number?: string | null;
  is_neutered?: boolean;
  health_flags?: { core_vaccines?: boolean; rabies_vaccine?: boolean; deworming?: boolean } | null;
  photo_media_id?: string | null;
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
  caregiver?: {
    display_name: string;
    type: string;
    area_code: string;
    name: string | null;
    demo: {
      home_type: string | null;
      photo_file: string | null;
      status: string | null;
      amenities: string[];
      trust_level: string | null;
      confidence_score: number | null;
      verified_details: Record<string, boolean | number>;
      rating_average: number | null;
      review_count: number | null;
      introduction?: string | null;
      experience_years?: number | null;
      care_principles?: { title: string; description: string }[];
      space_amenities?: { title: string; description: string }[];
      is_demo: boolean;
    } | null;
  } | null;
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
  context?: { pet_name: string | null; pet_breed: string | null; pet_weight_kg: string | number | null; service_type: string; start_at: string | null; end_at: string | null } | null;
};

export type Inquiry = { id: string; listing_id: string; listing_title: string; sitter_name: string };

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
