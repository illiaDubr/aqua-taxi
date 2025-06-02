<template>
    <div class="orders">
        <div class="orders__header">
            <img :src="city" alt="background" class="orders__bg" />
            <div class="orders__top-bar">
                <div class="orders__burger">☰</div>
                <div class="orders__balance">
                    <span>{{ balance && typeof balance.value === 'number' ? balance.value.toFixed(2) : '0.00' }} грн</span>

                    <button @click="showTopUpModal = true">＋</button>
                </div>
            </div>
        </div>

        <div class="orders__tabs">
            <span :class="{ active: activeTab === 'active' }" @click="activeTab = 'active'">Активні замовлення</span>
            <span :class="{ active: activeTab === 'new' }" @click="activeTab = 'new'">Нові замовлення</span>
        </div>

        <!-- Вкладка: Активні -->
        <div class="orders__content" v-if="activeTab === 'active'">
            <div v-if="activeOrders.length">
                <div v-for="order in activeOrders" :key="order.id" class="orders__card">
                    <h3>🚰 {{ order.quantity }} × Срібна вода</h3>
                    <p>
                        📍
                        <a
                            :href="getMapLink(order.address)"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="text-decoration: underline; color: #007bff;"
                        >
                            {{ order.address }}
                        </a>
                    </p>
                    <p>💳 {{ order.payment_method === 'cash' ? 'Готівка' : 'Картка' }} | {{ order.total_price }} грн</p>
                    <p>⏱ {{ order.delivery_time_type === 'custom' ? formatDate(order.custom_time) : 'Найближчий час' }}</p>
                    <p v-if="order.user">👤 Клієнт: {{ order.user.name }} {{ order.user.surname }}</p>
                    <p v-if="order.user">📞 {{ order.user.phone }}</p>
                </div>
            </div>
            <div v-else>
                <p>У вас поки немає активних замовлень</p>
                <div class="orders__wrap">
                    <h1 class="orders__title">
                        Щоб розмістити послугу,<br />
                        поповніть баланс на 100грн
                    </h1>
                    <a href="#" class="orders__notify">Ввімкнути повідомлення</a>
                    <button class="orders__pay" v-if="balance <= 20">Поповнити баланс</button>
                    <button class="orders__map" @click="goToMap">Набрати воду</button>
                </div>
            </div>
        </div>


        <!-- Вкладка: Нові -->
        <div class="orders__content" v-else>
            <div v-if="newOrders.length">
                <div v-for="order in newOrders" :key="order.id" class="orders__card">
                    <h3>🚰 {{ order.quantity }} × Срібна вода</h3>
                    <p>
                        📍
                        <a
                            :href="getMapLink(order.address)"
                            target="_blank"
                            rel="noopener noreferrer"
                            style="text-decoration: underline; color: #007bff;"
                        >
                            {{ order.address }}
                        </a>
                    </p>
                    <p>💳 {{ order.payment_method === 'cash' ? 'Готівка' : 'Картка' }} | {{ order.total_price }} грн</p>
                    <p>⏱ {{ order.delivery_time_type === 'custom' ? formatDate(order.custom_time) : 'Найближчий час' }}</p>
                    <button class="orders__map" @click="acceptOrder(order.id)">Прийняти замовлення</button>
                </div>
            </div>
            <p v-else>Немає нових замовлень</p>
        </div>

        <!-- Модалка -->
        <div v-if="showTopUpModal" class="modal">
            <div class="modal__overlay" @click="showTopUpModal = false"></div>
            <div class="modal__content">
                <h3>Поповнення балансу</h3>
                <input type="number" v-model="topUpAmount" placeholder="Сума в грн" />
                <button @click="payWithFondy">Поповнити</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';
import { useRouter } from 'vue-router';
import city from '@/assets/city.png';

const router = useRouter();
const activeTab = ref('active');
const showTopUpModal = ref(false);
const topUpAmount = ref('');
const balance = ref(null);

const newOrders = ref([]);

const driverToken = localStorage.getItem('driver_token');

const goToMap = () => router.push('/map');

const getMapLink = (address) => {
    const query = encodeURIComponent(address);
    return `https://www.google.com/maps/search/?api=1&query=${query}`;
};

const fetchBalance = async () => {
    try {
        const res = await axios.get('/api/driver/balance', {
            headers: { Authorization: `Bearer ${driverToken}` }
        });
        balance.value = res.data.balance ?? 0;
    } catch (e) {
        console.error(e);
    }
};

const fetchNewOrders = async () => {
    if (!driverToken) return; // 🛑 нет токена — не шлем запрос
    try {
        const res = await axios.get('/api/driver/orders/new', {
            headers: { Authorization: `Bearer ${driverToken}` }
        });
        newOrders.value = res.data;
    } catch (e) {
        console.error('❌ Не вдалося завантажити нові замовлення', e);
    }
};
const activeOrders = ref([]);

