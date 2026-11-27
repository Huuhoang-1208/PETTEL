import { PettelIcon } from "@/components/ui/pettel-icon";
import type { Listing } from "@/lib/api/types";
import { dateTime } from "@/lib/format";
import { services, statuses } from "@/lib/constants";
import Link from "next/link";
import { bookingDetailHref, careEstimate, dong, type CareContext, type ChatRecord } from "../types";
import styles from "../chat.module.css";

export function CareOverview({
  record,
  context,
  listing,
  compact = false,
}: {
  record?: ChatRecord;
  context: CareContext | null;
  listing?: Listing;
  compact?: boolean;
}) {
  const estimate = careEstimate(record, context, listing);
  const detailHref = record?.status
    ? bookingDetailHref(record)
    : record?.listing_id
      ? `/detail?id=${record.listing_id}`
      : "/services";
  return (
    <section className={compact ? styles.overview : styles.bookingPanel}>
      <header>
        <h3>
          <PettelIcon name="calendar" />
          {compact ? "Thông tin gửi thú cưng" : "Lịch chăm sóc của bé"}
        </h3>
        <span>{record?.status ? (statuses[record.status] ?? record.status) : "Đang trao đổi"}</span>
      </header>
      <dl className={compact ? styles.overviewGrid : styles.bookingRows}>
        <div>
          <dt>Thú cưng</dt>
          <dd>
            <PettelIcon name="paw" /> {context?.pet_name || "Chưa chọn bé"}
          </dd>
          {context?.pet_breed && (
            <small>
              {context.pet_breed}
              {context.pet_weight_kg ? ` · ${Number(context.pet_weight_kg)}kg` : ""}
            </small>
          )}
        </div>
        <div>
          <dt>Dịch vụ</dt>
          <dd>
            {services[context?.service_type || listing?.service_type || ""] ??
              record?.listing_title ??
              "Chăm sóc thú cưng"}
          </dd>
        </div>
        <div>
          <dt>Thời gian gửi</dt>
          <dd>{context?.start_at ? dateTime(context.start_at) : "Chưa chọn ngày"}</dd>
          {context?.end_at && <small>Đón bé: {dateTime(context.end_at)}</small>}
          {estimate.quantity && (
            <small>
              Tổng cộng: {estimate.quantity} {estimate.unit}
            </small>
          )}
        </div>
        <div className={styles.estimate}>
          <dt>{record?.status ? "Giá trị đơn chăm sóc" : "Tạm tính chi phí"}</dt>
          <dd>{estimate.total !== null ? dong(estimate.total) : "Chưa có báo giá"}</dd>
          <small>
            {record?.status
              ? "Theo thông tin đơn đã tạo"
              : "Giá chính thức được kiểm tra khi đặt lịch"}
          </small>
        </div>
      </dl>
      <Link href={detailHref} className={compact ? styles.overviewAction : styles.primaryButton}>
        {record?.status ? "Xem chi tiết đặt lịch" : "Xem dịch vụ & Đặt lịch"}
        <PettelIcon name="arrow" />
      </Link>
      {!compact && (
        <p className={styles.paymentNote}>
          Chưa trừ tiền ngay · Lịch được xác nhận theo trạng thái đơn chăm sóc.
        </p>
      )}
    </section>
  );
}
