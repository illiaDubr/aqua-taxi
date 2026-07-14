<template>
    <div class="map-page">
        <!-- КАРТА -->
        <div ref="mapContainer" class="map-container"></div>

        <!-- ПЛАВАЮЩИЕ КНОПКИ -->
        <div class="map-fabs">
            <button class="fab fab--left" @click="router.back()" title="Назад">⬅</button>
            <button class="fab fab--right" @click="toggleOrders" :aria-expanded="showOrders" title="Мої замовлення">📦</button>
        </div>

        <!-- МОДАЛКА СОЗДАНИЯ ЗАКАЗА -->
        <FactoryOrderModal
            v-if="showModal && selectedFactory"
            :factory-id="selectedFactory.id"
            :water-type="selectedFactory.water_types"
            :on-close="handleCloseFactoryModal"
        />

        <!-- ДРОУЭР: МОИ ЗАКАЗЫ -->
        <aside class="drawer" :class="{ open: showOrders }" role="dialog" aria-modal="true">
            <header class="drawer__head">
                <h3>Мої замовлення</h3>
                <button class="drawer__close" @click="toggleOrders">✕</button>
            </header>

            <div class="drawer__body">
                <div v-if="loadingOrders" class="muted">Завантаження…</div>
                <div v-else-if="!driverOrders.length" class="muted">Немає відправлених заявок.</div>

                <ul v-else class="orders">
                    <li v-for="o in driverOrders" :key="o.id" class="order">
                        <div class="order__row order__row--top">
                            <div class="factory-head">
                                <b class="factory-name">{{ o.factory?.name ?? 'Фабрика' }}</b>
                                <div v-if="factoryPhone(o)" class="factory-sub">
                                    📞 {{ factoryPhone(o) }}
                                </div>
                                <div v-if="factorySite(o)" class="factory-sub">
                                    🔗 <a :href="normalizeUrl(factorySite(o))" target="_blank" rel="noopener">{{ shortUrl(factorySite(o)) }}</a>
                                </div>
                            </div>
                            <span class="status">{{ statusLabel(o.status) }}</span>
                        </div>

                        <div class="order__row">
                            <span class="lbl">Тип:</span>
                            <span>{{ waterLabel(o) }}</span>
                        </div>

                        <div class="order__row">
                            <span class="lbl">К-сть:</span>
                            <span>{{ o.quantity ?? 0 }} бут.</span>
                        </div>

                        <div class="order__row">
                            <span class="lbl">Час:</span>
                            <span>{{ fmtDate(o.created_at) }}</span>
                        </div>

                        <div v-if="o.comment" class="order__comment">{{ o.comment }}</div>
                    </li>
                </ul>
            </div>

            <footer class="drawer__foot">
                <button class="btn" @click="fetchDriverOrders" :disabled="loadingOrders">Оновити</button>
            </footer>
        </aside>

        <!-- ЗАТЕМНЕНИЕ -->
        <div class="scrim" :class="{ show: showOrders }" @click="toggleOrders"></div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount, nextTick } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import FactoryOrderModal from '@/views/FactoryOrderModal.vue'
import markerIcon from 'leaflet/dist/images/marker-icon.png'
import markerShadow from 'leaflet/dist/images/marker-shadow.png'

const icon = L.icon({
  iconUrl: markerIcon,
  shadowUrl: markerShadow,
  iconSize: [25, 41],
  iconAnchor: [12, 41],
  popupAnchor: [1, -34],
})
const router = useRouter()

/** ===== API ===== */
const FACTORIES_COORDS_URL = '/api/factories/coordinates'
const DRIVER_ORDERS_URL    = '/api/factory-orders/mine' // правильный эндпоинт

/** ===== Карта ===== */
const mapContainer = ref(null)
let map = null
let markersLayer = null

const factories = ref([])
const selectedFactory = ref(null)
const showModal = ref(false)

const initMap = () => {
    if (map || !mapContainer.value) return
    map = L.map(mapContainer.value, {
        zoomControl: true,
        attributionControl: true
    }).setView([50.45, 30.52], 12)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 20,
        attribution: '&copy; OpenStreetMap contributors'
    }).addTo(map)

    markersLayer = L.layerGroup().addTo(map)
}

