<script setup>
import { computed, ref } from 'vue';
import { RouterLink, useRoute } from 'vue-router';
import AppIcon from './AppIcon.vue';

const props = defineProps({ authenticated: { type: Boolean, default: false }, user: { type: Object, default: null } });
const emit = defineEmits(['logout']);
const route = useRoute();
const mobileOpen = ref(false);
const items = computed(() => {
  const base = [{ label: 'Khám phá', to: '/', icon: 'home' }];
  if (!props.authenticated) return [...base, { label: 'Đăng nhập', to: '/login', icon: 'user' }];
  return [...base,
    { label: 'Thú cưng', to: '/pets', icon: 'paw' },
    { label: 'Báo cáo', to: '/reports', icon: 'shield' },
    { label: 'Tài khoản', to: '/account', icon: 'user' },
  ];
});

function isActive(path) {
  return path === '/' ? route.path === '/' : route.path === path || route.path.startsWith(`${path}/`);
}

function closeMobileMenu() {
  mobileOpen.value = false;
}
</script>

<template>
  <header class="site-header">
    <div class="header-inner">
      <RouterLink to="/" class="brand" aria-label="PETTEL, về trang khám phá" @click="closeMobileMenu">
        <span class="brand-mark" aria-hidden="true">
          <svg viewBox="0 0 24 24" fill="none">
            <path d="M12 20c-3.2 0-7-1.5-7-4.6 0-2.2 1.7-3.4 3.5-4.2L12 9.5l3.5 1.7c1.8.8 3.5 2 3.5 4.2 0 3.1-3.8 4.6-7 4.6Z" fill="currentColor" />
            <ellipse cx="6.7" cy="7.2" rx="2.1" ry="2.8" fill="currentColor" />
            <ellipse cx="11.1" cy="4.9" rx="2.1" ry="2.8" fill="currentColor" />
            <ellipse cx="16.1" cy="5.4" rx="2.1" ry="2.8" fill="currentColor" />
            <ellipse cx="19.6" cy="8.5" rx="2" ry="2.6" fill="currentColor" />
          </svg>
        </span>
        <span>PETTEL</span>
      </RouterLink>

      <nav class="main-nav" aria-label="Điều hướng chính">
        <RouterLink v-for="item in items" :key="item.to" :to="item.to" class="nav-link" :class="{ active: isActive(item.to) }" :aria-current="isActive(item.to) ? 'page' : undefined">
          <span class="nav-icon"><AppIcon :name="item.icon" :size="17" /></span>
          <span>{{ item.label }}</span>
        </RouterLink>
      </nav>

      <div class="header-actions">
        <RouterLink v-if="authenticated && user" to="/account" class="welcome">{{ user.name }}</RouterLink>
        <button v-if="authenticated" type="button" class="link-button header-logout" @click="emit('logout')">Đăng xuất</button>
        <button class="menu-toggle" type="button" :aria-expanded="mobileOpen" aria-controls="mobile-nav" :aria-label="mobileOpen ? 'Đóng điều hướng' : 'Mở điều hướng'" @click="mobileOpen = !mobileOpen">
          <AppIcon :name="mobileOpen ? 'close' : 'menu'" :size="21" />
        </button>
      </div>
    </div>

    <nav id="mobile-nav" class="mobile-nav" :class="{ open: mobileOpen }" aria-label="Điều hướng di động" :inert="!mobileOpen">
      <RouterLink v-for="item in items" :key="item.to" :to="item.to" class="nav-link" :class="{ active: isActive(item.to) }" :aria-current="isActive(item.to) ? 'page' : undefined" @click="closeMobileMenu">
        <span class="nav-icon"><AppIcon :name="item.icon" :size="17" /></span>
        <span>{{ item.label }}</span>
      </RouterLink>
    </nav>
  </header>
</template>
