<script setup>
import { ref, computed, watchEffect, nextTick, onBeforeUnmount } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const route = useRoute()
const router = useRouter()

// === Имя товара из параметра маршрута ===
const productName = computed(() => {
    return decodeURIComponent(route.params.productId || 'Срібна вода, 19л')
})

// === Код типа воды: 'silver' | 'deep' (иначе null) ===
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

// === Цены по коду типа воды ===
const WATER_TYPE_PRICES = {
    silver: 120,
    deep: 130,
}

// --- form state
const address = ref('')
const quantity = ref('')
const bottleOption = ref('own')
const timeOption = ref('now')
const customTime = ref('')
const paymentMethod = ref('cash')

// новая опция
const deliveryOption = ref('home') // home | entrance | coffee

// --- карта / ручной выбор локации
const manualMode = ref(false) // как только включим — назад нельзя
const mapRef = ref(null)
const map = ref(null)
const marker = ref(null)
const lat = ref(null)
const lng = ref(null)

// --- базовая цена от waterType
const basePrice = computed(() => WATER_TYPE_PRICES[waterType.value] ?? 120)

// --- расчёт суммы
const totalAmount = computed(() => {
    const qty = parseInt(quantity.value, 10)
    if (isNaN(qty)) return 0

    if (deliveryOption.value === 'coffee') {
        // спец-тариф для кав'ярні
        return qty >= 5 ? qty * 70 : 0
    }

    const price = basePrice.value
    if (deliveryOption.value === 'entrance') return Math.round(qty * price * 0.8)
    return qty * price
})

// --- валидация адреса через Nominatim
const validateAddress = async (addr) => {
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addr)}`
    const response = await fetch(url, {
        headers: { 'User-Agent': 'AquaTaxi (support@aquataxi.example)' },
    })
    const data = await response.json()
    return data.length ? data[0] : null
}

// --- уничтожение карты
const destroyMap = () => {
    if (map.value) {
        map.value.off()
        map.value.remove()
        map.value = null
    }
    marker.value = null
}

// Чистим карту при размонтировании компонента
onBeforeUnmount(() => {
    destroyMap()
})

// --- включение ручного режима (только один раз, без обратного выключения)
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

        if (marker.value) marker.value.setLatLng(e.latlng)
        else marker.value = L.marker(e.latlng).addTo(map.value)
    })
}

// Подстраховка на случай задержки рендера
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
                else marker.value = L.marker(e.latlng).addTo(map.value)
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

        // проверка для кав'ярні
        if (deliveryOption.value === 'coffee' && quantity.value < 5) {
            alert('❌ Мінімальне замовлення для кав’ярні — 5 бутлів')
            return
        }

        // не отправляем заказ с неизвестным типом воды
        if (!waterType.value) {
            alert('❌ Невідомий тип води. Оновіть сторінку або виберіть товар повторно.')
            return
        }

        const token = localStorage.getItem('user_token')

        const payload = {
            product_name: productName.value, // полное название (например, "Срібна вода, 19л")
            water_type: waterType.value,     // 'silver' | 'deep'
            quantity: Number(quantity.value),
            bottle_option: bottleOption.value,
            delivery_time_type: timeOption.value,
            custom_time: customTime.value || null,
            payment_method: paymentMethod.value,
            total_price: totalAmount.value,
            delivery_option: deliveryOption.value,
            lat: manualMode.value ? lat.value : Number(result?.lat),
            lng: manualMode.value ? lng.value : Number(result?.lon),
            ...( !manualMode.value && address.value.trim()
                    ? { address: address.value.trim() }
                    : {}
            ),
        }

        await axios.post('/api/orders', payload, {
            headers: { Authorization: `Bearer ${token}` },
        })

        router.push({
            name: 'orders',
            query: {
                showPopup: true,
                product: productName.value,
                quantity: quantity.value,
                time: timeOption.value === 'custom' ? customTime.value : 'Найближчий час',
            },
        })
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errs = error.response.data?.errors || {}
            const msg =
                Object.values(errs).flat().join('\n') ||
                error.response.data?.message ||
                'Validation error'
            alert('❌ ' + msg)
            return
        }
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
                <input type="text" placeholder="Ваша адреса" v-model="address" />

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
                        В квартиру
                    </button>
                    <button :class="{ active: deliveryOption === 'entrance' }" @click="deliveryOption = 'entrance'">
                        Під під’їзд (-20%)
                    </button>
                    <button :class="{ active: deliveryOption === 'coffee' }" @click="deliveryOption = 'coffee'">
                        Кав’ярня (від 5 бутлів)
                    </button>
                </div>
            </div>

            <!-- бутлі -->
            <div class="form__group">
                <label>Бутелі</label>
                <div class="form__switch">
                    <button :class="{ active: bottleOption === 'own' }" @click="bottleOption = 'own'">Свої бутелі</button>
                    <button :class="{ active: bottleOption === 'buy' }" @click="bottleOption = 'buy'">Придбати бутелі</button>
                </div>
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
.form__switch {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}
.form__switch button {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    background: #f1f1f1;
    border: none;
    font-size: 15px;
    font-weight: 500;
    color: #555;
    cursor: pointer;
    transition: all 0.2s ease;
}
.form__switch button.active {
    background: #007bff;
    color: white;
}

.form {
    min-height: 100vh;
    background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 60%);
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding-top: 140px;
    position: relative;
}

.form__bg {
    position: absolute;
    top: 30px;
    width: 100%;
    height: 140px;
    background: url('@/assets/city.png') no-repeat center;
    background-size: cover;
    z-index: 0;
}

.form__card {
    background: #fff;
    border-radius: 20px;
    padding: 24px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    z-index: 1;
    position: relative;
}

.form__title {
    text-align: center;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.3;
    margin-bottom: 4px;
}

.form__subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 20px;
}

.form__group {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.form__group label {
    font-size: 14px;
    font-weight: 600;
    color: #444;
}

.form__group input,
.form__group select {
    padding: 14px;
    font-size: 15px;
    border: 1px solid #ccc;
    border-radius: 12px;
    outline: none;
    background: #f9f9f9;
}

.form__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.form__total {
    font-size: 16px;
    font-weight: 600;
}

.form__amount {
    font-size: 18px;
    font-weight: 700;
}

.form__submit {
    width: 100%;
    padding: 14px;
    font-size: 16px;
    font-weight: 600;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}

/* ручной выбор */
.manual-btn {
    margin-top: -8px;
    margin-bottom: 8px;
    padding: 8px 12px;
    font-size: 14px;
    border: 1px solid #007bff;
    background: #fff;
    color: #007bff;
    border-radius: 8px;
    cursor: pointer;
}

.manual-badge {
    margin-top: -6px;
    margin-bottom: 8px;
    padding: 8px 12px;
    font-size: 14px;
    border: 1px solid #cbd5e1;
    background: #f8fafc;
    color: #334155;
    border-radius: 8px;
    user-select: none;
    pointer-events: none; /* специально — чтобы не кликалось */
}

.geo-warning {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    padding: 10px;
    border-radius: 8px;
    font-size: 14px;
    color: #92400e;
    margin-bottom: 10px;
}

.map-container {
    height: 250px;
    margin-top: 10px;
    border-radius: 8px;
    overflow: hidden;
}
</style>
