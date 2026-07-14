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

            <div v-if="order.driver && order.status === 'in_progress'" class="order__driver">
              <p>🚚 <strong>Ваш водій:</strong></p>
              <p>{{ order.driver.name }} {{ order.driver.surname }}</p>
              <p>📞 {{ order.driver.phone }}</p>
            </div>

            <!-- Отображаем, если заказ уже отменён -->
            <div v-if="isOrderCancelled(order)" class="order__cancelled">
              <p><strong>Замовлення відмінено</strong></p>
              <p v-if="order.cancel_reason">Причина: {{ order.cancel_reason }}</p>
              <p v-if="order.cancelled_by">Скасовано: {{ humanCancelledBy(order.cancelled_by) }}</p>
              <p v-if="order.cancelled_at">Час: {{ formatDate(order.cancelled_at) }}</p>
            </div>

            <div class="order__actions">
              <button
                v-if="order.status !== 'completed' && !isOrderCancelled(order)"
                class="order__btn"
                @click="confirmOrder(order)"
              >Підтвердити отримання</button>

              <button
                v-if="!isOrderCancelled(order) && order.status !== 'completed'"
                class="order__btn cancel"
                @click="openCancelModal(order)"
              >
                Скасувати замовлення
              </button>
            </div>
          </div>
        </div>
      </div>
      <p v-else class="order__placeholder">Немає активних замовлень</p>
    </div>

    <!-- Модалка отмены заказа -->
    <div v-if="showCancelModal" class="modal-backdrop" @click.self="closeCancelModal">
      <div class="modal">
        <h3>Причина скасування замовлення</h3>
        <textarea v-model="cancelReason" rows="5" placeholder="Опишіть причину (необов'язково)"></textarea>

        <div class="modal-actions">
          <button class="order__btn cancel" @click="submitCancel" :disabled="cancelLoading">
            {{ cancelLoading ? 'Скасування...' : 'Підтвердити скасування' }}
          </button>
          <button class="order__btn" @click="closeCancelModal" :disabled="cancelLoading">Закрити</button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
/* Комментарии на русском для разработчиков.
   Этот компонент расширяет твой исходный — добавлена логика отмены заказа. */

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
   { title: 'Глибокого\nочищення, 19л', price: "від 70", image: water2 },
  { title: 'Срібна вода, 19л', price: "від 75", image: water1 },
 
])

// --- cancel modal state ---
const showCancelModal = ref(false)
const cancelReason = ref('')
const cancelLoading = ref(false)
const orderToCancel = ref(null) // объект заказа, который отменяем

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

// подтверждение получения (как у тебя было)
const confirmOrder = async (order) => {
  try {
    const token = localStorage.getItem('user_token')
    await axios.post(`/api/orders/${order.id}/complete`, { rating: 5 }, {
      headers: { Authorization: `Bearer ${token}` }
    })
    await fetchActiveOrders()
  } catch (error) {
    console.error('❌ Помилка при завершенні замовлення', error)
    alert(error?.response?.data?.message || 'Помилка при завершенні замовлення')
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
    case 'cancelled': return 'Скасовано'
    default: return status
  }
}

// ---------- отмена заказа ----------
const openCancelModal = (order) => {
  orderToCancel.value = order
  cancelReason.value = ''
  showCancelModal.value = true
}

const closeCancelModal = () => {
  if (cancelLoading.value) return
  showCancelModal.value = false
  orderToCancel.value = null
  cancelReason.value = ''
}

const submitCancel = async () => {
  if (!orderToCancel.value) return
  const id = orderToCancel.value.id
  cancelLoading.value = true
  try {
    const token = localStorage.getItem('user_token')
    const payload = { reason: cancelReason.value || null }
    await axios.post(`/api/orders/${id}/cancel`, payload, {
      headers: { Authorization: `Bearer ${token}` }
    })
    // обновляем список заказов
    await fetchActiveOrders()
    showCancelModal.value = false
    orderToCancel.value = null
    cancelReason.value = ''
    // показываем простое уведомление — если у тебя есть toast, используй его
    if (window?.$toast) window.$toast.success('Замовлення скасовано')
    else alert('Замовлення скасовано')
  } catch (err) {
    console.error('❌ Помилка скасування', err)
    const msg = err?.response?.data?.message || 'Не вдалося скасувати замовлення'
    alert(msg)
  } finally {
    cancelLoading.value = false
  }
}

// помощники
const isOrderCancelled = (order) => {
  // если у заказа статус cancelled или есть cancelled_at (с учётом бэкенда)
  return order.status === 'cancelled' || Boolean(order.cancelled_at)
}

const humanCancelledBy = (who) => {
  if (!who) return ''
  switch (who) {
    case 'user': return 'Користувач'
    case 'driver': return 'Водій'
    case 'system': return 'Система'
    default: return who
  }
}
// ---------- /отмена заказа ----------

onMounted(async () => {
  // при загрузке подгружаем активные заказы (если уже на вкладке)
  if (activeTab.value === 'active') await fetchActiveOrders()

  // Websocket listener (Echo) — если в проекте есть
  if (Echo) {
    Echo.private(`orders`)
      .listen('OrderStatusUpdated', () => {
        fetchActiveOrders()
      })
  }
})
</script>

