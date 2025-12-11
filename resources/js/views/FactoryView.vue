<template>
    <div class="producer-orders">
        <!-- Верхняя панель -->
        <div class="producer-orders__topbar">
            <div class="status-toggle" :class="{ offline: !isOnline }" @click="toggleStatus">
                <span class="label">{{ isOnline ? 'Онлайн' : 'Оффлайн' }}</span>
                <div class="toggle-circle"></div>
            </div>
            <div class="balance-box">
                <span class="amount">{{ (balance ?? 0).toFixed(2) }}грн</span>
                <button class="plus" @click="topUp">＋</button>
            </div>
            <button class="logout-btn" @click="logoutFactory">Вийти</button>
        </div>

        <div class="bg">
            <!-- Табы -->
            <div class="tabs">
                <button :class="{ active: currentTab === 'active' }" @click="switchTab('active')">
                    Активні замовлення
                </button>
                <button :class="{ active: currentTab === 'new' }" @click="switchTab('new')">
                    Нові замовлення
                </button>
            </div>

            <!-- Алерт -->
            <div class="alert">
                <p>Для прийняття замовлень потрібно ввімкнути повідомлення від Aqua Taxi</p>
            </div>

            <!-- Активні -->
            <div class="content" v-if="currentTab === 'active'">
                <div class="toolbar">
                    <button class="topup-button" @click="manualRefreshActive" :disabled="loadingActive">
                        {{ loadingActive ? 'Оновлення…' : 'Оновити список' }}
                    </button>
                    <small class="muted" v-if="lastSeenActive">
                        Останнє оновлення: {{ fmtTime(lastSeenActive) }}
                    </small>
                </div>

                <div v-if="!ordersActive.length && !loadingActive" class="empty">
                    Активних замовлень немає
                </div>

                <div v-if="errorActive" class="error">{{ errorActive }}</div>

                <div v-for="o in ordersActive" :key="o.id" class="order-card">
                    <div class="order-meta">
                        <div class="order-title">
                            <b>#{{ o.id }}</b> • {{ waterName(o.water_type, o.factory?.water_types) }} • {{ o.quantity }} бут
                        </div>
                        <div class="order-sub">
                            {{ money(o.total_price) }} • Ціна/бут: {{ money(o.price_per_bottle) }}
                        </div>
                        <div class="order-sub">
                            Статус: <span class="badge badge--active">{{ o.status }}</span>
                            <span v-if="o.accepted_at" class="subtle"> • Прийнято: {{ fmtTime(o.accepted_at) }}</span>
                        </div>
                    </div>
                    <div class="order-actions">
                        <button
                            class="btn btn-complete"
                            :disabled="!!busy[o.id] || !isOnline"
                            @click="complete(o.id)"
                        >
                            {{ busy[o.id] ? '...' : 'Виконано' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Нові -->
            <div class="content" v-else-if="currentTab === 'new'">
                <div class="toolbar">
                    <button class="topup-button" @click="manualRefreshNew" :disabled="loadingNew">
                        {{ loadingNew ? 'Оновлення…' : 'Оновити список' }}
                    </button>
                    <small class="muted" v-if="lastSeenNew">Останнє оновлення: {{ fmtTime(lastSeenNew) }}</small>
                </div>

                <div v-if="!ordersNew.length && !loadingNew" class="empty">
                    Нових замовлень поки немає
                </div>

                <div v-if="errorNew" class="error">{{ errorNew }}</div>

                <div v-for="o in ordersNew" :key="o.id" class="order-card">
                    <div class="order-meta">
                        <div class="order-title">
                            <b>#{{ o.id }}</b> • {{ waterName(o.water_type, o.factory?.water_types) }} • {{ o.quantity }} бут
                        </div>
                        <div class="order-sub">
                            {{ money(o.total_price) }} • Ціна/бут: {{ money(o.price_per_bottle) }}
                        </div>
                        <div class="order-sub">
                            Статус: <span class="badge">{{ o.status }}</span>
                            <span class="subtle"> • Створено: {{ fmtTime(o.created_at) }}</span>
                        </div>
                    </div>
                    <div class="order-actions">
                        <button
                            class="btn btn-accept"
                            :disabled="!!busy[o.id] || !isOnline"
                            @click="accept(o.id)"
                        >
                            {{ busy[o.id] ? '...' : 'Прийняти' }}
                        </button>
                    </div>
                </div>
            </div>

            <!-- Лоадеры -->
            <div
                v-if="(currentTab==='new' && loadingNew) || (currentTab==='active' && loadingActive)"
                class="loader"
            >
                Завантаження…
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

/** --- State --- */
const currentTab     = ref('active')
const isOnline       = ref(true)
const balance        = ref(0)

const ordersNew      = ref([])
const ordersActive   = ref([])

const loadingNew     = ref(false)
const loadingActive  = ref(false)
const errorNew       = ref('')
const errorActive    = ref('')

const lastSeenNew    = ref(null)
const lastSeenActive = ref(null)

const POLL_MS = 60_000
const pollTimer = ref(null)
const busy      = ref({})

let abortCtl = null

/** --- Auth helpers --- */
function getFactoryToken () {
    return localStorage.getItem('factory_token') || localStorage.getItem('token') || ''
}
function cfg(extra = {}) {
    const t = getFactoryToken()
    return {
        headers: t ? { Authorization: `Bearer ${t}` } : {},
        ...extra,
    }
}

/** --- UI helpers --- */
function toggleStatus() { isOnline.value = !isOnline.value }

async function topUp() {
    const amount = prompt("Введіть суму поповнення (грн):")
    if (!amount) return
    const num = Number(amount)
    if (isNaN(num) || num <= 0) {
        alert("Некоректна сума")
        return
    }

    try {
        const { data } = await axios.post('/api/factory/payment/initiate', { amount: num }, cfg())
        console.log('payment initiate response', data)
        if (!data?.payment_url) {
            alert("Не вдалося створити платіж")
            return
        }
        window.location.href = data.payment_url
    } catch (err) {
        console.error(err)
        alert(err?.response?.data?.message || "Помилка створення платежу")
    }
}

function money(v) { const n = Number(v ?? 0); return `${n.toFixed(2)} грн` }
function fmtTime(iso) { try { return new Date(iso).toLocaleString('uk-UA') } catch { return '' } }

function waterName(code, types) {
    const raw = (code ?? '').toString().trim().toLowerCase()
    if (!raw) return '—'
    if (Array.isArray(types)) {
        const hit = types.find(t => {
            const c = (t.code ?? '').toString().toLowerCase()
            const n = (t.name ?? '').toString().toLowerCase()
            return raw === c || raw === n
        })
        if (hit?.name) return hit.name
    }
    if (raw === 'silver') return 'Срібна'
    if (raw === 'deep')   return 'Глибокого очищення'
    return raw.charAt(0).toUpperCase() + raw.slice(1)
}

/** --- Merge без дублей --- */
function mergeOrders(targetRef, incoming) {
    const cur = targetRef.value
    const map = new Map(cur.map(o => [o.id, o]))
    for (const o of (incoming || [])) {
        const prev = map.get(o.id) || {}
        map.set(o.id, { ...prev, ...o })
    }
    targetRef.value = Array.from(map.values()).sort((a,b) => b.id - a.id)
}

/** --- Abort helpers --- */
function cancelInFlight() {
    if (abortCtl) abortCtl.abort()
    abortCtl = null
}
async function withAbort(getter) {
    cancelInFlight()
    abortCtl = new AbortController()
    try { return await getter({ signal: abortCtl.signal }) } finally {}
}

/** --- Errors --- */
function handleAuthError(e, where) {
    const st = e?.response?.status
    if (st === 401 || st === 403) {
        const msg = e?.response?.data?.message || 'Only factories can view this list'
        if (where === 'new')    errorNew.value    = msg
        if (where === 'active') errorActive.value = msg
        stopPolling()
        return true
    }
    return false
}

/** --- API --- */
async function pollNew() {
    loadingNew.value = true
    errorNew.value   = ''
    try {
        const params = { status: 'new' }
        if (lastSeenNew.value) params.updated_since = lastSeenNew.value

        const { data } = await withAbort(({ signal }) =>
            axios.get('/api/factory-orders', cfg({ params, signal }))
        )
        mergeOrders(ordersNew, data?.orders)
        lastSeenNew.value = data?.meta?.server_time || new Date().toISOString()
    } catch (e) {
        if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') {
            if (!handleAuthError(e, 'new')) errorNew.value = e?.response?.data?.message || 'Помилка завантаження'
        }
    } finally { loadingNew.value = false }
}

async function pollActive() {
    loadingActive.value = true
    errorActive.value   = ''
    try {
        const params = { status: 'accepted' }
        if (lastSeenActive.value) params.updated_since = lastSeenActive.value

        const { data } = await withAbort(({ signal }) =>
            axios.get('/api/factory-orders', cfg({ params, signal }))
        )
        mergeOrders(ordersActive, data?.orders)
        lastSeenActive.value = data?.meta?.server_time || new Date().toISOString()
    } catch (e) {
        if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') {
            if (!handleAuthError(e, 'active')) errorActive.value = e?.response?.data?.message || 'Помилка завантаження'
        }
    } finally { loadingActive.value = false }
}

