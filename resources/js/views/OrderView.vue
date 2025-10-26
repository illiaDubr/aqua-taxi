<template>
    <div class="order">
        <div class="order__header">
            <img :src="city" alt="background" class="order__bg" />
            <button class="order__logout" @click="logoutUser">Вийти</button>
        </div>

        <div class="order__tabs">
            <span :class="{ active: activeTab === 'order' }" @click="activeTab = 'order'">Замовити</span>
            <span :class="{ active: activeTab === 'active' }" @click="switchToActiveOrders">Активні<br> замовлення</span>
        </div>

        <div class="order__content" v-if="activeTab === 'order'">
            <div v-for="(product, index) in products" :key="index" class="order__card">
                <img :src="product.image" alt="product" class="order__image" />
                <div class="order__info">
                    <h3 class="order__title">{{ product.title }}</h3>
                    <p>{{ product.price }} грн</p>
                    <button class="order__btn" @click="goToOrderForm(product)">Замовити</button>
                </div>
            </div>
        </div>

        <div class="order__content" v-else>
            <div v-if="activeOrders.length">
                <div v-for="order in activeOrders" :key="order.id" class="order__card">
                    <div class="order__info">
                        <h3 class="order__title">Адреса: {{ order.address }}</h3>
                        <p>Кількість: {{ order.quantity }}</p>
                        <p>Час: {{ order.delivery_time_type === 'custom' ? formatDate(order.custom_time) : 'Найближчий час' }}</p>
                        <p>Статус: {{ statusText(order.status) }}</p>

                        <div v-if="order.status === 'in_progress' && order.driver" class="order__driver">
                            <p>🚚 <strong>Ваш водій:</strong></p>
                            <p>{{ order.driver.name }} {{ order.driver.surname }}</p>
                            <p>📞 {{ order.driver.phone }}</p>
                        </div>

                        <button
                            v-if="order.status !== 'completed'"
                            class="order__btn"
                            @click="confirmOrder(order)"
                        >Підтвердити отримання</button>
                    </div>
                </div>
            </div>
            <p v-else class="order__placeholder">Немає активних замовлень</p>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'

import city from '@/assets/city.png'
import water1 from '@/assets/1.png'
import water2 from '@/assets/2.png'

const Echo = window.Echo
const router = useRouter()
const activeTab = ref('order')
const activeOrders = ref([])

const products = ref([
    { title: 'Срібна вода, 19л', price: 260, image: water1 },
    { title: 'Глибокого\nочищення, 19л', price: 250, image: water2 }
])

const goToOrderForm = (product) => {
    router.push({ name: 'orderForm', params: { productId: encodeURIComponent(product.title) } })
}

const switchToActiveOrders = async () => {
    activeTab.value = 'active'
    await fetchActiveOrders()
}

const fetchActiveOrders = async () => {
    try {
        const token = localStorage.getItem('user_token')
        const { data } = await axios.get('/api/orders/active', {
            headers: { Authorization: `Bearer ${token}` }
        })
        activeOrders.value = data
    } catch (error) {
        console.error('❌ Не вдалося отримати активні замовлення', error)
    }
}

// 👉 без попапа: сразу завершаем заказ с дефолтным рейтингом 5
const confirmOrder = async (order) => {
    try {
        const token = localStorage.getItem('user_token')
        await axios.post(`/api/orders/${order.id}/complete`, { rating: 5 }, {
            headers: { Authorization: `Bearer ${token}` }
        })
        await fetchActiveOrders()
    } catch (error) {
        console.error('❌ Помилка при завершенні замовлення', error)
    }
}

const logoutUser = async () => {
  try {
    const token = localStorage.getItem('user_token')
    if (!token) {
      alert('Ви вже вийшли з системи')
      return
    }

    await axios.post('/api/user/logout', {}, {
      headers: { Authorization: `Bearer ${token}` }
    })

    localStorage.removeItem('user_token')
    localStorage.removeItem('active_token')
    router.push('/auth') // или другой маршрут авторизации
  } catch (error) {
    console.error('❌ Помилка при виході', error)
    alert('Помилка при виході')
  }
}


const formatDate = (str) => {
    if (!str) return ''
    const d = new Date(str)
    return d.toLocaleString('uk-UA', {
        day: '2-digit',
        month: '2-digit',
        hour: '2-digit',
        minute: '2-digit',
    })
}

const statusText = (status) => {
    switch (status) {
        case 'new': return 'Очікує підтвердження'
        case 'in_progress': return 'В дорозі'
        case 'completed': return 'Виконано'
        default: return status
    }
}

onMounted(() => {
    Echo.private(`orders`)
        .listen('OrderStatusUpdated', () => {
            fetchActiveOrders()
        })
})
</script>

<style scoped>
.order__logout {
  position: absolute;
  top: 16px;
  right: 16px;
  background-color: #ffffffdd;
  color: #3498db;
  border: 1px solid #3498db;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  z-index: 2;
  transition: background-color 0.2s ease;
}
.order__logout:hover {
  background-color: #3498db;
  color: white;
}

.order__driver {
    background: #f0f8ff;
    border: 1px solid #3498db;
    padding: 12px;
    border-radius: 10px;
    color: #333;
    font-size: 14px;
    text-align: center;
}
.order__driver p {
    margin: 4px 0;
}

/* удалены стили модалки и попапа подтверждения */

.order__title {
    font-size: 15px;
    font-weight: 600;
    margin: 0;
    white-space: pre-line;
    text-align: center;
}
.order {
    min-height: 100vh;
    background-color: #f9f9f9;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.order__header {
    position: relative;
    height: 140px;
    width: 100%;
    background-color: #00aaff;
}
.order__bg {
    position: absolute;
    top: 16px;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    pointer-events: none;
    z-index: 1;
}
.order__tabs {
    display: flex;
    justify-content: space-around;
    width: 100%;
    background: white;
    padding: 32px 0;
    border-radius: 16px 16px 0 0;
    margin-top: -24px;
    z-index: 2;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
}
.order__tabs span {
    font-weight: 600;
    color: #ccc;
    font-size: 20px;
    cursor: pointer;
    padding-bottom: 6px;
}
.order__tabs .active {
    color: #3498db;
    border-bottom: 2px solid #3498db;
}
.order__content {
    width: 100%;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}
.order__card {
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    display: flex;
    gap: 12px;
    padding: 12px;
    margin: 16px;
    align-items: center;
}
.order__image {
    width: 150px;
    height: 220px;
    object-fit: contain;
}
.order__info {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}
.order__btn {
    font-size: 16px;
    background-color: #3498db;
    color: white;
    border-radius: 10px;
    border: none;
    padding: 16px 20px;
}
.order__placeholder {
    text-align: center;
    color: #777;
    margin-top: 40px;
}
</style>
