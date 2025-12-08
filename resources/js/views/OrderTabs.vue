<template>
    <div class="driver-map">
        <!-- Табы -->
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
            <button class="logout-btn" @click="logoutDriver">Вийти</button>
        </div>

        <!-- Фильтр -->
        <div class="driver-map__filter-panel">
            <button :class="{ active: selectedWaterType === null }" @click="setWaterFilter(null)">Усі типи</button>
            <button :class="{ active: selectedWaterType === 'silver' }" @click="setWaterFilter('silver')">Показати Срібну</button>
            <button :class="{ active: selectedWaterType === 'deep' }" @click="setWaterFilter('deep')">Показати Глибокого очищення</button>
        </div>

        <!-- Алерт о новом заказе -->
        <div v-if="newOrderAlert" class="order-alert">🚚 Нове замовлення додано на карту</div>

        <!-- Уведомления об отменах -->
        <div v-if="cancelledNotifications.length" class="cancel-notifications">
          <div v-for="n in cancelledNotifications" :key="n.id" class="cancel-item" :class="{ seen: n.seen }">
            <div class="cancel-item__head">
              <strong>⚠️ Замовлення скасоване</strong>
              <small>{{ n.cancelled_at ? formatDate(n.cancelled_at) : '—' }}</small>

            </div>
            <div class="cancel-item__body">
              <div><strong>Адреса:</strong> {{ n.address ?? '—' }}</div>
              <div><strong>Кількість:</strong> {{ n.quantity ?? '—' }}</div>
              <div v-if="n.cancel_reason"><em>Причина: {{ n.cancel_reason }}</em></div>
              <div><strong>Скасовано:</strong> {{ humanCancelledBy(n.cancelled_by) }}</div>
            </div>
            <div class="cancel-item__actions">
              <button @click="markNotificationSeen(n)">ОК</button>
            </div>
          </div>
        </div>

        <div ref="mapContainer" class="driver-map__container"></div>

        <!-- Модалки -->
        <BalanceTopUpModal :visible="showTopUpModal" @close="showTopUpModal = false" />

        <!-- Модалка — недостаточно бутлей -->
        <div v-if="showRefillModal" class="modal-backdrop" @click.self="showRefillModal = false">
            <div class="modal">
                <h3>🚰 Потрібно набрати воду</h3>
                <p>{{ refillMessage }}</p>
                <div class="modal-actions">
                    <button @click="showRefillModal = false">Закрити</button>
                </div>
            </div>
        </div>

        <!-- Модалка — низький баланс -->
        <div v-if="showLowBalanceModal" class="modal-backdrop" @click.self="showLowBalanceModal = false">
            <div class="modal">
                <h3>💳 Низький баланс</h3>
                <p>Ваш баланс менше 10% від суми цього замовлення. Будь ласка, поповніть баланс, щоб приймати замовлення.</p>
                <div class="modal-actions">
                    <button @click="openTopUp">Поповнити</button>
                    <button @click="showLowBalanceModal = false">Пізніше</button>
                </div>
            </div>
        </div>

        <!-- Модалка — уведомление после принятия заказа -->
        <div v-if="showPostAcceptModal" class="modal-backdrop" @click.self="closePostAcceptModal">
            <div class="modal">
                <h3>ℹ️ Після виконання замовлення</h3>
                <p v-html="postAcceptMessage"></p>
                <div class="modal-actions">
                    <button @click="closePostAcceptModal">Понятно</button>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
/*
  Обновлённый driver-map.vue
  - Polling отменённых заказов: GET /api/driver/orders/cancelled?since=ISO
  - Показ уведомлений, удаление маркеров и удаление заказов из локального состояния.
  - Не использует broadcasting / websockets (fallback polling-only).
*/