async function accept(id) {
    busy.value[id] = true
    try {
        await axios.post(`/api/factory-orders/${id}/accept`, {}, cfg())
        const idx = ordersNew.value.findIndex(x => x.id === id)
        if (idx !== -1) {
            const o = { ...ordersNew.value[idx], status: 'accepted', accepted_at: new Date().toISOString() }
            ordersNew.value.splice(idx, 1)
            ordersActive.value.unshift(o)
        } else await pollActive()
    } catch (e) {
        if (!handleAuthError(e, 'new')) {
            errorNew.value = e?.response?.data?.message || 'Не вдалося прийняти замовлення'
            await Promise.allSettled([pollNew(), pollActive()])
        }
    } finally { delete busy.value[id] }
}

async function complete(id) {
    busy.value[id] = true
    try {
        await axios.post(`/api/factory-orders/${id}/complete`, {}, cfg())
        ordersActive.value = ordersActive.value.filter(x => x.id !== id)
        await pollActive()
    } catch (e) {
        if (!handleAuthError(e, 'active')) errorActive.value = e?.response?.data?.message || 'Не вдалося завершити замовлення'
    } finally { delete busy.value[id] }
}

/** --- Polling --- */
function refreshNow() {
    if (currentTab.value === 'new') pollNew()
    else if (currentTab.value === 'active') pollActive()
}

