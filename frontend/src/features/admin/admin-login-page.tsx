"use client";

import { PettelIcon } from "@/components/ui/pettel-icon";
import { api } from "@/lib/api/client";
import { write } from "@/lib/api/request";
import { type User } from "@/lib/api/types";
import { useSession } from "@/providers/session-provider";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState, type FormEvent } from "react";
import styles from "./admin-login-page.module.css";

export function AdminLoginPage() {
  const router = useRouter();
  const { user, loading, refresh } = useSession();
  const [busy, setBusy] = useState(false);
  const [error, setError] = useState("");

  useEffect(() => {
    if (user?.roles?.some((role) => role.role === "ADMIN" || role.role === "SUPPORT")) {
      router.replace("/admin");
    }
  }, [router, user]);

  async function submit(event: FormEvent<HTMLFormElement>) {
    event.preventDefault();
    if (busy) return;

    const form = new FormData(event.currentTarget);
    setBusy(true);
    setError("");
    try {
      await write("post", "/login", {
        email: String(form.get("email")),
        password: String(form.get("password")),
      });
      const { data: signedInUser } = await api.get<User>("/api/v1/user");
      const staff = signedInUser.roles?.some(
        (role) => role.role === "ADMIN" || role.role === "SUPPORT",
      );

      if (!staff) {
        await write("post", "/logout");
        refresh();
        setError("Tài khoản này không có quyền truy cập cổng quản trị.");
        return;
      }

      refresh();
      router.replace("/admin");
    } catch {
      setError("Không thể đăng nhập. Hãy kiểm tra email, mật khẩu và kết nối rồi thử lại.");
    } finally {
      setBusy(false);
    }
  }

  const alreadyStaff = user?.roles?.some(
    (role) => role.role === "ADMIN" || role.role === "SUPPORT",
  );

  return (
    <main className={styles.page}>
      <div className={styles.glow} />
      <section className={styles.card} aria-labelledby="admin-login-heading">
        <Link className={styles.brand} href="/" aria-label="PETTEL Trang chủ">
          <span className={styles.brandMark}>
            <PettelIcon name="paw" />
          </span>
          <span>
            <strong>PETTEL</strong>
            <small>ADMIN PORTAL</small>
          </span>
        </Link>
        <div className={styles.icon}>
          <PettelIcon name="shield" />
        </div>
        <p className={styles.eyebrow}>CỔNG DÀNH CHO ĐỘI NGŨ NỘI BỘ</p>
        <h1 id="admin-login-heading">Đăng nhập quản trị</h1>
        <p className={styles.intro}>
          Sử dụng tài khoản ADMIN hoặc SUPPORT để tiếp tục vào khu vực vận hành PETTEL.
        </p>
        {error && (
          <p className={styles.error} role="alert">
            {error}
          </p>
        )}
        {loading && !alreadyStaff ? (
          <p className={styles.status}>Đang kiểm tra phiên đăng nhập…</p>
        ) : null}
        <form className={styles.form} onSubmit={(event) => void submit(event)}>
          <label>
            Email công việc
            <input
              name="email"
              type="email"
              autoComplete="username"
              placeholder="staff@pettel.vn"
              required
              disabled={busy}
            />
          </label>
          <label>
            Mật khẩu
            <input
              name="password"
              type="password"
              autoComplete="current-password"
              placeholder="Nhập mật khẩu"
              required
              disabled={busy}
            />
          </label>
          <button type="submit" disabled={busy || Boolean(alreadyStaff)}>
            {busy ? "Đang xác thực…" : "Đăng nhập vào Admin Portal"}
            <PettelIcon name="arrow" />
          </button>
        </form>
        <div className={styles.accessNote}>
          <PettelIcon name="shield" />
          <span>
            Quyền truy cập được xác định theo role của tài khoản. SUPPORT chỉ xem dữ liệu; thao tác
            quản trị dành cho ADMIN.
          </span>
        </div>
        <Link className={styles.returnLink} href="/login">
          ← Quay lại đăng nhập PETTEL
        </Link>
      </section>
      <p className={styles.footer}>PETTEL · Cổng quản trị nội bộ</p>
    </main>
  );
}
