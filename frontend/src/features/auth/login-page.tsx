"use client";

import { SiteHeader } from "@/components/layout/site-header";
import { ApiError } from "@/components/ui/api-error";
import { write } from "@/lib/api/request";
import { useSession } from "@/providers/session-provider";
import { useRouter, useSearchParams } from "next/navigation";
import { useState, type FormEvent } from "react";

export function LoginPage() {
  const router = useRouter();
  const query = useSearchParams();
  const { refresh } = useSession();
  const [mode, setMode] = useState<"login" | "register" | "forgot-password">("login");
  const [error, setError] = useState<unknown>();
  const [busy, setBusy] = useState(false);
  const [sent, setSent] = useState(false);
  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
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
      const next = query.get("next") ?? "/";
      router.replace(
        next.startsWith("/") && !next.startsWith("//") && !next.includes("\\") ? next : "/",
      );
    } catch (failure) {
      setError(failure);
    } finally {
      setBusy(false);
    }
  }
  return (
    <>
      <SiteHeader />
      <main className="app-auth app-card">
        <h1>
          {mode === "register"
            ? "Tạo tài khoản PETTEL"
            : mode === "login"
              ? "Chào mừng trở lại"
              : "Lấy lại mật khẩu"}
        </h1>
        <ApiError error={error} />
        {sent ? (
          <p role="status">
            Yêu cầu đã được xử lý. Kiểm tra email và làm theo liên kết đặt lại mật khẩu.
          </p>
        ) : (
          <form className="app-form" key={mode} onSubmit={submit}>
            {mode === "register" && (
              <label>
                Họ tên
                <input name="name" required maxLength={100} autoComplete="name" />
              </label>
            )}
            <label>
              Email
              <input name="email" type="email" required autoComplete="email" />
            </label>
            {mode !== "forgot-password" && (
              <label>
                Mật khẩu
                <input
                  name="password"
                  type="password"
                  required
                  minLength={mode === "register" ? 8 : undefined}
                  autoComplete={mode === "login" ? "current-password" : "new-password"}
                />
              </label>
            )}
            {mode === "register" && (
              <label>
                Nhập lại mật khẩu
                <input
                  name="password_confirmation"
                  type="password"
                  required
                  minLength={8}
                  autoComplete="new-password"
                />
              </label>
            )}
            <button className="app-button" disabled={busy}>
              {busy
                ? "Đang xử lý..."
                : mode === "register"
                  ? "Đăng ký"
                  : mode === "login"
                    ? "Đăng nhập"
                    : "Gửi liên kết đặt lại"}
            </button>
          </form>
        )}
        <div className="app-actions">
          {[
            ["login", "Đăng nhập"],
            ["register", "Tạo tài khoản"],
            ["forgot-password", "Quên mật khẩu?"],
          ].map(([value, label]) => (
            <button
              key={value}
              onClick={() => {
                setMode(value as typeof mode);
                setError(undefined);
                setSent(false);
              }}
            >
              {label}
            </button>
          ))}
        </div>
      </main>
    </>
  );
}
