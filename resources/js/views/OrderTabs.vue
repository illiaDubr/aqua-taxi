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

    <div v-if="newOrderAlert" class="order-alert">🚚 Нове замовлення додано на карту</div>
    <div ref="mapContainer" class="driver-map__container"></div>

    <!-- Модалка -->
    <BalanceTopUpModal :visible="showTopUpModal" @close="showTopUpModal = false" />
  </div>
</template>

<script setup>
import { ref, onMounted, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import BalanceTopUpModal from '../views/BalanceTopUpModal.vue';
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

const icon = L.icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
})
/* ---------- state ---------- */
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


const authHeaders = () => ({ Authorization: `Bearer ${localStorage.getItem('driver_token')}` })


function logoutDriver() {
  const token = localStorage.getItem('driver_token')
  if (!token) {
    alert('Ви вже вийшли з системи')
    return
  }

  axios.post('/api/driver/logout', {}, {
    headers: { Authorization: `Bearer ${token}` }
  })
  .then(() => {
    localStorage.removeItem('driver_token')
    router.push('/auth') // или другой маршрут авторизации
  })
  .catch(err => {
    console.error('❌ Помилка при виході', err)
    alert('Помилка при виході')
  })
}
/* ---------- маппинги ---------- */
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

/* ---------- нормализация ---------- */
// Поддержка water_type ИЛИ product_name
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

/* ---------- вспомогательное ---------- */
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

/* ---------- профиль водителя ---------- */
const fetchDriverData = async () => {
    try {
        const res = await axios.get('/api/driver/profile', { headers: authHeaders() })
        const d = res.data?.driver ?? res.data ?? {}
        bottles.value = Number(d.bottles ?? 0)
        balance.value = Number(d.balance ?? 0)
    } catch (e) {
        console.error('❌ Помилка отримання даних водія', e)
    }
}

/* ---------- платеж ---------- */


const showModal = ref(false);
// const payWithIPay = async () => {
//   try {
//     const res = await axios.post('/api/driver/pay', {
//       amount: parseFloat(topUpAmount.value)
//     }, { headers: authHeaders() })

//     const { url, params } = res.data
//     const form = document.createElement('form')
//     form.method = 'POST'
//     form.action = url

//     Object.entries(params).forEach(([key, value]) => {
//       const input = document.createElement('input')
//       input.type = 'hidden'
//       input.name = key
//       input.value = value
//       form.appendChild(input)
//     })

//     document.body.appendChild(form)
//     form.submit()
//   } catch (error) {
//     alert('❌ Помилка при генерації платежу')
//     console.error(error)
//   }
// }

/* ---------- карта ---------- */
const clearMarkers = () => {
    Object.values(orderMarkers.value).forEach(marker => { try { map.value?.removeLayer(marker) } catch(_){} })
    orderMarkers.value = {}
    renderedOrderIds.value = []
}

/* ---------- загрузка заказов ---------- */
const fetchOrders = async () => {
    try {
        clearMarkers()
        ordersState.value = []

        const endpoint = currentTab.value === 'active' ? '/api/driver/orders/active' : '/api/driver/orders/new'
        const res = await axios.get(endpoint, { headers: authHeaders() })

        console.debug('[orders raw]', res.data)

        let orders = (res.data ?? []).map(o => {
            const statusRaw = (o.status ?? '').toString().trim().toLowerCase()
            const lat = Number(o.latitude ?? o.lat ?? o.Latitude ?? o.Lat)
            const lng = Number(o.longitude ?? o.lng ?? o.Longitude ?? o.Lng)

            return {
                ...o,
                status: statusRaw,
                // если water_type пустой — извлекаем из product_name
                water_type: normalizeWaterCode(o.water_type ?? o.waterType, o.product_name ?? o.productName),
                _bottle_option: pickBottleOption(o),
                _bottle_quality: pickBottleQuality(o),
                _lat: lat,
                _lng: lng,
                _driver_id: o.driver_id ?? o.driverId ?? null,
            }
        })

        // страховка по статусам
        if (currentTab.value === 'active') {
            orders = orders.filter(o => ['accepted', 'in_progress'].includes(o.status))
        } else {
            orders = orders.filter(o => o.status === 'new' && (o._driver_id == null))
        }

        // фильтр по типу воды
        if (selectedWaterType.value) {
            orders = orders.filter(o => o.water_type === selectedWaterType.value)
        }

        console.debug('[orders normalized]',
            orders.map(o => ({ id: o.id, status: o.status, _lat: o._lat, _lng: o._lng, _driver_id: o._driver_id }))
        )

        // рендерим
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
          ${ currentTab.value === 'new' ? `<br><button onclick="window.acceptOrder(${order.id})" class="accept-button">✅ Прийняти</button>` : '' }
        </div>
      `

            const m = L.marker([lat, lng], { icon }).addTo(map.value).bindPopup(popupHtml)
            orderMarkers.value[order.id] = m

            const pulse = L.circle([lat, lng], { radius: 60, color: '#3498db', fillColor: '#3498db', fillOpacity: 0.3 }).addTo(map.value)
            setTimeout(() => { try { map.value?.removeLayer(pulse) } catch(_){} }, 3000)
        })

        // автофокус
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
  if (urlParams.get('ipay') === 'success') {
    alert('✅ Баланс успішно поповнено')
  }

  await fetchDriverData()
  await nextTick()

  map.value = L.map(mapContainer.value, { zoomControl: false }).setView([50.4501, 30.5234], 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data © OpenStreetMap contributors'
  }).addTo(map.value)

  await fetchOrders()
})



/* ---------- accept ---------- */
window.acceptOrder = async function(orderId) {
    const confirmAccept = confirm('Підтвердити прийняття замовлення?')
    if (!confirmAccept) return
    try {
        await axios.post(`/api/driver/orders/${orderId}/accept`, {}, { headers: authHeaders() })
        alert('✅ Замовлення прийнято')
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
</script>

<style>
.driver-map { position: fixed; width: 100%; height: 100vh; }

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

/* Топ-панель */
.driver-map__top-panel {
    position: absolute; width: 100%; padding: 24px 0px; display: flex; justify-content: space-between;
    background-color: #0095FF; border-radius: 0px 0px 10px 10px; z-index: 1000;
}
.driver-map__block {
    display: flex; align-items: center; background-color: #e8f1ff; border-radius: 12px; padding: 4px 10px; font-weight: 600; margin: 0 5px;
}
.driver-map__block button { background: none; border: none; font-size: 20px; font-weight: bold; cursor: pointer; }

/* Карта */
.driver-map__container { height: 100%; width: 100%; }

/* Фильтры */
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

/* Модалка */
.modal { position: fixed; inset: 0; z-index: 10000; display: grid; place-items: center; }
.modal__overlay { position: absolute; inset: 0; background: rgba(0,0,0,0.5); }
.modal__content { position: relative; background: #fff; padding: 16px; border-radius: 10px; min-width: 320px; }
</style>
