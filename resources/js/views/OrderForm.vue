<script setup>
import { ref, computed, watch, watchEffect, nextTick, onBeforeUnmount } from 'vue'

import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'
const icon = L.icon({
  iconUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-icon.png',
  shadowUrl: 'https://unpkg.com/leaflet@1.9.4/dist/images/marker-shadow.png',
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
})
const route = useRoute()
const router = useRouter()

// === Название товара из роута
const productName = computed(() =>
    decodeURIComponent(route.params.productId || 'Срібна вода, 19л')
)

// === Код типа воды
const waterType = computed(() => {
    const n = (productName.value || '')
        .toLowerCase()
        .replace(/19\s*л/g, '')
        .replace(/[(),]/g, ' ')
        .replace(/\s+/g, ' ')
        .trim()
    if (n.includes('срібн')) return 'silver'
    if (n.includes('глибок')) return 'deep'
    return null
})

// --- state
const address = ref('')
const quantity = ref('')               // select строкой → ниже приводим к числу
const bottleOption = ref('own')        // 'own' | 'buy'
const bottleQuality = ref('ideal')     // 'ideal' | 'average' | 'bad' (только для own)
const timeOption = ref('now')          // 'now' | 'custom'
const customTime = ref('')
const paymentMethod = ref('cash')
const deliveryOption = ref('home')     // 'home' | 'entrance' | 'coffee'
const qualityWarning = ref('')
const showQualityAlert = ref(false)
watch([bottleOption, bottleQuality], ([option, quality]) => {
  if (option === 'own' && quality === 'bad') {
    bottleOption.value = 'buy'
    if (!showQualityAlert.value) {
      qualityWarning.value = 'Ваші бутлі мають поганий стан. Ми автоматично переключили вас на покупку нових бутлів.'
      showQualityAlert.value = true
      setTimeout(() => showQualityAlert.value = false, 5000)
    }
  }
})

// --- карта/ручной выбор
const manualMode = ref(false)
const mapRef = ref(null)
const map = ref(null)
const marker = ref(null)
const lat = ref(null)
const lng = ref(null)

