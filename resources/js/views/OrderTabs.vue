<template>
    <div class="driver-map">
        <!-- Верхняя панель -->
        <div class="driver-map__top-panel">
            <div class="driver-map__block" @click="goToMap" style="cursor: pointer;">
                <span>{{ bottles }} бут.</span>
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
const renderedOrderIds = ref([]); // 🆕 список уже отрисованных заказов

const goToMap = () => router.push('/map');

const showOrderAlert = () => {
    newOrderAlert.value = true;
    setTimeout(() => (newOrderAlert.value = false), 3000);
};

const fetchDriverData = async () => {
    try {
        const token = localStorage.getItem('driver_token');
        const res = await axios.get('/api/driver/profile', {
            headers: {
                Authorization: `Bearer ${token}`
            }
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
        const res = await axios.post('/api/driver/pay', {
            amount: parseFloat(topUpAmount.value)
        }, {
            headers: {
                Authorization: `Bearer ${token}`
            }
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

const fetchNewOrders = async () => {
    try {
        const token = localStorage.getItem('driver_token');
        const res = await axios.get('/api/driver/orders/new', {
            headers: {
                Authorization: `Bearer ${token}`
            }
        });

        const orders = res.data;

        orders.forEach(order => {
            if (renderedOrderIds.value.includes(order.id)) return; // 🛑 уже на карте
            renderedOrderIds.value.push(order.id);

            const exists = newOrders.value.some(o => o.id === order.id);
            if (!exists) newOrders.value.unshift(order);

            showOrderAlert();

            if (!order.latitude || !order.longitude) return;

            const lat = parseFloat(order.latitude);
            const lng = parseFloat(order.longitude);
            if (isNaN(lat) || isNaN(lng)) return;

            const icon = L.icon({
                iconUrl: '/assets/loc.png',
                iconSize: [35, 45],
                iconAnchor: [17, 45],
                popupAnchor: [0, -35]
            });

            L.marker([lat, lng], { icon })
                .addTo(map.value)
                .bindPopup(`
                    <b>🚰 Нове замовлення</b><br>
                    <b>Адреса:</b> ${order.address}<br>
                    <b>Кількість:</b> ${order.quantity} бут.<br>
                    <b>Оплата:</b> ${order.payment_method === 'cash' ? 'Готівка' : 'Картка'}
                `)
                .openPopup();

            const pulse = L.circle([lat, lng], {
                radius: 60,
                color: '#3498db',
                fillColor: '#3498db',
                fillOpacity: 0.3
            }).addTo(map.value);

            setTimeout(() => map.value.removeLayer(pulse), 3000);
            map.value.setView([lat, lng], 14);
        });
    } catch (error) {
        console.error('❌ Помилка завантаження нових замовлень', error);
    }
};

onMounted(async () => {
    console.log('🟦 Ініціалізація карти...');
    await fetchDriverData();
    await nextTick();

    map.value = L.map(mapContainer.value, {
        zoomControl: false
    }).setView([50.4501, 30.5234], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map.value);

    await fetchNewOrders();
    setInterval(fetchNewOrders, 60000); // 🔁 каждый 60 сек
});
</script>


<style scoped>
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
