import './reverb';
import './bootstrap.js';
import { createApp } from 'vue';
import App from './components/App.vue';
import router from './router';
import { createPinia } from 'pinia';
import axios from 'axios';

// Стили для включения в финальный бандл
import './views/AuthUser.vue';
import './views/AuthDriver.vue';
import './views/WelcomeView.vue';
import './views/AuthAdmin.vue';
import './views/OrderView.vue';
import './views/CertificatesPage.vue';
import './views/CertificateReview.vue';

// Создание и регистрация Pinia
const pinia = createPinia();
const app = createApp(App);

app.use(pinia);
app.use(router);

// 🛡️ Инициализация токена администратора и пользователя
const adminToken = localStorage.getItem('admin_token');
const userToken = localStorage.getItem('token');

// Устанавливаем приоритет токена администратора
if (adminToken) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${adminToken}`;
} else if (userToken) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${userToken}`;
}

app.mount('#app');
