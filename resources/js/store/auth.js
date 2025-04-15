// resources/js/store/auth.js
import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        token: null,
        user: null,
    }),
    actions: {
        login(token, user) {
            this.token = token;
            this.user = user;
            localStorage.setItem('token', token);
            axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
        },
        logout() {
            this.token = null;
            this.user = null;
            localStorage.removeItem('token');
            delete axios.defaults.headers.common['Authorization'];
        }
    },
});
