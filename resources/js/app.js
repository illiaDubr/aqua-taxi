// resources/js/app.js
import './bootstrap.js';
import { createApp } from 'vue';
import App from './components/App.vue';
import router from './router';
import { createPinia } from 'pinia';
import axios from 'axios';

const pinia = createPinia();
const app = createApp(App);

app.use(pinia);
app.use(router);

// 👉 Всегда берём самый "свежий" токен из localStorage
const pickToken = () => {
    // если явно указали активный токен — берём его
    const active = localStorage.getItem('active_token');
    if (active) {
        const t = localStorage.getItem(active);
        if (t) return t;
    }

    // роут-зависимый выбор: на фабричных страницах — фабричный токен
    const path = window.location.pathname || '';
    if (path.startsWith('/factory')) {
        return localStorage.getItem('factory_token')
            || localStorage.getItem('token'); // fallback
    }
    if (path.startsWith('/ordersDrive') || path.startsWith('/auth-driver')) {
        return localStorage.getItem('driver_token');
    }

    // общий fallback
    return localStorage.getItem('admin_token')
        || localStorage.getItem('factory_token')
        || localStorage.getItem('driver_token')
        || localStorage.getItem('token');
};

axios.interceptors.request.use((config) => {
    const t = pickToken();
    if (t) {
        config.headers = config.headers || {};
        config.headers.Authorization = `Bearer ${t}`;
    } else if (config?.headers?.Authorization) {
        delete config.headers.Authorization;
    }
    return config;
});
app.mount('#app');