const fetchFactories = async () => {
    try {
        const { data } = await axios.get(FACTORIES_COORDS_URL)
        factories.value = Array.isArray(data) ? data : []
        renderMarkers()
    } catch (e) {
        console.error('Помилка завантаження виробників', e)
    }
}

const renderMarkers = () => {
    if (!markersLayer) return
    markersLayer.clearLayers()

    factories.value.forEach(f => {
        const lat = Number(f.lat ?? f.latitude)
        const lng = Number(f.lng ?? f.longitude)
        if (!Number.isFinite(lat) || !Number.isFinite(lng)) return

        const m = L.marker([lat, lng], { icon }).addTo(markersLayer).bindPopup(f.name ?? 'Виробник')
        m.on('click', () => {
            selectedFactory.value = f
            showModal.value = true
        })
    })
}

const handleCloseFactoryModal = () => {
    showModal.value = false
    selectedFactory.value = null
}

/** ===== Мои заказы (drawer) ===== */
const showOrders = ref(false)
const driverOrders = ref([])
const loadingOrders = ref(false)
let pollTimer = null

const bearer = () => {
    return localStorage.getItem('driver_token') || sessionStorage.getItem('driver_token') || ''
}

const fetchDriverOrders = async () => {
    loadingOrders.value = true
    try {
        const { data } = await axios.get(DRIVER_ORDERS_URL, {
            headers: { Authorization: `Bearer ${bearer()}` },
            // params: { status: 'new,in_progress,completed' } // при желании
        })
        driverOrders.value = Array.isArray(data?.orders) ? data.orders : []
    } catch (e) {
        console.error('Не вдалося завантажити замовлення', e)
        driverOrders.value = []
    } finally {
        loadingOrders.value = false
    }
}

const toggleOrders = async () => {
    showOrders.value = !showOrders.value
    if (showOrders.value) {
        await fetchDriverOrders()
        pollTimer = setInterval(fetchDriverOrders, 60_000)
    } else if (pollTimer) {
        clearInterval(pollTimer)
        pollTimer = null
    }
}

/** ===== Отображение ===== */
// Маппинг кодов → названий
const WATER_DICTIONARY = {
    silver: 'Срібна',
    deep: 'Глибокого очищення',
}

const waterLabel = (o) => {
    const raw = (o?.water_type ?? '').toString().trim()
    if (!raw) return '—'

    const lc = raw.toLowerCase()

    // 1) Если фабрика прислана с water_types — попробуем найти там
    const types = o?.factory?.water_types
    if (types && Array.isArray(types)) {
        const hit = types.find(t => {
            const code = (t.code ?? '').toString().toLowerCase()
            const name = (t.name ?? '').toString().toLowerCase()
            return lc === code || lc === name
        })
        if (hit?.name) return hit.name
    }

    // 2) Наш словарь (быстро и просто)
    if (WATER_DICTIONARY[lc]) return WATER_DICTIONARY[lc]

    // 3) Фолбэк — красивая капитализация исходной строки
    return lc.charAt(0).toUpperCase() + lc.slice(1)
}

// Детали фабрики
const factoryPhone = (o) => o?.factory?.phone || o?.factory?.contact_phone || null
const factorySite  = (o) => o?.factory?.website || o?.factory?.site || o?.factory?.url || null

