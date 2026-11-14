"use client";

import { type Listing } from "@/lib/api/types";

export type Profile = {
  display_name: string;
  bio: string | null;
  type: string;
  area_code: string;
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
