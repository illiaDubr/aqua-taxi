<script setup>
import { ref, computed, watchEffect, nextTick } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const route = useRoute()
const router = useRouter()

const productName = decodeURIComponent(route.params.productId || '')
const pricePerBottle = 120

const address = ref('')
const quantity = ref('')
const bottleOption = ref('own')
const timeOption = ref('now')
const customTime = ref('')
const paymentMethod = ref('cash')

// ручной выбор локации
const manualMode = ref(false)
const mapRef = ref(null)
const map = ref(null)
const marker = ref(null)
const lat = ref(null)
const lng = ref(null)

const totalAmount = computed(() => {
    const qty = parseInt(quantity.value, 10)
    return isNaN(qty) ? 0 : qty * pricePerBottle
})

const validateAddress = async (addr) => {
    const url = `https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(addr)}`
    const response = await fetch(url, {
        headers: { 'User-Agent': 'AquaTaxi (support@aquataxi.example)' } // укажи свой email/домен
    })
    const data = await response.json()
    return data.length ? data[0] : null
}

// инициализация/разрушение карты при показе/скрытии блока
const destroyMap = () => {
    if (map.value) {
        map.value.off()
        map.value.remove()
        map.value = null
    }
    marker.value = null
}

watchEffect(async () => {
    if (manualMode.value && mapRef.value && !map.value) {
        await nextTick()
        map.value = L.map(mapRef.value).setView([50.4501, 30.5234], 13)

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data © OpenStreetMap contributors'
        }).addTo(map.value)

        map.value.on('click', (e) => {
            lat.value = e.latlng.lat
            lng.value = e.latlng.lng

            if (marker.value) marker.value.setLatLng(e.latlng)
            else marker.value = L.marker(e.latlng).addTo(map.value)
        })
    }

    if (!manualMode.value) {
        // при скрытии режима — очищаем карту и координаты
        destroyMap()
        lat.value = null
        lng.value = null
    }
})

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

        const token = localStorage.getItem('user_token')

        // СБОРКА payload: адрес отправляем ТОЛЬКО если не manualMode и поле не пустое
        const payload = {
            quantity: Number(quantity.value),
            bottle_option: bottleOption.value,
            delivery_time_type: timeOption.value,
            custom_time: customTime.value || null,
            payment_method: paymentMethod.value,
            total_price: totalAmount.value, // бэк всё равно пересчитает
            lat: manualMode.value ? lat.value : Number(result?.lat),
            lng: manualMode.value ? lng.value : Number(result?.lon)
        }
        if (!manualMode.value && address.value.trim()) {
            payload.address = address.value.trim()
        }

        await axios.post('/api/orders', payload, {
            headers: { Authorization: `Bearer ${token}` }
        })

        router.push({
            name: 'orders',
            query: {
                showPopup: true,
                product: productName,
                quantity: quantity.value,
                time: timeOption.value === 'custom' ? customTime.value : 'Найближчий час'
            }
        })
    } catch (error) {
        if (axios.isAxiosError(error) && error.response?.status === 422) {
            const errs = error.response.data?.errors || {}
            const msg =
                Object.values(errs).flat().join('\n') ||
                error.response.data?.message ||
                'Validation error'
            alert('❌ ' + msg)
            console.error('422 errors:', errs)
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
            <p class="form__subtitle">Срібна вода, 19л</p>

            <div class="form__group">
                <label>Введіть ваші дані</label>

                <input type="text" placeholder="Ваша адреса" v-model="address" />

                <!-- кнопка включения ручного выбора -->
                <button type="button" class="manual-btn" @click="manualMode = !manualMode">
                    {{ manualMode ? 'Сховати карту' : 'Вибрати на карті' }}
                </button>

                <!-- карта для ручного выбора -->
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

            <div class="form__group">
                <label>Бутелі</label>
                <div class="form__switch">
                    <button :class="{ active: bottleOption === 'own' }" @click="bottleOption = 'own'">Свої бутелі</button>
                    <button :class="{ active: bottleOption === 'buy' }" @click="bottleOption = 'buy'">Придбати бутелі</button>
                </div>
            </div>

            <div class="form__group">
                <label>Час</label>
                <div class="form__switch">
                    <button :class="{ active: timeOption === 'now' }" @click="timeOption = 'now'">Привезти в найближчий час</button>
                    <button :class="{ active: timeOption === 'custom' }" @click="timeOption = 'custom'">Привезти на обраний час</button>
                </div>

                <input v-if="timeOption === 'custom'" type="datetime-local" v-model="customTime" />
            </div>

            <div class="form__group">
                <label>Спосіб оплати</label>
                <div class="form__switch">
                    <button :class="{ active: paymentMethod === 'cash' }" @click="paymentMethod = 'cash'">Готівка</button>
                    <button :class="{ active: paymentMethod === 'card' }" @click="paymentMethod = 'card'">Оплата карткою</button>
                </div>
            </div>

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
