import { createRouter, createWebHistory } from 'vue-router';
import HomeView from '../views/HomeView.vue';
import AuthUser from '../views/AuthUser.vue';
import OrderForm from '../views/OrderForm.vue';
import OrderTabs from '../views/OrderTabs.vue';

const routes = [
    {
        path: '/',
        name: 'home',
        component: () => import('../views/WelcomeView.vue')
    },
    {
        path: '/auth-user',
        name: 'authUser',
        component: () => import('../views/AuthUser.vue'),
    },
    {
        path: '/auth-driver',
        name: 'authdriver',
        component: () => import('../views/AuthDriver.vue'),
    },
    {
        path: '/auth-factory',
        name: 'authFactory',
        component: () => import('../views/AuthFactory.vue'),
    },
    {
        path: '/auth-admin',
        name: 'authAdmin',
        component: () => import('../views/AuthAdmin.vue'),
    },
    {
        path: '/orders',
        name: 'orders',
        component: () => import('../views/OrderView.vue'),
    },
    {
        path: '/order-form/:productId',
        name: 'orderForm',
        component: OrderForm,
    },
    {
        path: '/admin',
        name: 'adminPanel',
        component: () => import('../views/CertificatesPage.vue')
    },
    {
        path: '/certificate/:id',
        name: 'certificateReview',
        component: () => import('../views/CertificateReview.vue')
    },
    {
        path: '/ordersDrive',
        name: 'OrdersDrive',
        component: OrderTabs,
    },
    {
        path: '/map',
        name: 'MapView',
        component: () => import('../views/MapView.vue')
    }

];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

export default router;
