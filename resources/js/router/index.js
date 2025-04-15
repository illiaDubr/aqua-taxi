import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import AuthView from '../views/AuthView.vue';

const routes = [
    {
        path: '/',
        name: 'home',
        component: HomeView,
    },
    {
        path: '/auth',
        name: 'auth',
        component: AuthView
    },
    {
        path: '/welcome',
        name: 'welcome',
        component: () => import('../views/WelcomeView.vue')
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
