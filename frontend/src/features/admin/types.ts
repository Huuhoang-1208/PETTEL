"use client";

import { type Listing } from "@/lib/api/types";

export type Change = {
  id: string;
  listing_id: string;
  proposed_data_json: { data: Listing };
  status: string;
};
