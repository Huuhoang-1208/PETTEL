"use client";

export type Quote = {
  price_total_vnd: number;
  quantity: number;
  price_unit: string;
};

export type Draft = {
  payload: {
    pet_id: string;
    listing_id: string;
    start_at: string;
    end_at: string;
    instructions: string;
  };
  quote: Quote;
  key: string;
};

export type Review = {
  id: string;
  rating: number;
  body: string | null;
  created_at: string;
  is_demo: boolean;
};
