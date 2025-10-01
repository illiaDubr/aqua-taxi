// resources/js/router/index.js
import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import AuthUser from '../views/AuthUser.vue';
import OrderForm from '../views/OrderForm.vue';
import OrderTabs from '../views/OrderTabs.vue';
import MapView from '../views/MapView.vue';

const routes = [
    // Публичные
    { path: '/', name: 'home', component: () => import('../views/WelcomeView.vue') },

    // Логины (публичные)
    { path: '/auth-user',    name: 'authUser',    component: () => import('../views/AuthUser.vue') },
    { path: '/auth-driver',  name: 'authDriver',  component: () => import('../views/AuthDriver.vue') },
    { path: '/auth-factory', name: 'authFactory', component: () => import('../views/AuthFactory.vue') },
    { path: '/auth-admin',   name: 'authAdmin',   component: () => import('../views/AuthAdmin.vue') },

    // Клиент (если у тебя есть пользовательские заказы)
    { path: '/orders',                 name: 'orders',     component: () => import('../views/OrderView.vue'),     meta: { role: 'user' } },
    { path: '/order-form/:productId',  name: 'orderForm',  component: () => import('../views/OrderForm.vue'),    meta: { role: 'user' } },

    // Админка
    { path: '/admin',           name: 'adminPanel',       component: () => import('../views/CertificatesPage.vue'), meta: { role: 'admin' } },
    { path: '/certificate/:id', name: 'certificateReview', component: () => import('../views/CertificateReview.vue'), meta: { role: 'admin' } },

    // Кабинет производителя
    { path: '/factory-page', name: 'factoryPage', component: () => import('../views/FactoryView.vue'), meta: { role: 'factory' } },

    // Водитель
    { path: '/ordersDrive', name: 'ordersDrive', component: () => import('../views/OrderTabs.vue'), meta: { role: 'driver' } },
    { path: '/map',         name: 'mapView',     component: () => import('../views/MapView.vue'),   meta: { role: 'driver' } },

    // Фоллбек
    { path: '/:pathMatch(.*)*', redirect: '/' },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
    scrollBehavior() { return { top: 0 }; },
});

export default router;
