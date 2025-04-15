import './bootstrap';
import { createApp } from 'vue';
import App from './components/App.vue';
import router from './router';
import { createPinia } from 'pinia';

const app = createApp(App);
app.use(router);
app.use(createPinia());
app.mount('#app');

const token = localStorage.getItem('token');
if (token) {
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
}
