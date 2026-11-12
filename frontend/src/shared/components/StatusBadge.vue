<script setup>
defineProps({ status: { type: String, required: true } });
const names = { REQUESTED: 'Chờ người chăm', AWAITING_PAYMENT: 'Chờ thanh toán mô phỏng', CONFIRMED: 'Đã xác nhận', IN_PROGRESS: 'Đang chăm sóc', AWAITING_CONFIRMATION: 'Chờ bạn xác nhận', COMPLETED: 'Đã hoàn tất', CANCELLED: 'Đã hủy', OPEN: 'Đang mở', IN_REVIEW: 'Đang xem xét', RESOLVED: 'Đã xử lý', DISMISSED: 'Đã đóng', ACTIVE: 'Đang hoạt động', ARCHIVED: 'Đã lưu trữ' };
</script>
<template><span class="status-pill" :class="`status-${String(status).toLowerCase()}`">{{ names[status] ?? status }}</span></template>
