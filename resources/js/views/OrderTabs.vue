<template>
    <div class="driver-map">
        <!-- Свитчер -->
        <div class="order-switcher">
            <button :class="{ active: currentTab === 'active' }" @click="switchTab('active')">Активні замовлення</button>
            <button :class="{ active: currentTab === 'new' }" @click="switchTab('new')">Нові замовлення</button>
        </div>

        <!-- Верхняя панель -->
        <div class="driver-map__top-panel">
            <div class="driver-map__block" @click="goToMap" style="cursor: pointer;">
                <span>{{ bottles !== null ? bottles : 0 }} бут.</span>
                <button>＋</button>
            </div>
            <div class="driver-map__block">
                <span>{{ typeof balance === 'number' ? balance.toFixed(2) : '0.00' }} грн</span>
                <button @click="showTopUpModal = true">＋</button>
            </div>
        </div>

        <!-- Алерт -->
        <div v-if="newOrderAlert" class="order-alert">
            🚚 Нове замовлення додано на карту
        </div>

        <!-- Карта -->
        <div ref="mapContainer" class="driver-map__container"></div>

        <!-- Модалка пополнения -->
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
import { ref, onMounted, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const mapContainer = ref();
const map = ref(null);
const bottles = ref(0);
const balance = ref(0);
const newOrders = ref([]);
const newOrderAlert = ref(false);
const showTopUpModal = ref(false);
const topUpAmount = ref('');
const router = useRouter();
const renderedOrderIds = ref([]);
const orderMarkers = ref({});
const currentTab = ref('new');

const goToMap = () => router.push('/map');
const switchTab = (tab) => {
    currentTab.value = tab;
    fetchOrders();
};

const fetchDriverData = async () => {
    try {
        const token = localStorage.getItem('driver_token');
        const res = await axios.get('/api/driver/profile', {
            headers: { Authorization: `Bearer ${token}` }
        });
        bottles.value = res.data.bottles;
        balance.value = res.data.balance;
    } catch (e) {
        console.error('❌ Помилка отримання даних водія', e);
    }
};

const payWithFondy = async () => {
    try {
        const token = localStorage.getItem('driver_token');
        const res = await axios.post('/api/driver/pay', { amount: parseFloat(topUpAmount.value) }, {
            headers: { Authorization: `Bearer ${token}` }
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
        alert('❌ Помилка при генерації платежу');
        console.error(error);
    }
};

const fetchOrders = async () => {
    try {
        Object.values(orderMarkers.value).forEach(marker => {
            map.value?.removeLayer(marker);
        });
        orderMarkers.value = {};
        renderedOrderIds.value = [];
        newOrders.value = [];

        const token = localStorage.getItem('driver_token');
        const endpoint = currentTab.value === 'active' ? '/api/driver/orders/active' : '/api/driver/orders/new';
        const res = await axios.get(endpoint, {
            headers: { Authorization: `Bearer ${token}` }
        });

        const orders = res.data;

        orders.forEach(order => {
            if (!order.latitude || !order.longitude) return;
            const lat = parseFloat(order.latitude);
            const lng = parseFloat(order.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            renderedOrderIds.value.push(order.id);
            newOrders.value.push(order);

            const icon = L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowUrl: null
            });

            const marker = L.marker([lat, lng], { icon })
                .addTo(map.value)
                .bindPopup(`
          <div class="order-popup">
            <b>${currentTab.value === 'active' ? '🚚 Активне замовлення' : '🚰 Нове замовлення'}</b><br>
<b>Адреса:</b> ${
                    currentTab.value === 'active'
                        ? `<a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.address)}" target="_blank">${order.address}</a>`
                        : order.address
                }<br>
<b>Кількість:</b> ${order.quantity} бут.<br>
<b>Оплата:</b> ${order.payment_method === 'cash' ? 'Готівка' : 'Картка'}<br>
<b>Сума:</b> ${order.total_price}<br>
${
                    currentTab.value === 'active' && order.user
                        ? `<b>Замовник:</b> ${order.user.name} ${order.user.surname}<br><b>Телефон:</b> ${order.user.phone}<br>`
                        : ''
                }<br>${
                    currentTab.value === 'new'
                        ? `<button onclick="window.acceptOrder(${order.id})" class="accept-button">✅ Прийняти</button>`
                        : ''
                }

          </div>
        `);

            orderMarkers.value[order.id] = marker;

            const pulse = L.circle([lat, lng], {
                radius: 60,
                color: '#3498db',
                fillColor: '#3498db',
                fillOpacity: 0.3
            }).addTo(map.value);

            setTimeout(() => map.value?.removeLayer(pulse), 3000);
            map.value?.setView([lat, lng], 14);
        });
    } catch (error) {
        console.error('❌ Помилка завантаження замовлень', error);
    }
};

onMounted(async () => {
    await fetchDriverData();
    await nextTick();
    map.value = L.map(mapContainer.value, { zoomControl: false }).setView([50.4501, 30.5234], 13);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map.value);
    await fetchOrders();
});

window.acceptOrder = async function(orderId) {
    const confirmAccept = confirm('Підтвердити прийняття замовлення?');
    if (!confirmAccept) return;

    try {
        const token = localStorage.getItem('driver_token');
        await axios.post(`/api/driver/orders/${orderId}/accept`, {}, {
            headers: { Authorization: `Bearer ${token}` }
        });

        alert('✅ Замовлення прийнято');
        map.value?.closePopup();

        const marker = orderMarkers.value[orderId];
        if (marker) {
            map.value?.removeLayer(marker);
            delete orderMarkers.value[orderId];
        }

        newOrders.value = newOrders.value.filter(o => o.id !== orderId);
        renderedOrderIds.value = renderedOrderIds.value.filter(id => id !== orderId);
    } catch (error) {
        if (error.response?.status === 409) {
            alert('❌ Це замовлення вже прийнято іншим водієм');
        } else {
            alert('❌ Помилка при прийнятті замовлення');
            console.error(error);
        }
    }
};
</script>

<style>
.order-switcher {
    display: flex;
    justify-content: center;
    gap: 20px;
    padding: 12px;
    background: #e9f1fc;
    border-radius: 0 0 12px 12px;
    font-weight: 600;
}
.order-switcher button {
    background: none;
    border: none;
    padding: 8px 12px;
    cursor: pointer;
    color: #888;
    border-bottom: 3px solid transparent;
    font-size: 15px;
}
.order-switcher button.active {
    color: #000;
    border-color: #0095FF;
}

.accept-button {
    background-color: #0095FF;
    color: white;
    padding: 6px 12px;
    border: none;
    border-radius: 6px;
    cursor: pointer;
    font-weight: 600;
}

.accept-button:hover {
    background-color: #43a047;
}


.driver-map {
    position: relative;
    width: 100%;
    height: 100vh;
}
.driver-map__top-panel {
    position: absolute;
    width: 100%;
    padding: 24px 0px;
    display: flex;
    justify-content: space-between;
    background-color: #0095FF;
    border-radius: 0px 0px 10px 10px;
    z-index: 1000;
}
.driver-map__block {
    display: flex;
    align-items: center;
    background-color: #e8f1ff;
    border-radius: 12px;
    padding: 4px 10px;
    font-weight: 600;
    margin: 0 5px;
}
.driver-map__block button {
    background: none;
    border: none;
    font-size: 20px;
    font-weight: bold;
    cursor: pointer;
}
.driver-map__container {
    height: 100%;
    width: 100%;
}
.order-alert {
    position: absolute;
    top: 90px;
    left: 50%;
    transform: translateX(-50%);
    background-color: #4caf50;
    color: white;
    padding: 12px 20px;
    border-radius: 8px;
    font-weight: 600;
    z-index: 9999;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
.order-list {
    position: absolute;
    bottom: 20px;
    left: 20px;
    background: white;
    padding: 10px;
    border-radius: 8px;
    box-shadow: 0 4px 8px rgba(0,0,0,0.2);
    font-size: 14px;
    z-index: 9999;
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
</style>
