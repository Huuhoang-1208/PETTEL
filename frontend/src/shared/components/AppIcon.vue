<script setup>
import { computed } from 'vue';

const props = defineProps({ name: { type: String, default: 'paw' }, size: { type: [Number, String], default: 18 } });
const paths = {
  paw: '<ellipse cx="7" cy="7.2" rx="1.7" ry="2.2" fill="currentColor" stroke="none"/><ellipse cx="12" cy="5.4" rx="1.7" ry="2.2" fill="currentColor" stroke="none"/><ellipse cx="17" cy="7.2" rx="1.7" ry="2.2" fill="currentColor" stroke="none"/><path d="M12 10.5c-2.4 0-6.5 3.2-6.5 6.1 0 1.8 1.4 2.8 3.1 2.8 1.3 0 2.2-.7 3.4-.7s2.1.7 3.4.7c1.7 0 3.1-1 3.1-2.8 0-2.9-4.1-6.1-6.5-6.1Z"/>',
  home: '<path d="m3 10 9-7 9 7v9a2 2 0 0 1-2 2h-5v-6h-4v6H5a2 2 0 0 1-2-2v-9Z"/>',
  user: '<circle cx="12" cy="8" r="3.5"/><path d="M5 21v-1.5a7 7 0 0 1 14 0V21H5Z"/>',
  calendar: '<rect x="3.5" y="5" width="17" height="16" rx="2.5"/><path d="M8 3v4m8-4v4M4 9h16m-12 4h2m3 0h2m-7 4h2"/>',
  shield: '<path d="M12 3 19 6v5c0 4.6-2.8 8.2-7 10-4.2-1.8-7-5.4-7-10V6l7-3Z"/><path d="m9 12 2 2 4-4"/>',
  search: '<circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 4.5 4.5"/>',
  pin: '<path d="M20 10c0 5.2-8 11-8 11S4 15.2 4 10a8 8 0 1 1 16 0Z"/><circle cx="12" cy="10" r="2.5"/>',
  menu: '<path d="M4 6h16M4 12h16M4 18h16"/>',
  close: '<path d="m6 6 12 12M18 6 6 18"/>',
  arrow: '<path d="M4 12h15m-6-6 6 6-6 6"/>',
  down: '<path d="M12 4v15m-6-6 6 6 6-6"/>',
  clock: '<circle cx="12" cy="12" r="9"/><path d="M12 7v5l3.5 2"/>',
};
const content = computed(() => paths[props.name] ?? paths.paw);
</script>

<template>
  <svg
    class="icon"
    :class="`icon-${name}`"
    :width="size"
    :height="size"
    viewBox="0 0 24 24"
    fill="none"
    stroke="currentColor"
    stroke-width="1.8"
    stroke-linecap="round"
    stroke-linejoin="round"
    aria-hidden="true"
    focusable="false"
    v-html="content"
  />
</template>
