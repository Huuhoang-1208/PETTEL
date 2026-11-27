import Link from "next/link";
import styles from "./error-pages.module.css";

export default function NotFound() {
  return (
    <main className={styles.page}>
      <section className={styles.card} aria-labelledby="not-found-title">
        <p className={styles.eyebrow}>LỖI 404</p>

        <h1 className={styles.title} id="not-found-title">
          Không tìm thấy trang
        </h1>

        <p className={styles.description}>
          Đường dẫn có thể đã thay đổi hoặc nội dung không còn tồn tại.
        </p>

        <div className={styles.actions}>
          <Link className={styles.primaryButton} href="/">
            Về trang chủ
          </Link>
        </div>
      </section>
    </main>
  );
}