import { ref, onMounted, nextTick, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import BalanceTopUpModal from '../views/BalanceTopUpModal.vue';

const mapContainer = ref(null)
const map = ref(null)
const bottles = ref(0)
const balance = ref(0)
const ordersState = ref([])
const newOrderAlert = ref(false)
const showTopUpModal = ref(false)
const topUpAmount = ref('')
const router = useRouter()
const renderedOrderIds = ref([])
const orderMarkers = ref({})
const currentTab = ref('active')
const selectedWaterType = ref(null)    // 'silver' | 'deep' | null

// модалки состояния
const showRefillModal = ref(false)
const refillMessage = ref('')
const showLowBalanceModal = ref(false)

// модалка после принятия
const showPostAcceptModal = ref(false)
const postAcceptMessage = ref('')

const authHeaders = () => ({ Authorization: `Bearer ${localStorage.getItem('driver_token')}` })

// --- polling cancelled state ---
const cancelledNotifications = ref([])   // список уведомлений об отменах
const lastCheckedCancelledAt = ref(new Date().toISOString()) // ISO string для since



let cancelledPollTimer = null
let ordersPollTimer = null

const formatDate = (date) => {
  if (!date) return '—'
  return new Date(date).toLocaleString('uk-UA', {
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
    hour: '2-digit',
    minute: '2-digit'
  })
}

const driverId = ref(null)

// helpers
const humanCancelledBy = (who) => {
  if (!who) return '—'
  switch (who) {
    case 'user': return 'Користувач'
    case 'driver': return 'Водій'
    case 'system': return 'Система'
    default: return who
  }
}

/* ---------- маппинги / вспомогательные (без изменений) ---------- */
const WATER_LABELS = { silver: 'Срібна', deep: 'Глибокого очищення' }
const QUALITY_LABELS = { ideal: 'Ідеальний', average: 'Середній', bad: 'Поганий' }

const deliveryLabel = (opt) => ({
    home: 'В квартиру',
    entrance: 'Під під’їзд (−20%)',
    coffee: 'Кав’ярня'
}[opt] ?? '—')

const bottleLabel = (opt) => (opt === 'buy' ? 'Придбати бутелі' : opt === 'own' ? 'Свої бутелі' : '—')
const payLabel = (p) => (p === 'cash' ? 'Готівка' : 'Картка')
const fmt = (n) => Number(n ?? 0).toFixed(2)
const qualityLabel = (q) => QUALITY_LABELS[String(q ?? '').toLowerCase()] ?? '—'

const normalizeWaterCode = (val, productName = '') => {
    if (!val && productName) {
        const name = productName.toLowerCase()
        if (name.includes('сріб')) return 'silver'
        if (name.includes('глиб')) return 'deep'
    }
    if (val == null) return null
    const v = String(val).toLowerCase()
    if (v === 'silver' || v.includes('сріб')) return 'silver'
    if (v === 'deep' || v.includes('глиб')) return 'deep'
    return null
}
const waterLabel = (val) => WATER_LABELS[normalizeWaterCode(val)] ?? '—'

const normalizeBottleOption = (val) => {
    const v = String(val ?? '').toLowerCase()
    if (v === 'buy' || v.includes('куп')) return 'buy'
    if (v === 'own' || v.includes('сво') || v.includes('влас')) return 'own'
    return null
}
const pickBottleOption = (o) =>
    normalizeBottleOption(o.bottle_option ?? o.bottleOption ?? o.bottles_option ?? o.bottlesOption ?? o.bottle)

const pickBottleQuality = (o) => {
    const v = String(o.bottle_quality ?? o.bottleQuality ?? o.quality ?? '').toLowerCase()
    if (['ideal','average','bad'].includes(v)) return v
    if (v.includes('ідеал') || v.includes('идеал')) return 'ideal'
    if (v.includes('серед') || v.includes('сред')) return 'average'
    if (v.includes('поган') || v.includes('плох')) return 'bad'
    return null
}

const getCustomer = (o) => {
    const user = o.user ?? o.customer ?? {}
    const name = o.user_name ?? o.customer_name ?? user.name ?? '—'
    const phone = o.user_phone ?? o.customer_phone ?? user.phone ?? '—'
    return { name, phone }
}
const tel = (p) => (p ? String(p).replace(/[^\d+]/g, '').replace(/(?!^)\+/g, '') : '')

const setWaterFilter = async (type) => { selectedWaterType.value = type; await fetchOrders() }
const goToMap = () => router.push('/map')
const switchTab = async (tab) => { currentTab.value = tab; await fetchOrders() }
const logoutDriver = async () => {
    try {
        await axios.post('/api/driver/logout', {}, {
            headers: authHeaders()
        })

        // удаляем токен
        localStorage.removeItem('driver_token')
        localStorage.removeItem('driver_data')

        // стоп пуллинга отмен
        stopCancelledPolling()

        // редирект на логин водителя
        router.push('/')
    } catch (error) {
        console.error('❌ Помилка виходу:', error)
    }
}
/* ---------- профиль водителя ---------- */
const fetchDriverData = async () => {
    try {
        const res = await axios.get('/api/driver/profile', { headers: authHeaders() })
        const d = res.data?.driver ?? res.data ?? {}
        bottles.value = Number(d.bottles ?? 0)
        balance.value = Number(d.balance ?? 0)
        driverId.value = d.id ?? d.driver_id ?? null
    } catch (e) {
        console.error('❌ Помилка отримання даних водія', e)
    }
}

/* ---------- polling cancelled orders ---------- */
const fetchCancelledOrders = async () => {
    try {
        const res = await axios.get('/api/driver/orders/cancelled', {
            headers: authHeaders(),
            params: { since: lastCheckedCancelledAt.value }
        })
        const data = res.data ?? []
        if (!Array.isArray(data) || data.length === 0) return
console.log('🔄 Запит на скасовані замовлення', lastCheckedCancelledAt.value)

        // упорядочим по времени: новые первыми
        data.sort((a,b) => new Date(b.cancelled_at) - new Date(a.cancelled_at))

        data.forEach((item) => {
            safeHandleOrderCancelled(item)
        })

        // обновим lastChecked до времени последнего элемента
        const latest = data[0]?.cancelled_at ?? new Date().toISOString()
        lastCheckedCancelledAt.value = latest
    } catch (e) {
        console.error('❌ Помилка отримання скасованих замовлень', e)
    }
}

const startOrdersPolling = (intervalMs = 20_000) => {
    fetchOrders() // сразу обновим
    if (ordersPollTimer) clearInterval(ordersPollTimer)
    ordersPollTimer = setInterval(fetchOrders, intervalMs)
}

const stopOrdersPolling = () => {
    if (ordersPollTimer) {
        clearInterval(ordersPollTimer)
        ordersPollTimer = null
    }
}


const startCancelledPolling = (intervalMs = 60_000) => {
    // сразу один запрос, затем интервал
    fetchCancelledOrders()
    if (cancelledPollTimer) clearInterval(cancelledPollTimer)
    cancelledPollTimer = setInterval(fetchCancelledOrders, intervalMs)
}

const stopCancelledPolling = () => {
    if (cancelledPollTimer) {
        clearInterval(cancelledPollTimer)
        cancelledPollTimer = null
    }
}

function safeHandleOrderCancelled(payload) {
    if (!payload || typeof payload !== 'object') return

    const {
        id,
        cancelled_at,
        cancelled_by,
        cancel_reason,
        address,
        quantity
    } = payload

    if (!id) return

    // удаляем маркер
    const marker = orderMarkers.value?.[id]
    if (marker && map.value?.removeLayer) {
        try {
            map.value.removeLayer(marker)
        } catch (_) {}
        delete orderMarkers.value[id]
    }

    // удаляем из состояния
    if (Array.isArray(ordersState.value)) {
        ordersState.value = ordersState.value.filter(o => String(o.id) !== String(id))
    }

    // добавляем уведомление
    cancelledNotifications.value?.unshift?.({
        id,
        cancelled_at,
        cancelled_by,
        cancel_reason,
        address,
        quantity,
        seen: false
    })

    // баннер + звук
    newOrderAlert.value = true
    setTimeout(() => {
        newOrderAlert.value = false
    }, 5000)
}


const markNotificationSeen = (n) => {
    n.seen = true
    // удаляем уведомление через секунду (плавно)
    setTimeout(() => {
        const idx = cancelledNotifications.value.findIndex(x => x.id === n.id)
        if (idx !== -1) cancelledNotifications.value.splice(idx, 1)
    }, 1000)
}

/* ---------- карта / загрузка заказов ---------- */
const clearMarkers = () => {
    Object.values(orderMarkers.value).forEach(marker => { try { map.value?.removeLayer(marker) } catch(_){} })
    orderMarkers.value = {}
    renderedOrderIds.value = []
}

const fetchOrders = async () => {
    try {
        clearMarkers()
        ordersState.value = []

        const endpoint = currentTab.value === 'active' ? '/api/driver/orders/active' : '/api/driver/orders/new'
        const res = await axios.get(endpoint, { headers: authHeaders() })

        let orders = (res.data ?? []).map(o => {
            const statusRaw = (o.status ?? '').toString().trim().toLowerCase()
            const lat = Number(o.latitude ?? o.lat ?? o.Latitude ?? o.Lat)
            const lng = Number(o.longitude ?? o.lng ?? o.Longitude ?? o.Lng)

            return {
                ...o,
                status: statusRaw,
                water_type: normalizeWaterCode(o.water_type ?? o.waterType, o.product_name ?? o.productName),
                _bottle_option: pickBottleOption(o),
                _bottle_quality: pickBottleQuality(o),
                _lat: lat,
                _lng: lng,
                _driver_id: o.driver_id ?? o.driverId ?? null,
            }
        })

        if (currentTab.value === 'active') {
            orders = orders.filter(o => ['accepted', 'in_progress'].includes(o.status))
        } else {
            orders = orders.filter(o => o.status === 'new' && (o._driver_id == null))
        }

        if (selectedWaterType.value) {
            orders = orders.filter(o => o.water_type === selectedWaterType.value)
        }

        orders.forEach(order => {
            const lat = order._lat
            const lng = order._lng
            if (!Number.isFinite(lat) || !Number.isFinite(lng)) return

            renderedOrderIds.value.push(order.id)
            ordersState.value.push(order)

            const icon = L.icon({
                iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
                iconSize: [25, 41],
                iconAnchor: [12, 41],
                popupAnchor: [1, -34],
                shadowUrl: null
            })

            const customer = getCustomer(order)
            const bottleOptionNorm = order._bottle_option
            const bottleQualityNorm = order._bottle_quality

            const qualityRow =
                bottleOptionNorm === 'own' && bottleQualityNorm
                    ? `<b>Якість бутиля:</b> <span class="quality-pill">${qualityLabel(bottleQualityNorm)}</span><br>`
                    : ''

            const customerRows =
                currentTab.value === 'active'
                    ? `<b>Клієнт:</b> ${customer.name}<br>
             <b>Телефон:</b> ${
                        tel(customer.phone) ? `<a href="tel:${tel(customer.phone)}">${customer.phone}</a>` : (customer.phone ?? '—')
                    }<br>`
                    : ''

            const popupHtml = `
        <div class="order-popup">
          <b>${currentTab.value === 'active' ? '🚚 Активне замовлення' : '🚰 Нове замовлення'}</b><br>
          <b>Адреса:</b> ${
                currentTab.value === 'active'
                    ? `<a href="https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(order.address ?? '')}" target="_blank">${order.address ?? '—'}</a>`
                    : (order.address ?? '—')
            }<br>
          <b>Кількість:</b> ${order.quantity} бут.<br>
          <b>Тип води:</b> ${waterLabel(order.water_type)}<br>
          <b>Бутелі:</b> ${bottleLabel(bottleOptionNorm)}<br>
          ${qualityRow}
          <b>Доставка:</b> ${deliveryLabel(order.delivery_option ?? order.deliveryOption)}<br>
          <b>Оплата:</b> ${payLabel(order.payment_method ?? order.paymentMethod)}<br>
          <b>Сума:</b> ${fmt(order.total_price ?? order.totalPrice)} грн<br>
          ${customerRows}
          ${ currentTab.value === 'new' ? `<br><button onclick="window.tryAcceptOrder(${order.id})" class="accept-button">✅ Прийняти</button>` : '' }
        </div>
      `

            const m = L.marker([lat, lng], { icon }).addTo(map.value).bindPopup(popupHtml)
            orderMarkers.value[order.id] = m

            const pulse = L.circle([lat, lng], { radius: 60, color: '#3498db', fillColor: '#3498db', fillOpacity: 0.3 }).addTo(map.value)
            setTimeout(() => { try { map.value?.removeLayer(pulse) } catch(_){} }, 3000)
        })

        if (orders.length) {
            const o = orders[0]
            map.value?.setView([o._lat, o._lng], 14)
        }
    } catch (error) {
        console.error('❌ Помилка завантаження замовлень', error)
    }
}

/* ---------- init ---------- */
onMounted(async () => {
    const urlParams = new URLSearchParams(window.location.search)
    const ipay = urlParams.get('ipay')
    const pid  = urlParams.get('pid')

    // ─────────────────────────────────────────────
    // 🟢 Обработка успешного платежа через PID → checkStatus API
    // ─────────────────────────────────────────────
    if (ipay === 'success' && pid) {
        try {
            const res = await axios.get(`/api/driver/pay/check?pid=${pid}`, {
                headers: authHeaders()
            })

            if (res.data.ipay_status == 5) {
                alert('✅ Баланс успішно поповнено!')
                // Обновляем данные водителя
                await fetchDriverData()
            } else {
                alert('ℹ️ Оплата ще не підтверджена. Спробуйте оновити сторінку через 20–30 сек.')
            }
        } catch (e) {
            console.error('Помилка перевірки платежу:', e)
            alert('⚠️ Помилка при перевірці платежу')
        }
    }
    // ─────────────────────────────────────────────

    await fetchDriverData()
    await nextTick()

    map.value = L.map(mapContainer.value, { zoomControl: false }).setView([50.4501, 30.5234], 13)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map.value)

    await fetchOrders()

    // запускаем polling отмен (интервал 60s)
    console.log('🚀 Стартує polling скасованих замовлень')
    startCancelledPolling(60_000)
        console.log('🚀 Стартує polling нових/активних замовлень')
startOrdersPolling(20_000)
    // экспортируем в window функцию tryAcceptOrder, чтобы кнопка могла её вызвать
    window.tryAcceptOrder = async function(orderId) {
        try {
            let order = ordersState.value.find(o => String(o.id) === String(orderId))
            if (!order) {
                const res = await axios.get(`/api/orders/${orderId}`, { headers: authHeaders() }).catch(()=>null)
                order = res?.data ?? null
            }
            if (!order) {
                alert('❌ Неможливо знайти дані замовлення')
                return
            }

            const requiredBottles = Number(order.quantity ?? order.qty ?? order.count ?? 0)
            const driverBottles = Number(bottles.value ?? 0)

            if (driverBottles < requiredBottles) {
                refillMessage.value = `У вас ${driverBottles} бут., а потрібно ${requiredBottles}. Спочатку наберіть води або поверніться після поповнення запасу.`
                showRefillModal.value = true
                return
            }

            const orderSum = Number(order.total_price ?? order.totalPrice ?? order.price ?? 0)
            const minRequiredBalance = 0.1 * orderSum

            if (orderSum > 0 && Number(balance.value ?? 0) < minRequiredBalance) {
                showLowBalanceModal.value = true
                return
            }

            const confirmAccept = confirm('Підтвердити прийняття замовлення?')
            if (!confirmAccept) return

            await axios.post(`/api/driver/orders/${orderId}/accept`, {}, { headers: authHeaders() })

            const fee = Number(0.1 * orderSum)
            if (orderSum > 0) {
                postAcceptMessage.value = `Після виконання цього замовлення з Вашого балансу буде списано <b>${fee.toFixed(2)} грн</b> (10% від суми замовлення ${fmt(orderSum)} грн).`
            } else {
                postAcceptMessage.value = `Після виконання цього замовлення з Вашого балансу буде списано <b>10% від суми замовлення</b> (сума замовлення = ${fmt(orderSum)} грн).`
            }
            showPostAcceptModal.value = true

            map.value?.closePopup()
            const marker = orderMarkers.value[orderId]
            if (marker) { try { map.value?.removeLayer(marker) } catch(_){}; delete orderMarkers.value[orderId] }

            await fetchDriverData()
            currentTab.value = 'active'
            await fetchOrders()
        } catch (error) {
            if (error?.response?.status === 409) alert('❌ Це замовлення вже прийнято іншим водієм')
            else { alert('❌ Помилка при прийнятті замовлення'); console.error(error) }
        }
    }
}) // end onMounted


