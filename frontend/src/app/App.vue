<script setup>
import { onMounted } from 'vue';
import { RouterView, useRouter } from 'vue-router';
import { ApiError } from '../shared/api/httpClient';
import { useAuthSession } from './composables/useAuthSession';
import AppHeader from '../shared/components/AppHeader.vue';
import AppFooter from '../shared/components/AppFooter.vue';

const router = useRouter();
const auth = useAuthSession();
onMounted(async () => { try { await auth.refresh(); } catch (error) { if (!(error instanceof ApiError) || error.status !== 401) console.error(error); } });
async function logout() { await auth.logout(); await router.push('/'); }
</script>

<template>
  <div class="site-shell">
    <a class="skip-link" href="#main">Bỏ qua điều hướng</a>
    <AppHeader :authenticated="auth.isAuthenticated.value" :user="auth.user.value" @logout="logout" />
    <main id="main" class="page" tabindex="-1"><RouterView /></main>
    <AppFooter />
  </div>
</template>
