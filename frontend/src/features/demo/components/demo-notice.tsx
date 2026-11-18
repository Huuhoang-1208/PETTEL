import Link from "next/link";
export function DemoNotice() {
  return (
    <aside className="app-demo-notice">
      Bản mô phỏng bằng video có sẵn. Chưa có truyền hình trực tiếp giữa người dùng.{" "}
      <Link href="/owner-profile#history">Về đơn chăm sóc</Link>
    </aside>
  );
}
