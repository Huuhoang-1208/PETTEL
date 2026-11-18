"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { ApiError } from "@/components/ui/api-error";
import { write } from "@/lib/api/request";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { useState } from "react";
export function ResetPasswordPage() {
  const query = useSearchParams();
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [done, setDone] = useState(false);
  return (
    <>
      <SiteHeader />
      <main className="app-auth app-card">
        <h1>Đặt lại mật khẩu</h1>
        <ApiError error={error} />
        {done ? (
          <p>
            Đã đặt lại mật khẩu. <Link href="/login">Đăng nhập</Link>
          </p>
        ) : (
          <form
            className="app-form"
            onSubmit={async (event) => {
              event.preventDefault();
              setBusy(true);
              setError(undefined);
              try {
                await write("post", "/reset-password", {
                  ...Object.fromEntries(new FormData(event.currentTarget)),
                  token: query.get("token"),
                });
                setDone(true);
              } catch (failure) {
                setError(failure);
              } finally {
                setBusy(false);
              }
            }}
          >
            <label>
              Email
              <input type="email" name="email" required defaultValue={query.get("email") ?? ""} />
            </label>
            <label>
              Mật khẩu mới
              <input
                type="password"
                name="password"
                required
                minLength={8}
                autoComplete="new-password"
              />
            </label>
            <label>
              Nhập lại mật khẩu
              <input
                type="password"
                name="password_confirmation"
                required
                minLength={8}
                autoComplete="new-password"
              />
            </label>
            <button className="app-button" disabled={busy || !query.get("token")}>
              Đặt lại mật khẩu
            </button>
          </form>
        )}
      </main>
    </>
  );
}
