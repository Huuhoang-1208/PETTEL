"use client";

import Link from "next/link";
import { useEffect } from "react";
import styles from "./error-pages.module.css";

type RouteError = Error & { digest?: string };

export default function ErrorPage({
  error,
  retry,
}: {
  error: unknown;
  retry: () => void;
}) {
  useEffect(() => {
    // Ghi mã nhận diện để đối chiếu log, không gửi nội dung exception ra giao diện.
    const digest =
      error instanceof Error && "digest" in error
        ? String((error as RouteError).digest ?? "")
        : "";

    console.error(
      "[PETTEL] Route rendering failed",
      digest ? { digest } : {},
    );
  }, [error]);

  return (
    <main className={styles.page} role="alert">
      <section className={styles.card} aria-labelledby="error-title">
        <p className={styles.eyebrow}>PETTEL</p>

        <h1 className={styles.title} id="error-title">
          Trang này đang gặp sự cố
        </h1>

        <p className={styles.description}>
          Nội dung chưa thể tải. Bạn có thể thử lại hoặc quay về trang chủ.
        </p>

        <div className={styles.actions}>
          <button className={styles.primaryButton} type="button" onClick={retry}>
            Thử lại
          </button>

          <Link className={styles.secondaryButton} href="/">
            Về trang chủ
          </Link>
        </div>
      </section>
    </main>
  );
}