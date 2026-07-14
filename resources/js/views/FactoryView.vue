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
.producer-orders {
    background: var(--grad-hero);
    position: relative; z-index: 1;
    max-width: var(--maxw); margin: 0 auto; min-height: 100vh;
}
.producer-orders__topbar {
    position: relative; z-index: 2; display: flex; justify-content: space-between; align-items: center; gap: 8px;
    padding: calc(14px + env(safe-area-inset-top, 0px)) 14px 18px; color: white;
}

/* Тумблер онлайн/офлайн */
.status-toggle {
    display: flex; align-items: center; justify-content: space-between; gap: 8px;
    background-color: var(--success); border-radius: var(--r-pill); padding: 9px 14px;
    cursor: pointer; transition: background-color .3s ease; min-width: 104px;
}
.status-toggle.offline { background-color: #9aa7b2; }
.status-toggle .label { font-size: 13px; font-weight: 700; color: white; }
.status-toggle .toggle-circle { width: 12px; height: 12px; background-color: white; border-radius: 50%; transition: transform .3s ease; }

.balance-box { display: flex; align-items: center; background: rgba(255,255,255,.94); border-radius: var(--r-pill); padding: 6px 8px 6px 14px; color: var(--ink); gap: 6px; }
.balance-box .amount { font-weight: 700; font-size: 14px; }
.balance-box .plus { background: var(--aqua-50); border: none; width: 28px; height: 28px; border-radius: var(--r-pill); font-size: 18px; cursor: pointer; color: var(--aqua-700); }

.logout-btn {
    background: rgba(255,255,255,.94); color: var(--aqua-700); border: none;
    border-radius: var(--r-pill); padding: 9px 14px; font-size: 13px; font-weight: 700; cursor: pointer;
}
.logout-btn:active { transform: scale(.97); }

/* Лист-контейнер */
.bg {
    margin: 0 auto; background: var(--bg); width: 100%; min-height: 78vh;
    overflow: hidden; border-radius: 26px 26px 0 0; padding-top: 6px;
}

/* Табы (сегмент) */
.tabs { display: flex; gap: 4px; padding: 5px; margin: 14px 14px 0; background: var(--surface); border-radius: var(--r-pill); box-shadow: var(--sh-sm); }
.tabs button { flex: 1; padding: 11px; background: none; border: none; font-size: 14px; font-weight: 600; color: var(--muted); border-radius: var(--r-pill); cursor: pointer; transition: all .2s var(--ease); }
.tabs .active { color: var(--aqua-700); background: var(--aqua-50); }

.alert {
    background: var(--warn-bg); border: 1px solid #fcd9a3; padding: 12px 14px; border-radius: var(--r-md); color: #a15c00;
    font-size: 13.5px; margin: 14px; text-align: center; line-height: 1.45;
}
.alert p { margin: 0; }

.content { margin-top: 6px; padding: 0 14px 16px; }
.toolbar { display: flex; align-items: center; gap: 12px; justify-content: flex-start; margin: 8px 0 10px; flex-wrap: wrap; }
.muted { color: var(--muted); font-size: 12px; }

.topup-button { background: var(--aqua-50); color: var(--aqua-700); padding: 10px 16px; font-size: 13.5px; font-weight: 700; border: none; border-radius: var(--r-pill); cursor: pointer; }
.topup-button:disabled { opacity: .55; }

.empty { padding: 40px 16px; text-align: center; color: var(--muted); }
.error { margin: 10px 0; color: var(--danger); text-align: center; }
.loader { margin: 16px; color: var(--muted); text-align: center; }

.order-card {
    background: var(--surface); border-radius: var(--r-lg); padding: 16px; margin: 12px 0;
    display: flex; justify-content: space-between; align-items: center; gap: 12px;
    border: 1px solid var(--line-2); box-shadow: var(--sh-sm);
}
.order-meta { display: grid; gap: 6px; min-width: 0; }
.order-title { font-size: 15px; color: var(--ink); font-weight: 600; }
.order-sub { color: var(--muted); font-size: 13px; }
.subtle { color: var(--faint); }
.badge { display: inline-block; padding: 3px 10px; background: var(--surface-2); color: var(--ink-2); border-radius: var(--r-pill); font-size: 12px; font-weight: 700; }
.badge--active { background: var(--success-bg); color: var(--success); }

.order-actions { display: flex; gap: 10px; flex: none; }
.btn { border: none; border-radius: var(--r-sm); padding: 11px 16px; cursor: pointer; font-weight: 700; font-size: 13.5px; transition: transform .15s var(--ease); }
.btn:active { transform: scale(.97); }
.btn:disabled { opacity: .5; }
.btn-accept { background: var(--grad-aqua); color: #fff; box-shadow: var(--sh-aqua); }
.btn-complete { background: var(--success); color: #fff; }

/* МОДАЛКА */
.modal-overlay { position: fixed; inset: 0; background: rgba(11,31,51,.45); backdrop-filter: blur(3px); display: flex; align-items: center; justify-content: center; z-index: 2000; padding: 20px; }
.modal { background: #fff; padding: 24px; border-radius: var(--r-lg); width: 100%; max-width: 340px; text-align: center; box-shadow: var(--sh-lg); }
.modal-actions { margin-top: 18px; display: flex; gap: 10px; }
.btn-cancel, .btn-confirm { flex: 1; padding: 13px; border-radius: var(--r-sm); border: none; font-weight: 700; cursor: pointer; }
.btn-confirm { background: var(--grad-aqua); color: white; box-shadow: var(--sh-aqua); }
.btn-cancel { background: var(--surface-2); color: var(--ink-2); }

.orders-page { padding: 15px; }
.balance-block { margin-bottom: 15px; font-size: 18px; }
</style>
