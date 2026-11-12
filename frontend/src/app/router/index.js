import { createRouter, createWebHistory } from 'vue-router';
import SearchPage from '../../features/owner/discovery/pages/SearchPage.vue';
import ListingDetailPage from '../../features/owner/discovery/pages/ListingDetailPage.vue';
import PetsPage from '../../features/owner/pets/pages/PetsPage.vue';
import BookingPage from '../../features/owner/bookings/pages/BookingPage.vue';
import LoginPage from '../../features/auth/pages/LoginPage.vue';
import AccountPage from '../../features/owner/account/pages/AccountPage.vue';
import ReportsPage from '../../features/owner/reports/pages/ReportsPage.vue';
import MessageThreadPage from '../../features/owner/messaging/pages/MessageThreadPage.vue';
import SandboxCheckoutPage from '../../features/owner/bookings/pages/SandboxCheckoutPage.vue';
import CompletionConfirmationPage from '../../features/owner/bookings/pages/CompletionConfirmationPage.vue';
import ReviewSubmissionPage from '../../features/owner/reviews/pages/ReviewSubmissionPage.vue';
import { authService } from '../../features/auth/services/authService';

export const router = createRouter({
  history: createWebHistory(import.meta.env.BASE_URL),
  routes: [
    { path: '/', name: 'search', component: SearchPage },
    { path: '/listings/:id', name: 'listing-detail', component: ListingDetailPage, props: true },
    { path: '/pets', name: 'pets', component: PetsPage, meta: { owner: true } },
    { path: '/account', name: 'account', component: AccountPage, meta: { owner: true } },
    { path: '/reports', name: 'reports', component: ReportsPage, meta: { owner: true } },
    { path: '/bookings/:bookingId/messages', name: 'booking-messages', component: MessageThreadPage, props: true, meta: { owner: true } },
    { path: '/book/:listingId', name: 'booking', component: BookingPage, props: true, meta: { owner: true } },
    { path: '/bookings/:bookingId/checkout', name: 'checkout', component: SandboxCheckoutPage, props: true, meta: { owner: true } },
    { path: '/bookings/:bookingId/confirm-completion', name: 'confirm-completion', component: CompletionConfirmationPage, props: true, meta: { owner: true } },
    { path: '/bookings/:bookingId/review', name: 'booking-review', component: ReviewSubmissionPage, props: true, meta: { owner: true } },
    { path: '/login', name: 'login', component: LoginPage },
    { path: '/reset-password', name: 'password-reset-link', component: LoginPage, props: false },
  ],
  scrollBehavior() { return { top: 0 }; },
});

router.beforeEach(async (to) => {
  if (!to.meta.owner) return true;
  try {
    await authService.me();
    return true;
  } catch {
    return { name: 'login', query: { redirect: to.fullPath } };
  }
});
