import { defineStore } from 'pinia';
import axios from 'axios';

export const useAdminFactoryStore = defineStore('adminFactory', {
    state: () => ({
        factories: [],
        loading: false,
    }),

    actions: {
        async fetchPendingFactories() {
            this.loading = true;
            try {
                const res = await axios.get('/api/admin/factories/pending');
                this.factories = res.data;
            } catch (e) {
                console.error('Помилка завантаження заявок:', e);
            } finally {
                this.loading = false;
            }
        },

        async approveFactory(id, verifiedUntil) {
            try {
                await axios.post(`/api/admin/factories/${id}/approve`, {
                    verified_until: verifiedUntil,
                });
                this.factories = this.factories.filter(f => f.id !== id);
            } catch (e) {
                console.error('Не вдалося підтвердити фабрику:', e);
            }
        },

        async rejectFactory(id) {
            try {
                await axios.post(`/api/admin/factories/${id}/reject`);
                this.factories = this.factories.filter(f => f.id !== id);
            } catch (e) {
                console.error('Не вдалося відхилити фабрику:', e);
            }
        },
    },
});
