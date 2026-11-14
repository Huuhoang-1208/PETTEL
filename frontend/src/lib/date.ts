"use client";

export const vietnamISO = (value: string) => new Date(`${value}:00+07:00`).toISOString();

export const localDate = (value: Date) =>
  new Date(value.getTime() + 7 * 3600000).toISOString().slice(0, 16);
