import { defineStore } from 'pinia';
import axios from 'axios';

export const useAdminStore = defineStore('admin', {
    state: () => ({
        token: localStorage.getItem('admin_token') || null,
        admin: null
    }),

    actions: {
        async login(email, password) {
            const res = await axios.post('/api/admin/login', { email, password });
            this.token = res.data.token;
            this.admin = res.data.admin;

            localStorage.setItem('admin_token', this.token);
            axios.defaults.headers.common['Authorization'] = `Bearer ${this.token}`;
        },

        logout() {
            this.token = null;
            this.admin = null;
            localStorage.removeItem('admin_token');
            delete axios.defaults.headers.common['Authorization'];
        }
    }
});