onBeforeUnmount(() => {
    // очищаем polling и window.tryAcceptOrder
    stopCancelledPolling()
    try { delete window.tryAcceptOrder } catch(_) {}
})

// открыть модалку пополнения
const openTopUp = () => {
    showLowBalanceModal.value = false
    showTopUpModal.value = true
}

const closePostAcceptModal = () => {
    showPostAcceptModal.value = false
    postAcceptMessage.value = ''
}
</script>

<style scoped>
/* Простейшие стили модалок (подложка, окно) — при необходимости адаптируйте под ваш дизайн */
.modal-backdrop{
    position: fixed;
    left:0;right:0;top:0;bottom:0;
    background: rgba(0,0,0,0.5);
    display:flex;
    align-items:center;
    justify-content:center;
    z-index: 9999;
}
.modal{
    background: #fff;
    padding: 18px;
    border-radius: 8px;
    width: 360px;
    max-width: 92%;
    box-shadow: 0 8px 24px rgba(0,0,0,0.2);
}
.modal h3{ margin:0 0 8px 0; }
.modal p{ margin:0 0 12px 0; line-height:1.4; }
.modal-actions{ display:flex; gap:8px; justify-content:flex-end; }
.modal-actions button{ padding:8px 12px; border-radius:6px; cursor:pointer; border:none; background:#0095FF; color:white; font-weight:600; }
.modal-actions button:hover { opacity:0.9; }
.accept-button { padding:6px 8px; cursor:pointer; }
.modal-overlay {
  position: fixed;
  top: 0; left: 0;
  width: 100%; height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.modal {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  width: 300px;
}

.driver-map { position: fixed; width: 100%; height: 100%; }

.logout-btn {
  margin-left: auto;
  background-color: #ffffffdd;
  color: #3498db;
  border: 1px solid #3498db;
  border-radius: 8px;
  padding: 8px 14px;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: background-color 0.2s ease;
}
.logout-btn:hover {
  background-color: #3498db;
  color: white;
}

/* Табы */
.order-switcher {
    display: flex; justify-content: center; gap: 20px; padding: 12px;
    background: #e9f1fc; border-radius: 0 0 12px 12px; font-weight: 600;
}
.order-switcher button {
    background: none; border: none; padding: 8px 12px; cursor: pointer; color: #888; border-bottom: 3px solid transparent; font-size: 15px;
}
.order-switcher button.active { color: #000; border-color: #0095FF; }

.driver-map__top-panel {
    position: absolute; width: 100%; padding: 24px 0px; display: flex; justify-content: space-between;
    background-color: #0095FF; border-radius: 0px 0px 10px 10px; z-index: 1000;
}
.driver-map__block {
    display: flex; align-items: center; background-color: #e8f1ff; border-radius: 12px; padding: 4px 10px; font-weight: 600; margin: 0 5px;
}
.driver-map__block button { background: none; border: none; font-size: 20px; font-weight: bold; cursor: pointer; }

.driver-map__container { height: 100%; width: 100%; }

.driver-map__filter-panel {
    position: absolute;
    bottom: 5px;
    left: 10px;
    z-index: 999;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.driver-map__filter-panel button {
    background-color: white;
    color: #0095FF;
    font-weight: 600;
    padding: 8px 12px;
    border: 2px solid #0095FF;
    border-radius: 8px;
    cursor: pointer;
    transition: 0.3s;
}
.driver-map__filter-panel button.active {
    background-color: #0095FF;
    color: white;
}

/* Попап и алерт */
.accept-button {
    background-color: #0095FF; color: white; padding: 6px 12px; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;
}
.accept-button:hover { background-color: #43a047; }

.order-alert {
    position: absolute; top: 90px; left: 50%; transform: translateX(-50%);
    background-color: #4caf50; color: white; padding: 12px 20px; border-radius: 8px; font-weight: 600;
    z-index: 9999; box-shadow: 0 4px 12px rgba(0, 0, 0, 0.3);
}
.order-popup .quality-pill {
    display:inline-block; padding:2px 8px; border-radius:999px;
    background:#eef4ff; border:1px solid #d8e2ff; font-size:12px; font-weight:700;
}

/* Уведомления об отменах */
.cancel-notifications {
  position: absolute;
  top: 120px;
  left: 50%;
  transform: translateX(-50%);
  width: 92%;
  max-width: 760px;
  z-index: 12000;
}
.cancel-item {
  background: #fff8f8;
  border: 1px solid #ffbebe;
  padding: 10px 12px;
  border-radius: 8px;
  margin-bottom: 8px;
  box-shadow: 0 6px 18px rgba(0,0,0,0.08);
  transition: transform .18s ease, opacity .18s ease;
}
.cancel-item.seen { opacity: 0.6; transform: translateY(-6px); }
.cancel-item__head { display:flex; justify-content:space-between; gap:8px; align-items:center; }
.cancel-item__body { font-size:14px; color:#6b1f1f; margin-top:6px; }
.cancel-item__actions { display:flex; justify-content:flex-end; margin-top:8px; }
.cancel-item__actions button { background:#0095FF; color:#fff; border:none; padding:6px 10px; border-radius:6px; cursor:pointer; }

/* Модалка общий overlay */
.modal { position: fixed; inset: 0; z-index: 10000; display: grid; place-items: center; }
.modal__overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
.modal__content { position: relative; background: #fff; padding: 16px; border-radius: 10px; min-width: 320px; }
</style>
