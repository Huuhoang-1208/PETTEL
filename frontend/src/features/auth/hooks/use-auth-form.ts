"use client";
import { write } from "@/lib/api/request";
import { api } from "@/lib/api/client";
import { type User } from "@/lib/api/types";
import { useSession } from "@/providers/session-provider";
import { usePathname, useRouter, useSearchParams } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
export type AuthMode = "login" | "register" | "forgot-password";
export type AuthRole = "OWNER" | "CAREGIVER";
export function useAuthForm() {
  const router = useRouter();
  const pathname = usePathname();
  const query = useSearchParams();
  const { refresh } = useSession();
  const [recovering, setRecovering] = useState(false);
  const mode: AuthMode =
    pathname === "/register" ? "register" : recovering ? "forgot-password" : "login";
  const [role, setRole] = useState<AuthRole>("OWNER");
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState(false);

  useEffect(() => {
    const restoreMode = () => {
      setRecovering(false);
      setError(undefined);
      setSent(false);
    };
    window.addEventListener("popstate", restoreMode);
    return () => window.removeEventListener("popstate", restoreMode);
  }, []);

  function changeMode(value: AuthMode) {
    if (busy) return;
    setRecovering(value === "forgot-password");
    setError(undefined);
    setSent(false);
    if (value !== "forgot-password") {
      const path = value === "register" ? "/register" : "/login";
      if (window.location.pathname !== path) {
        // Next.js syncs native history with usePathname/useSearchParams;
        // keeping this component mounted preserves the panel slide animation.
        window.history.pushState(
          null,
          "",
          `${path}${window.location.search}${window.location.hash}`,
        );
      }
    }
  }
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy) return;
    const data = Object.fromEntries(new FormData(event.currentTarget));
    setBusy(true);
    setError(undefined);
    try {
      await write("post", `/${mode}`, data);
      if (mode === "forgot-password") {
        setSent(true);
        return;
      }
      refresh();
      const isStaffLogin = mode === "login" && (await api.get<User>("/api/user")).data.roles?.some(
        (assignedRole) => assignedRole.role === "ADMIN" || assignedRole.role === "SUPPORT",
      );
      const next = isStaffLogin ? "/admin" : query.get("next") ?? (role === "CAREGIVER" ? "/caregiver" : "/");
      router.replace(
        next.startsWith("/") && !next.startsWith("//") && !next.includes("\\") ? next : "/",
      );
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  return { mode, role, setRole, error, busy, sent, changeMode, submit };
}

export type AuthFormState = ReturnType<typeof useAuthForm>;
