"use client";

import { useApi } from "@/hooks/use-api";
import { unauthorized } from "@/lib/api/errors";
import { type User } from "@/lib/api/types";
import { createContext, useContext, type ReactNode } from "react";

export const Session = createContext<{
  user: User | null;
  loading: boolean;
  error: unknown;
  refresh: () => void;
}>({ user: null, loading: true, error: null, refresh: () => {} });

export function SessionProvider({ children }: { children: ReactNode }) {
  const resource = useApi<User>("/api/user");
  return (
    <Session.Provider
      value={{
        user: resource.data ?? null,
        loading: resource.loading,
        error: unauthorized(resource.error) ? null : resource.error,
        refresh: resource.reload,
      }}
    >
      {children}
    </Session.Provider>
  );
}

export const useSession = () => useContext(Session);
