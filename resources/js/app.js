import './bootstrap';
import { createApp } from 'vue';
import App from './components/App.vue';
import router from './router';
import { createPinia } from 'pinia';
import axios from 'axios';

// Импортируем компоненты только ради того, чтобы Vite включил их стили в app.css
import './views/AuthUser.vue';
import './views/AuthDriver.vue';
import './views/WelcomeView.vue';
import './views/AuthAdmin.vue';
import './views/OrderView.vue';
import './views/CertificatesPage.vue';
import './views/CertificateReview.vue';

// Инициализация Vue-приложения
const app = createApp(App);

app.use(router);
app.use(createPinia());

// Настройка axios
const token = localStorage.getItem('token');
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}

app.mount('#app');