// === Валидация адреса через Nominatim
const validateAddress = async (addr) => {
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addr)}`
    const response = await fetch(url, { headers: { 'User-Agent': 'AquaTaxi (web)' } })
    const data = await response.json()
    return data.length ? data[0] : null
}

// ==== ТАРИФЫ (по ТЗ)
const UNIT_PRICES = {
    deep:   { one: 250, many: 180 },
    silver: { one: 260, many: 190 },
}
const BUY_SURCHARGE = 350      // + за покупку бутля
const COFFEE_PRICE  = 70       // кав’ярня (від 5 бутлів)
const COFFEE_TIER_PRICES = {
  2: 120,
  3: 100,
  4: 90,
  5: 80,
  more: 70,
}
// --- цена за 1 бутыль (только вода)
const unitWaterPrice = computed(() => {
  const qty = Number.parseInt(quantity.value, 10) || 0
  const wt = waterType.value
  if (!wt || !qty) return 0

  // === КАВ’ЯРНЯ ===
  if (deliveryOption.value === 'coffee') {
    if (qty < 2) return 0
    if (qty >= 6) return COFFEE_TIER_PRICES.more
    return COFFEE_TIER_PRICES[qty] ?? 0
  }

  // === ОБЫЧНАЯ ЛОГИКА ===
  const tier = qty >= 2 ? 'many' : 'one'
  return UNIT_PRICES[wt]?.[tier] ?? 0
})

// --- наценка за покупку бутля (за 1 шт)
const unitBottleSurcharge = computed(() => (bottleOption.value === 'buy' ? BUY_SURCHARGE : 0))

// --- скидка -20% применяется только к части "вода"
const discountFactor = computed(() => (deliveryOption.value === 'entrance' ? 0.8 : 1))

// --- ИТОГО (как в UI)
const totalAmount = computed(() => {
    const qty = Number.parseInt(quantity.value, 10) || 0
    if (!qty || !waterType.value) return 0

    // защита для кофе-режима
   if (deliveryOption.value === 'coffee' && qty < 2) return 0

    const waterPart  = Math.round(unitWaterPrice.value * discountFactor.value) * qty
    const bottlePart = unitBottleSurcharge.value * (bottleOption.value === 'buy' ? qty : 0)
    return waterPart + bottlePart
})

// --- мета для бэка (чтобы не пересчитал по старому)
const pricingMeta = computed(() => {
  const qty = Number.parseInt(quantity.value, 10) || 0
  const wt  = waterType.value

  return {
    water_type: wt,
    delivery_option: deliveryOption.value,
    coffee_pricing: deliveryOption.value === 'coffee'
      ? {
          tiers: COFFEE_TIER_PRICES,
          applied_unit_price: unitWaterPrice.value,
        }
      : null,
    discount_applied: deliveryOption.value === 'entrance' ? 0.2 : 0,
    unit_bottle_surcharge: unitBottleSurcharge.value,
  }
})


// --- уничтожение карты
const destroyMap = () => {
    if (map.value) {
        map.value.off()
        map.value.remove()
        map.value = null
    }
    marker.value = null
}
onBeforeUnmount(destroyMap)

// --- включение ручного режима
const activateManual = async () => {
  if (manualMode.value) return
  manualMode.value = true
  await nextTick()
  if (!mapRef.value || map.value) return

  map.value = L.map(mapRef.value).setView([50.4501, 30.5234], 13)
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: 'Map data © OpenStreetMap contributors',
  }).addTo(map.value)

  map.value.on('click', (e) => {
    lat.value = e.latlng.lat
    lng.value = e.latlng.lng
    if (marker.value) {
      marker.value.setLatLng(e.latlng)
    } else {
      marker.value = L.marker(e.latlng, { icon }).addTo(map.value)
    }
  })
} // ← 

// подстраховка рендера карты
watchEffect(async () => {
    if (manualMode.value && mapRef.value && !map.value) {
        await nextTick()
        if (!map.value) {
            map.value = L.map(mapRef.value).setView([50.4501, 30.5234], 13)
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: 'Map data © OpenStreetMap contributors',
            }).addTo(map.value)
            map.value.on('click', (e) => {
                lat.value = e.latlng.lat
                lng.value = e.latlng.lng
                if (marker.value) marker.value.setLatLng(e.latlng)
                else marker.value = L.marker(e.latlng, { icon }).addTo(map.value)
            })
        }
    }
})

// --- отправка заказа
const createOrder = async () => {
    try {
        let result = null

        if (manualMode.value) {
            if (lat.value == null || lng.value == null) {
                alert('❌ Оберіть точку на карті')
                return
            }
        } else {
            result = await validateAddress(address.value)
            if (!result) {
                alert('❌ Адрес не знайдено. Введіть точнішу адресу або виберіть на карті.')
                return
            }
        }

        const qty = Number.parseInt(quantity.value, 10) || 0
        if (!qty) { alert('❌ Оберіть кількість'); return }
        if (deliveryOption.value === 'coffee' && qty < 2) { alert('❌ Мінімальне замовлення для кав’ярні — 2 бутлі'); return }
        if (!waterType.value) { alert('❌ Невідомий тип води. Оновіть сторінку або виберіть товар повторно.'); return }

        const token = localStorage.getItem('user_token')

        // адрес для бэка: либо введённый, либо "<lat>, <lng>" в ручном режиме
        const addressText = address.value.trim() || (manualMode.value ? `${lat.value}, ${lng.value}` : '')
        if (!addressText) {
            alert('❌ Вкажіть адресу або оберіть точку на карті')
            return
        }

        // разложение суммы = как в UI
        const waterUnit = unitWaterPrice.value
        const bottleUnit = unitBottleSurcharge.value
        const purchaseCount = bottleOption.value === 'buy' ? qty : 0
        const waterAfterDiscount = Math.round(waterUnit * discountFactor.value) * qty
        const bottleSubtotal = bottleUnit * purchaseCount
        const totalUI = waterAfterDiscount + bottleSubtotal

        const payload = {
            product_name: productName.value,
            water_type: waterType.value,
            quantity: qty,

            bottle_option: bottleOption.value,
            bottle_quality: bottleOption.value === 'own' ? bottleQuality.value : null,
            purchase_bottle_count: purchaseCount,           // ВАЖНО для бэка

            delivery_time_type: timeOption.value,
            custom_time: customTime.value || null,
            payment_method: paymentMethod.value,
            delivery_option: deliveryOption.value,

            // Цены — чтобы бэк не пересчитывал по-старому
            unit_water_price: waterUnit,
            unit_bottle_surcharge: bottleUnit,
            water_subtotal_after_discount: waterAfterDiscount,
            bottle_subtotal: bottleSubtotal,
            discount_factor: discountFactor.value,
            pricing_meta: pricingMeta.value,

            // Итого — ровно как в интерфейсе
            total_price: Number(totalUI),

            // Гео/адрес
            lat: manualMode.value ? lat.value : Number(result?.lat),
            lng: manualMode.value ? lng.value : Number(result?.lon),
            address: addressText,
        }

        await axios.post('/api/orders', payload, {
            headers: { Authorization: `Bearer ${token}` },
        })

        router.push({
            name: 'orders',
            query: {
                showPopup: true,
                product: productName.value,
                quantity: String(qty),
                time: timeOption.value === 'custom' ? customTime.value : 'Протягом години',
            },
        })
    } catch (error) {
        // 422: показываем какие поля упали
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errs = error.response.data?.errors || {}
            const lines = Object.entries(errs).map(([k, v]) => {
                const arr = Array.isArray(v) ? v : [v]
                return `${k}: ${arr.join(', ')}`
            })
            const msg = lines.length ? lines.join('\n') : (error.response.data?.message || 'Validation error')
            alert('❌ ' + msg)
            return
        }
        // прочие ошибки
        alert('❌ Помилка створення замовлення')
        console.error(error)
    }
}
</script>


<template>
    <div class="form">
        <div class="form__bg"></div>

        <div class="form__card">
            <h2 class="form__title">Оформлення<br />замовлення</h2>
            <p class="form__subtitle">{{ productName }}</p>

            <!-- адрес -->
            <div class="form__group">
                <label>Введіть ваші дані</label>
                <input type="text" placeholder="Введіть назву міста та адресу" v-model="address" />

                <!-- КНОПКА ТОЛЬКО ВКЛЮЧАЕТ КАРТУ -->
                <button
                    v-if="!manualMode"
                    type="button"
                    class="manual-btn"
                    @click="activateManual"
                >
                    Вибрати на карті
                </button>
                <div v-else class="manual-badge" aria-disabled="true">
                    🗺️ Карта активна — виберіть точку
                </div>

                <div v-if="manualMode" class="geo-warning">
                    <p>📍 Клікніть по карті, щоб обрати місцезнаходження:</p>
                    <div ref="mapRef" class="map-container"></div>
                    <p v-if="lat && lng">Обрані координати: {{ lat.toFixed(5) }}, {{ lng.toFixed(5) }}</p>
                </div>

                <select v-model="quantity">
                    <option disabled value="">Кількість бутелів</option>
                    <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
                </select>
            </div>

            <!-- опция доставки -->
            <div class="form__group">
                <label>Варіант доставки</label>
                <div class="form__switch">
                    <button :class="{ active: deliveryOption === 'home' }" @click="deliveryOption = 'home'">
                        Додому/Офіс
                    </button>
                    <button :class="{ active: deliveryOption === 'entrance' }" @click="deliveryOption = 'entrance'">
                        Додому/Офіс (-20%)
                    </button>
                    <button :class="{ active: deliveryOption === 'coffee' }" @click="deliveryOption = 'coffee'">
                        Кав’ярня (від 2 бутлів)
                    </button>
                </div>
            </div>

            <!-- бутлі -->
            <div class="form__group bottle-section">
                <label>Бутелі</label>

                <!-- сегмент-контрол -->
                <div class="segmented">
                    <button
                        :class="{ active: bottleOption === 'own' }"
                        @click="bottleOption = 'own'"
                        type="button"
                    >
                        Свої бутелі
                    </button>
                    <button
                        :class="{ active: bottleOption === 'buy' }"
                        @click="bottleOption = 'buy'"
                        type="button"
                    >
                        Придбати бутлі
                    </button>
                </div>

                <!-- качество только для своих -->
                <transition name="fade-slide">
  <div v-if="bottleOption === 'own'" class="quality-row">
    <label class="quality-label">Оберіть якість ваших бутлів:</label>
    <select v-model="bottleQuality" class="quality-select">
      <option value="ideal">Ідеальний</option>
      <option value="average">Середній</option>
      <option value="bad">Поганий</option>
    </select>

    <transition name="fade-slide">
      <div v-if="showQualityAlert" class="quality-alert">
        <span class="quality-alert-text">{{ qualityWarning }}</span>
        <button class="quality-alert-close" @click="showQualityAlert = false">×</button>
      </div>
    </transition>
  </div>
</transition>

                <!-- чип с подсказкой для покупки -->
                <transition name="fade-slide">
                    <div v-if="bottleOption === 'buy'" class="info-chip">
                        <span class="chip-dot" aria-hidden="true"></span>
                        +350 грн за кожен бутиль
                    </div>
                </transition>
            </div>

            <!-- время -->
            <div class="form__group">
                <label>Час</label>
                <div class="form__switch">
                    <button :class="{ active: timeOption === 'now' }" @click="timeOption = 'now'">Найближчий час</button>
                    <button :class="{ active: timeOption === 'custom' }" @click="timeOption = 'custom'">На обраний час</button>
                </div>
                <input v-if="timeOption === 'custom'" type="datetime-local" v-model="customTime" />
            </div>

            <!-- оплата -->
            <div class="form__group">
                <label>Спосіб оплати</label>
                <div class="form__switch">
                    <button :class="{ active: paymentMethod === 'cash' }" @click="paymentMethod = 'cash'">Готівка</button>
                    <button :class="{ active: paymentMethod === 'card' }" @click="paymentMethod = 'card'">Картка</button>
                </div>
            </div>

            <!-- итог -->
            <div class="form__footer">
                <span class="form__total">До сплати:</span>
                <span class="form__amount">{{ totalAmount }} грн</span>
            </div>

            <button class="form__submit" @click="createOrder">Створити товар</button>
        </div>
    </div>
</template>

<style scoped>
.map-container {
  height: 400px;
  width: 100%;
  position: relative;
  z-index: 1;
}

.leaflet-marker-icon,
.leaflet-marker-shadow {
  display: block !important;
  opacity: 1 !important;
}
.quality-row {
  display: flex;
  flex-direction: column;
  gap: 8px;
  margin-top: 12px;
}
.quality-label {
  font-weight: 700;
  font-size: 14px;
  color: var(--ink);
}
.quality-select {
  padding: 12px 14px;
  border-radius: var(--r-sm);
  border: 1.5px solid var(--line);
  font-size: 15px;
}
.quality-alert {
  position: relative;
  background-color: var(--danger-bg);
  color: #721c24;
  border: 1px solid #f5c6cb;
  padding: 12px 16px;
  border-radius: var(--r-sm);
  margin-top: 8px;
  font-size: 14px;
  animation: fadeIn 0.3s ease;
}
.quality-alert-text {
  display: block;
  padding-right: 24px;
}
.quality-alert-close {
  position: absolute;
  top: 6px;
  right: 10px;
  background: none;
  border: none;
  font-size: 18px;
  color: #721c24;
  cursor: pointer;
}

@keyframes fadeIn {
  from { opacity: 0; transform: translateY(-5px); }
  to { opacity: 1; transform: translateY(0); }
}
.bottle-section .segmented {
    display: flex;
    gap: 6px;
    background: var(--surface-2);
    padding: 6px;
    border-radius: var(--r-sm);
}
.bottle-section .segmented button {
    flex: 1;
    padding: 11px 12px;
    border: 0;
    border-radius: var(--r-xs);
    background: transparent;
    color: var(--muted);
    font-weight: 600;
    cursor: pointer;
    transition: transform .06s ease, background .2s ease, color .2s ease, box-shadow .2s ease;
}
.bottle-section .segmented button.active {
    background: var(--grad-aqua);
    color: #fff;
    box-shadow: var(--sh-aqua);
}

/* Ряд качества */
.quality-row {
    display: flex;
    align-items: center;
    gap: 10px;
    margin-top: 6px;
}
.quality-badge {
    display: inline-flex;
    align-items: center;
    height: 30px;
    padding: 0 12px;
    font-size: 12px;
    font-weight: 700;
    color: var(--aqua-700);
    background: var(--aqua-50);
    border: 1px solid var(--aqua-200);
    border-radius: var(--r-pill);
}
.quality-select {
    flex: 1;
    text-align: center;
    padding: 0 14px;
    height: 46px;
    border-radius: var(--r-sm);
    border: 1.5px solid var(--line);
    background: var(--surface);
    font-size: 15px;
    color: var(--ink);
    transition: box-shadow .15s ease, border-color .15s ease;
    appearance: none;
}
.quality-select:focus {
    outline: none;
    border-color: var(--aqua-400);
    box-shadow: 0 0 0 4px var(--tap);
}

/* Инфо-чип для покупки */
.info-chip {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    margin-top: 8px;
    padding: 8px 14px;
    font-size: 13px;
    color: var(--success);
    background: var(--success-bg);
    border-radius: var(--r-pill);
}
.info-chip .chip-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--success);
}

/* Плавное появление */
.fade-slide-enter-active,
.fade-slide-leave-active {
    transition: opacity .18s ease, transform .18s ease;
}
.fade-slide-enter-from,
.fade-slide-leave-to {
    opacity: 0;
    transform: translateY(-4px);
}

.form__switch {
    display: flex;
    gap: 8px;
    flex-wrap: wrap;
}
.form__switch button {
    flex: 1;
    min-width: 92px;
    padding: 12px 10px;
    border-radius: var(--r-sm);
    background: var(--surface-2);
    border: 1.5px solid transparent;
    font-size: 14px;
    font-weight: 600;
    color: var(--muted);
    cursor: pointer;
    transition: all 0.2s var(--ease);
}
.form__switch button.active {
    background: var(--aqua-50);
    color: var(--aqua-700);
    border-color: var(--aqua-300);
}

.form {
    min-height: 100vh;
    max-width: var(--maxw);
    margin: 0 auto;
    background: var(--bg);
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding: 96px 14px 24px;
    position: relative;
}
.form__bg {
    position: absolute;
    top: 0; left: 0;
    width: 100%;
    height: 150px;
    background: var(--grad-hero);
    border-radius: 0 0 28px 28px;
    z-index: 0;
}
.form__card {
    background: var(--surface);
    border-radius: var(--r-xl);
    padding: 24px 20px;
    width: 100%;
    max-width: 420px;
    box-shadow: var(--sh-md);
    z-index: 1;
    position: relative;
}
.form__title {
    text-align: center;
    font-size: 22px;
    font-weight: 800;
    line-height: 1.2;
    margin-bottom: 4px;
    color: var(--ink);
}
.form__subtitle {
    text-align: center;
    color: var(--muted);
    margin-bottom: 22px;
    font-weight: 600;
}
.form__group {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 22px;
}
.form__group label {
    font-size: 14px;
    font-weight: 700;
    color: var(--ink);
}
.form__group input,
.form__group select {
    padding: 14px 16px;
    font-size: 16px;
    border: 1.5px solid var(--line);
    border-radius: var(--r-sm);
    outline: none;
    background: var(--surface);
    color: var(--ink);
    transition: border-color .18s var(--ease), box-shadow .18s var(--ease), background .18s;
}
.form__group input:focus,
.form__group select:focus {
    border-color: var(--aqua-400);
    box-shadow: 0 0 0 4px var(--tap);
    background: var(--aqua-50);
}
.form__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 18px;
    padding: 16px 18px;
    background: var(--aqua-50);
    border-radius: var(--r-md);
}
.form__total {
    font-size: 15px;
    font-weight: 600;
    color: var(--ink-2);
}
.form__amount {
    font-size: 22px;
    font-weight: 800;
    color: var(--aqua-700);
}
.form__submit {
    width: 100%;
    min-height: 54px;
    padding: 15px;
    font-size: 16px;
    font-weight: 700;
    background: var(--grad-aqua);
    color: white;
    border: none;
    border-radius: var(--r-md);
    cursor: pointer;
    box-shadow: var(--sh-aqua);
    transition: transform .15s var(--ease);
}
.form__submit:active { transform: scale(.98); }

/* ручной выбор */
.manual-btn {
    margin-top: -4px;
    margin-bottom: 8px;
    padding: 11px 14px;
    font-size: 14px;
    font-weight: 600;
    border: 1.5px solid var(--aqua-300);
    background: var(--aqua-50);
    color: var(--aqua-700);
    border-radius: var(--r-sm);
    cursor: pointer;
}
.manual-btn:active { transform: scale(.98); }
.manual-badge {
    margin-top: -6px;
    margin-bottom: 8px;
    padding: 10px 14px;
    font-size: 14px;
    border: 1px solid var(--line);
    background: var(--surface-2);
    color: var(--ink-2);
    border-radius: var(--r-sm);
    user-select: none;
    pointer-events: none;
}
.geo-warning {
    background: var(--warn-bg);
    border: 1px solid #fcd34d;
    padding: 12px;
    border-radius: var(--r-sm);
    font-size: 14px;
    color: #92400e;
    margin-bottom: 10px;
}
.map-container {
    height: 250px;
    margin-top: 10px;
    border-radius: var(--r-sm);
    overflow: hidden;
}
.info-note {
    font-size: 13px;
    color: var(--success);
    background: var(--success-bg);
    padding: 10px 12px;
    border-radius: var(--r-sm);
}
</style>