function startPolling(fn) {
    stopPolling()
    if (!getFactoryToken()) { errorNew.value = errorActive.value = 'Потрібен вхід як виробник'; return }
    fn()
    pollTimer.value = setInterval(() => {
        if (document.visibilityState === 'visible' && navigator.onLine) fn()
    }, POLL_MS)
}

function stopPolling() { if (pollTimer.value) { clearInterval(pollTimer.value); pollTimer.value = null } cancelInFlight() }
function manualRefreshNew() { pollNew() }
function manualRefreshActive() { pollActive() }

function switchTab(tab) {
    currentTab.value = tab
    if (tab === 'new') startPolling(pollNew)
    else if (tab === 'active') startPolling(pollActive)
    else stopPolling()
}
    async function loadBalance() {
    try {
        const { data } = await axios.get('/api/factory/balance', cfg())
        balance.value = Number(data?.balance ?? 0)
    } catch (err) {
        console.error("Balance load error", err)
    }
}

const confirmModal = ref({ show: false, order: null, fee: 0 })


function askAccept(o) {
const fee = Number(o.total_price) * 0.1
confirmModal.value = {
show: true,
order: o,
fee: fee.toFixed(2)
}
}
/** --- Logout --- */
function logoutFactory() {
    const token = getFactoryToken()
    if (!token) { window.location.href = 'https://example.com/'; return }
    axios.post('/api/logout', {}, { headers: { Authorization: `Bearer ${token}` } })
        .catch(err => console.error(err))
        .finally(() => {
            localStorage.removeItem('factory_token')
            localStorage.removeItem('token')
            window.location.href = 'https://example.com/'
        })
}

/** --- Events --- */
const handleVisibilityChange = () => {
    if (document.visibilityState === 'visible') { refreshNow(); startPolling(currentTab.value==='new'?pollNew:pollActive) }
    else stopPolling()
}
const handleOnline  = () => startPolling(currentTab.value==='new'?pollNew:pollActive)
const handleOffline = () => stopPolling()
const handleExternalRefresh = () => refreshNow()

onMounted(() => {
    loadBalance()
    switchTab('active')
    document.addEventListener('visibilitychange', handleVisibilityChange)
    window.addEventListener('focus', loadBalance)
    window.addEventListener('online',  handleOnline)
    window.addEventListener('offline', handleOffline)
    window.addEventListener('factory-orders:refresh', handleExternalRefresh)
})
onBeforeUnmount(() => {
    stopPolling()
    window.removeEventListener('focus', loadBalance)
    document.removeEventListener('visibilitychange', handleVisibilityChange)
    window.removeEventListener('online',  handleOnline)
    window.removeEventListener('offline', handleOffline)
    window.removeEventListener('factory-orders:refresh', handleExternalRefresh)
})
</script>

<style scoped>
    .orders-page { padding: 15px; }
