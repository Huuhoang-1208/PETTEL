import { createApp } from 'vue';
import { router } from './app/router';
import App from './app/App.vue';
import './shared/styles/tokens.css';
import './shared/styles/global.css';
import './shared/styles/discovery.css';

createApp(App).use(router).mount('#app');