const fetchActiveOrders = async () => {
    try {
        const res = await axios.get('/api/driver/orders/active', {
            headers: { Authorization: `Bearer ${driverToken}` }
        });
        activeOrders.value = res.data;
    } catch (e) {
        console.error('❌ Не вдалося отримати активні замовлення', e);
    }
};
const acceptOrder = async (orderId) => {
    try {
        await axios.post(`/api/driver/orders/${orderId}/accept`, {}, {
            headers: { Authorization: `Bearer ${driverToken}` }
        });
        await fetchNewOrders();
    } catch (e) {
        alert('❌ Помилка при прийнятті замовлення');
        console.error(e);
    }
};

const payWithFondy = async () => {
    try {
        const res = await axios.post('/api/driver/pay', {
            amount: parseFloat(topUpAmount.value)
        }, {
            headers: { Authorization: `Bearer ${driverToken}` }
        });

        const { url, params } = res.data;

        const form = document.createElement('form');
        form.method = 'POST';
        form.action = url;

        Object.entries(params).forEach(([key, value]) => {
            const input = document.createElement('input');
            input.type = 'hidden';
            input.name = key;
            input.value = value;
            form.appendChild(input);
        });

        document.body.appendChild(form);
        form.submit();
    } catch (error) {
        alert('Помилка при генерації платежу');
        console.error(error);
    }
};

const formatDate = (str) => {
    if (!str) return '';
    const d = new Date(str);
    return d.toLocaleString('uk-UA', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    });
};

watch(activeTab, (tab) => {
    if (tab === 'new') fetchNewOrders();
    if (tab === 'active') fetchActiveOrders(); // 👈
});

onMounted(() => {
    fetchBalance();
    if (activeTab.value === 'new') fetchNewOrders();

    window.Echo.channel('orders')
        .listen('.NewOrderCreated', (e) => {
            console.log('🆕 Новый заказ через сокет:', e.order);
            newOrders.value.unshift(e.order); // или fetchNewOrders()
        })
        .listen('.OrderStatusUpdated', (e) => {
            console.log('📦 Обновление заказа:', e.order);
            if (activeTab.value === 'new') fetchNewOrders();
        });
});
</script>

<style scoped>
.orders__wrap {
    display: flex;
    flex-direction: column;
    margin-top: 300px;
}

.orders__card {
    background: white;
    border-radius: 12px;
    padding: 16px;
    box-shadow: 0 4px 12px rgba(0,0,0,0.05);
    margin-bottom: 16px;
    text-align: left;
}

.modal {
    position: fixed;
    top: 0; left: 0;
    width: 100%; height: 100%;
    display: flex;
    align-items: center;
    justify-content: center;
    z-index: 999;
}

.modal__overlay {
    position: absolute;
    width: 100%; height: 100%;
    background: rgba(0, 0, 0, 0.5);
}

.modal__content {
    position: relative;
    background: white;
    border-radius: 16px;
    padding: 24px;
    width: 300px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.modal__content input {
    padding: 12px;
    border-radius: 8px;
    border: 1px solid #ccc;
}

.modal__content button {
    background: #3498db;
    color: white;
    border: none;
    border-radius: 8px;
    padding: 12px;
    cursor: pointer;
}

.orders {
    font-family: 'Montserrat', sans-serif;
    background: #f8f9fa;
    min-height: 100vh;
    overflow-x: hidden;
}

.orders__map {
    background: #00aaff;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 14px 24px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    margin-top: 12px;
}

.orders__header {
    position: relative;
    background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 100%);
    padding: 16px;
}

.orders__bg {
    position: absolute;
    top: 60px;
    left: 0;
    width: 100%;
    height: 120px;
    object-fit: cover;
    pointer-events: none;
    z-index: 0;
}

.orders__top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
    margin-bottom: 32px;
}

.orders__burger {
    font-size: 24px;
    cursor: pointer;
}

.orders__balance {
    display: flex;
    align-items: center;
    background: #fff;
    border-radius: 12px;
    padding: 6px 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    gap: 8px;
    font-weight: 500;
}

.orders__balance button {
    background: #00aaff;
    border: none;
    color: white;
    border-radius: 7px;
    padding: 10px 3px;
    font-size: 16px;
    line-height: 0;
    cursor: pointer;
    text-align: center;
}

.orders__tabs {
    display: flex;
    justify-content: space-around;
    padding: 12px 0;
    background: white;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    margin: 24px 0px;
    z-index: 1;
    position: relative;
}

.orders__tabs span {
    font-size: 16px;
    font-weight: 600;
    color: #ccc;
    padding: 8px 0;
    cursor: pointer;
}

.orders__tabs .active {
    color: #3498db;
    border-bottom: 2px solid #3498db;
}

.orders__content {
    padding: 24px;
    min-height: 60vh;
    text-align: center;
}
.orders__alert {
    border: 1px solid #e74c3c;
    background: #fff0f0;
    color: #c0392b;
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
}
.orders__title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 16px;
}
.orders__notify {
    text-decoration: none;
    color: #0095FF;
    display: inline-block;
    margin-bottom: -30px;
    font-size: 14px;
}
.orders__pay {
    background: #3498db;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 14px 24px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
}
</style>