<style scoped>
.order {
  min-height: 100vh;
  max-width: var(--maxw);
  margin: 0 auto;
  background: var(--bg);
  display: flex;
  flex-direction: column;
}

/* ---- Хедер ---- */
.order__header {
  position: relative;
  height: 132px;
  width: 100%;
  overflow: hidden;
  background: var(--grad-hero);
  border-radius: 0 0 28px 28px;
}
.order__bg {
  position: absolute;
  top: 10px; left: 0;
  width: 100%; height: 100%;
  object-fit: contain;
  opacity: .32;
  pointer-events: none;
  z-index: 1;
}
.order__logout {
  position: absolute;
  top: calc(16px + env(safe-area-inset-top, 0px));
  right: 16px;
  background: rgba(255,255,255,.92);
  color: var(--aqua-700);
  border: none;
  border-radius: var(--r-pill);
  padding: 9px 16px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  z-index: 2;
  box-shadow: var(--sh-xs);
}
.order__logout:active { transform: scale(.97); }

/* ---- Табы (сегмент) ---- */
.order__tabs {
  display: flex;
  gap: 4px;
  padding: 5px;
  margin: -20px 16px 4px;
  position: relative;
  z-index: 3;
  background: var(--surface);
  border-radius: var(--r-pill);
  box-shadow: var(--sh-sm);
}
.order__tabs span {
  flex: 1;
  text-align: center;
  font-weight: 600;
  color: var(--muted);
  font-size: 14px;
  line-height: 1.15;
  cursor: pointer;
  padding: 11px 8px;
  border-radius: var(--r-pill);
  transition: all .2s var(--ease);
}
.order__tabs span br { display: none; }
.order__tabs .active {
  color: var(--aqua-700);
  background: var(--aqua-50);
}

.order__content {
  width: 100%;
  padding: 16px;
  display: flex;
  flex-direction: column;
  gap: 14px;
}

/* ---- Карточки ---- */
.order__card {
  background: var(--surface);
  border-radius: var(--r-lg);
  box-shadow: var(--sh-sm);
  display: flex;
  gap: 14px;
  padding: 14px;
  align-items: center;
}
.order__image {
  width: 96px;
  height: 150px;
  object-fit: contain;
  flex: none;
}
.order__info {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 4px;
  min-width: 0;
}
.order__title {
  font-size: 15.5px;
  font-weight: 700;
  color: var(--ink);
  margin: 0;
  white-space: pre-line;
  text-align: center;
}
.order__info p { margin: 2px 0; font-size: 14px; color: var(--ink-2); }

.order__driver {
  width: 100%;
  background: var(--aqua-50);
  border: 1px solid var(--aqua-200);
  padding: 12px;
  border-radius: var(--r-sm);
  color: var(--ink-2);
  font-size: 14px;
  text-align: center;
}
.order__driver p { margin: 4px 0; }

.order__cancelled {
  width: 100%;
  margin-top: 12px;
  background: var(--danger-bg);
  border: 1px solid #ffb3b3;
  padding: 12px;
  border-radius: var(--r-sm);
  color: #8a1f1f;
  font-size: 14px;
  text-align: center;
}

.order__actions { display: flex; flex-direction: column; gap: 8px; width: 100%; }
.order__btn {
  width: 100%;
  font-size: 15px;
  font-weight: 700;
  background: var(--grad-aqua);
  color: white;
  border-radius: var(--r-sm);
  border: none;
  padding: 13px 16px;
  margin-top: 8px;
  cursor: pointer;
  box-shadow: var(--sh-aqua);
  transition: transform .15s var(--ease);
}
.order__btn:active { transform: scale(.98); }
.order__btn.cancel {
  background: var(--danger-bg);
  color: var(--danger);
  box-shadow: none;
}
.order__placeholder {
  text-align: center;
  color: var(--muted);
  margin-top: 48px;
  font-size: 15px;
}

/* ---- Модалка ---- */
.modal-backdrop {
  position: fixed;
  inset: 0;
  background: rgba(11,31,51,.45);
  backdrop-filter: blur(3px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 20px;
}
.modal {
  width: 100%;
  max-width: 420px;
  background: white;
  border-radius: var(--r-lg);
  padding: 22px;
  box-shadow: var(--sh-lg);
}
.modal h3 { font-size: 17px; margin-bottom: 14px; }
.modal textarea {
  width: 100%;
  box-sizing: border-box;
  border-radius: var(--r-sm);
  border: 1.5px solid var(--line);
  padding: 12px 14px;
  resize: vertical;
  font-size: 15px;
  font-family: inherit;
  outline: none;
}
.modal textarea:focus { border-color: var(--aqua-400); box-shadow: 0 0 0 4px var(--tap); }
.modal-actions {
  display: flex;
  gap: 12px;
  margin-top: 16px;
}
.modal-actions .order__btn { margin-top: 0; }
</style>
