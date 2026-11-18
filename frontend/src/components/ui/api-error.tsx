"use client";

import { errorText } from "@/lib/api/errors";

export function ApiError({ error }: { error: unknown }) {
  return error ? (
    <p className="app-error" role="alert">
      {errorText(error)}
    </p>
  ) : null;
}