.balance-block { margin-bottom: 15px; font-size: 18px; }


.tabs { display: flex; gap: 10px; margin-bottom: 15px; }
.tabs button { padding: 8px 12px; border-radius: 6px; border: none; background: #ddd; }
.tabs .active { background: #4caf50; color: white; }


.order-card { padding: 12px; border-radius: 10px; background: #f1f1f1; margin-bottom: 10px; }
.actions button { padding: 8px 14px; border-radius: 6px; border: none; background: #28a745; color: #fff; }


/* МОДАЛКА */
.modal-overlay {
position: fixed;
inset: 0;
background: rgba(0,0,0,0.5);
display: flex;
align-items: center;
justify-content: center;
z-index: 2000;
}
.modal {
background: #fff;
padding: 20px;
border-radius: 12px;
width: 90%;
max-width: 350px;
text-align: center;
}
.modal-actions {
margin-top: 15px;
display: flex;
justify-content: space-between;
gap: 10px;
}
.btn-cancel,
.btn-confirm {
flex: 1;
padding: 10px;
border-radius: 8px;
border: none;
}
.btn-confirm { background: #28a745; color: white; }
.btn-cancel { background: #ccc; color: black; }
.bg{
    margin: 0 auto;
    background: white;
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-radius: 15px 15px 0 0;
}

.status-toggle {
    display: flex;
    align-items: center;
    background-color: #00c853;
    border-radius: 24px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}
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

.status-toggle.offline { background-color: #9e9e9e; }
.status-toggle .label { font-size: 14px; color: white; }
.status-toggle.offline .label { transform: translateX(10px) scale(0.9); transition: transform 0.3s ease; }
.status-toggle .toggle-circle { width: 12px; height: 12px; background-color: white; border-radius: 50%; transition: transform 0.3s ease; }
.status-toggle.offline .toggle-circle { transform: translateX(-80px) scale(0.9); }

.producer-orders { background: #00aaff; position: relative; z-index: 1; font-family: 'Montserrat', sans-serif; }
.producer-orders__topbar {
    position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center;
    padding: 12px; color: white;
}

.balance-box { display: flex; align-items: center; background-color: white; border-radius: 20px; padding: 4px 10px; color: #333; gap: 4px; }
.balance-box .amount { font-weight: 600; font-size: 14px; }
.balance-box .plus { background: none; border: none; font-size: 18px; cursor: pointer; color: #007BFF; }

.tabs { display: flex; padding: 10px; border-radius: 15px; justify-content: space-around; margin-top: 16px; border-bottom: 2px solid #eee; }
.tabs button { flex: 1; padding: 12px; background: none; border: none; font-size: 15px; font-weight: 500; color: #999; position: relative; }
.tabs .active { color: #000; }
.tabs .active::after { content: ''; position: absolute; bottom: 0; left: 25%; right: 25%; height: 3px; background-color: #2196F3; border-radius: 4px; }

.alert {
    background: #fff4f4; border: 1px solid #ffbcbc; padding: 12px; border-radius: 12px; color: #d32f2f;
    font-size: 14px; margin: 10px; text-align: center;
}

.content { margin-top: 16px; padding: 0 10px 16px; }
.toolbar { display: flex; align-items: center; gap: 12px; justify-content: flex-start; margin: 0 10px 8px; }
.muted { color: #7a7a7a; }

.topup-button { background-color: #2196F3; color: white; padding: 10px 16px; font-size: 14px; border: none; border-radius: 12px; cursor: pointer; }

.empty { padding: 16px; text-align: center; color: #777; }
.error { margin: 10px; color: #c62828; text-align: center; }
.loader { margin: 10px; color: #333; text-align: center; }

.order-card {
    background: #fff; border-radius: 12px; padding: 12px; margin: 10px;
    display: flex; justify-content: space-between; align-items: center;
    border: 1px solid #eee;
}
.order-meta { display: grid; gap: 6px; }
.order-title { font-size: 16px; }
.order-sub { color: #666; font-size: 13px; }
.subtle { color: #9c9c9c; }
.badge { display: inline-block; padding: 2px 8px; background:#ececec; border-radius: 8px; font-size: 12px; }
.badge--active { background:#d6f5e0; }

.order-actions { display:flex; gap:10px; }
.btn { border: none; border-radius: 10px; padding: 8px 12px; cursor: pointer; font-weight: 600; }
.btn-accept { background:#2e7d32; color:#fff; }
.btn-complete { background:#1565c0; color:#fff; }
</style>
