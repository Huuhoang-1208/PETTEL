"use client";

import { type Listing } from "@/lib/api/types";

export type Profile = {
  display_name: string;
  bio: string | null;
  type: string;
  area_code: string;
  application_data_json?: SitterApplicationData;
  contact_verification?: {
    email_verified: boolean;
    email_verification_available: boolean;
    phone_verified: boolean;
    phone_verification_available: boolean;
  };
};

export type SitterApplicationData = {
  private_address?: string | null;
  experience_species?: string[];
  care_skills?: string[];
  capacity?: string;
  housing_type?: string;
  space_type?: string;
  amenities?: string[];
  space_media_ids?: number[];
  avatar_media_id?: number | null;
  contact_phone?: string | null;
  contact_email?: string | null;
  safety_plan?: string | null;
  kyc_front_media_id?: number | null;
  kyc_back_media_id?: number | null;
  certificate_media_id?: number | null;
  agreements?: { partner_terms?: boolean; care_commitment?: boolean };
  experience_years?: number | null;
  care_principles?: { title: string; description: string }[];
  pet_requirements?: string[];
};

export type ManagedListing = Omit<Listing, "media"> & {
  version: number;
  suspension_source: string | null;
  pending_change_request_id: string | null;
  latest_decision: { reason: string | null } | null;
  media: {
    media_object_id: string;
    image_url: string;
    thumbnail_url: string;
  }[];
};

export type Slot = { id: string; start_at: string; end_at: string };
