<script setup>
import heroImage from '../../../../shared/assets/heroImage.png';
</script>

<template>
  <section class="hero" aria-labelledby="discover-title">
    <div class="hero-copy">
      <p class="eyebrow">Chăm sóc gần gũi · Đà Nẵng</p>
      <h1 id="discover-title">Thêm an tâm cho<br /><em>mỗi ngày xa nhà.</em></h1>
      <p>Chọn dịch vụ phù hợp cho người bạn bốn chân — với lịch chăm sóc rõ ràng và thông tin được chia sẻ riêng trong từng lượt đặt.</p>
      <a href="#results" class="button secondary hero-cta">Khám phá dịch vụ <span aria-hidden="true">↓</span></a>
    </div>
    <div class="hero-art">
      <img :src="heroImage" alt="Bốn chú chó thuộc các giống khác nhau" fetchpriority="high" />
    </div>
  </section>
</template>
