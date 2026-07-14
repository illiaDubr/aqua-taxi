<script setup>
import { ref, onMounted } from 'vue'
import axios from 'axios'

const orders = ref([])
const loading = ref(true)
const error = ref(false)

const fetchMyOrders = async () => {
    try {
        const token = localStorage.getItem('user_token')
        const res = await axios.get('/api/orders/my-active', {
            headers: { Authorization: `Bearer ${token}` }
        })
        orders.value = res.data
    } catch (err) {
        console.error('❌ Не вдалося завантажити замовлення', err)
        error.value = true
    } finally {
        loading.value = false
    }
}

onMounted(() => {
    fetchMyOrders()
})
</script>

<template>
    <div class="active-orders">
        <h2 class="active-orders__title">Активні замовлення</h2>

        <div v-if="loading" class="active-orders__loading">
            Завантаження...
        </div>

        <div v-else-if="error" class="active-orders__error">
            Не вдалося отримати замовлення. Спробуйте пізніше.
        </div>

        <div v-else-if="orders.length === 0" class="active-orders__empty">
            У вас немає активних замовлень
        </div>

        <div v-else class="active-orders__list">
            <div
                class="order-card"
                v-for="order in orders"
                :key="order.id"
            >
                <p><strong>Адреса:</strong> {{ order.address }}</p>
                <p><strong>Кількість:</strong> {{ order.quantity }} бут.</p>
                <p><strong>Оплата:</strong> {{ order.payment_method === 'cash' ? 'Готівка' : 'Картка' }}</p>
                <p><strong>Час:</strong>
                    {{ order.delivery_time_type === 'now'
                        ? 'Якнайшвидше'
                        : new Date(order.custom_time).toLocaleString('uk-UA')
                    }}
                </p>
                <p><strong>Статус:</strong> {{ formatStatus(order.status) }}</p>
            </div>
        </div>
    </div>
</template>

<script>
function formatStatus(status) {
    switch (status) {
        case 'new': return 'Нове';
        case 'in_progress': return 'Виконується';
        case 'done': return 'Завершено';
        default: return status;
    }
}
</script>

<style scoped>
.active-orders {
    max-width: var(--maxw);
    margin: 0 auto;
    padding: 24px 16px;
    min-height: 100vh;
    background: var(--bg);
}

.active-orders__title {
    font-size: 24px;
    font-weight: 800;
    color: var(--ink);
    margin-bottom: 20px;
}

.active-orders__loading,
.active-orders__error,
.active-orders__empty {
    font-size: 15px;
    color: var(--muted);
    padding: 40px 12px;
    text-align: center;
}

.active-orders__list {
    display: flex;
    flex-direction: column;
    gap: 14px;
}

.order-card {
    background: var(--surface);
    padding: 18px;
    border-radius: var(--r-lg);
    box-shadow: var(--sh-sm);
    border: 1px solid var(--line-2);
}

.order-card p {
    margin: 7px 0;
    font-size: 14.5px;
    color: var(--ink-2);
}
.order-card strong { color: var(--ink); font-weight: 700; }
</style>