const normalizeUrl = (u) => {
    if (!u) return '#'
    try {
        const hasProto = /^https?:\/\//i.test(u)
        return hasProto ? u : `https://${u}`
    } catch { return u }
}
const shortUrl = (u) => {
    if (!u) return ''
    const clean = u.replace(/^https?:\/\//i, '').replace(/\/+$/,'')
    return clean.length > 40 ? clean.slice(0, 40) + '…' : clean
}

const statusLabel = (s) => ({
    new: 'Нове',
    pending: 'В очікуванні',
    in_progress: 'Виконується',
    completed: 'Завершено',
    canceled: 'Скасовано'
}[s] ?? (s || '—'))

const fmtDate = (iso) => {
    if (!iso) return '—'
    try {
        return new Date(iso).toLocaleString('uk-UA', {
            day: '2-digit', month: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit'
        })
    } catch { return iso }
}

/** ===== Жизненный цикл ===== */
const onEsc = (e) => {
    if (e.key === 'Escape') {
        if (showModal.value) handleCloseFactoryModal()
        else if (showOrders.value) showOrders.value = false
    }
}

onMounted(async () => {
    await nextTick()
    if (!mapContainer.value) return
    initMap()
    await fetchFactories()
    window.addEventListener('keydown', onEsc)
})

onBeforeUnmount(() => {
    window.removeEventListener('keydown', onEsc)
    if (pollTimer) clearInterval(pollTimer)
    if (map) map.remove()
})
</script>

<style scoped>
.map-page{position:relative;width:100%;height:100%;}
.map-container{width:100%;height:100vh;}

/* FABs */
.map-fabs{position:fixed;top:calc(14px + env(safe-area-inset-top,0px));left:0;right:0;z-index:10000;pointer-events:none;max-width:var(--maxw);margin:0 auto;}
.fab{pointer-events:auto;position:absolute;width:48px;height:48px;border-radius:var(--r-md);border:none;
    background:rgba(255,255,255,.96);backdrop-filter:blur(8px);box-shadow:var(--sh-md);cursor:pointer;
    display:flex;align-items:center;justify-content:center;font-size:19px;color:var(--ink);transition:transform .15s var(--ease);}
.fab:active{transform:scale(.94);}
.fab--left{left:14px;}
.fab--right{right:14px;background:var(--grad-aqua);color:#fff;box-shadow:var(--sh-aqua);}
@media (max-width:520px){.fab{width:46px;height:46px;font-size:17px;}}

/* Drawer */
.drawer{position:fixed;top:0;right:0;height:100dvh;width:min(460px,92vw);background:var(--bg);
    transform:translateX(100%);transition:transform .28s var(--ease);z-index:9999;display:flex;flex-direction:column;
    border-radius:24px 0 0 24px;box-shadow:var(--sh-lg);overflow:hidden;}
.drawer.open{transform:translateX(0);}
.drawer__head{display:flex;align-items:center;justify-content:space-between;padding:18px 18px;background:var(--surface);border-bottom:1px solid var(--line-2);}
.drawer__head h3{font-size:18px;}
.drawer__close{border:0;background:var(--surface-2);width:38px;height:38px;border-radius:var(--r-pill);font-size:16px;cursor:pointer;color:var(--ink);}
.drawer__body{flex:1;overflow:auto;padding:14px 16px;}
.drawer__foot{padding:14px 16px;border-top:1px solid var(--line-2);display:flex;justify-content:flex-end;background:var(--surface);}
.btn{border:none;background:var(--aqua-50);color:var(--aqua-700);border-radius:var(--r-sm);padding:11px 18px;font-weight:700;cursor:pointer;}
.btn:disabled{opacity:.55;}
.muted{color:var(--muted);text-align:center;padding:30px 0;}
.orders{list-style:none;margin:0;padding:0;display:grid;gap:12px;}
.order{border:1px solid var(--line-2);background:var(--surface);border-radius:var(--r-lg);padding:14px;box-shadow:var(--sh-xs);}
.order__row{display:flex;justify-content:space-between;gap:8px;font-size:13.5px;margin:6px 0;color:var(--ink-2);}
.order__row--top{align-items:flex-start;}
.lbl{color:var(--muted);}
.factory-head{display:flex;flex-direction:column;gap:2px;}
.factory-name{font-size:14.5px;color:var(--ink);}
.factory-sub{font-size:12px;color:var(--muted);}
.factory-sub a{color:var(--aqua-600);}
.order__comment{margin-top:8px;font-size:13px;border-left:3px solid var(--aqua-200);padding-left:10px;color:var(--ink-2);}
.status{font-weight:700;font-size:12px;color:var(--aqua-700);background:var(--aqua-50);padding:4px 10px;border-radius:var(--r-pill);}
.scrim{position:fixed;inset:0;background:rgba(11,31,51,.45);backdrop-filter:blur(2px);opacity:0;pointer-events:none;transition:opacity .2s ease;z-index:9998;}
.scrim.show{opacity:1;pointer-events:auto;}
</style>
