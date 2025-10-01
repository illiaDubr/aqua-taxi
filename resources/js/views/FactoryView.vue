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
                    <small class="muted" v-if="lastSeenActive">Останнє оновлення: {{ fmtTime(lastSeenActive) }}</small>
                </div>

                <div v-if="!ordersActive.length && !loadingActive" class="empty">
                    Активних замовлень немає
                </div>

                <div v-if="errorActive" class="error">{{ errorActive }}</div>

                <div v-for="o in ordersActive" :key="o.id" class="order-card">
                    <div class="order-meta">
                        <div class="order-title">
                            <b>#{{ o.id }}</b> • {{ o.water_type }} • {{ o.quantity }} бут
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
                            <b>#{{ o.id }}</b> • {{ o.water_type }} • {{ o.quantity }} бут
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
            <div v-if="(currentTab==='new' && loadingNew) || (currentTab==='active' && loadingActive)" class="loader">
                Завантаження…
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onBeforeUnmount } from 'vue'
import axios from 'axios'

/** --- Состояния --- */
const currentTab = ref('active')
const isOnline = ref(true)
const balance = ref(0)

const ordersNew = ref([])
const ordersActive = ref([])

const loadingNew = ref(false)
const loadingActive = ref(false)
const errorNew = ref('')
const errorActive = ref('')

const lastSeenNew = ref(null)
const lastSeenActive = ref(null)

const pollTimer = ref(null)
// реактивная мапа занятых id
const busy = ref({})

// контроллер для отмены висящих запросов
let abortCtl = null

/** --- Helpers --- */
function toggleStatus() { isOnline.value = !isOnline.value }
function topUp() { /* TODO: модалка пополнения, пока заглушка */ }

function money(v) {
    const n = Number(v ?? 0)
    return `${n.toFixed(2)} грн`
}
function fmtTime(iso) {
    try { return new Date(iso).toLocaleString() } catch { return '' }
}

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
    try {
        return await getter({ signal: abortCtl.signal })
    } finally {
        // не чистим тут — пусть внешний код отменяет при необходимости
    }
}

/** --- API --- */
async function pollNew() {
    loadingNew.value = true
    errorNew.value = ''
    try {
        const params = { status: 'new' }
        if (lastSeenNew.value) params.updated_since = lastSeenNew.value
        const { data } = await withAbort(({ signal }) =>
            axios.get('/api/factory-orders', { params, signal })
        )
        mergeOrders(ordersNew, data?.orders)
        lastSeenNew.value = data?.meta?.server_time || new Date().toISOString()
    } catch (e) {
        if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') {
            errorNew.value = e?.response?.data?.message || 'Помилка завантаження'
        }
    } finally {
        loadingNew.value = false
    }
}

async function pollActive() {
    loadingActive.value = true
    errorActive.value = ''
    try {
        const params = { status: 'accepted' }
        if (lastSeenActive.value) params.updated_since = lastSeenActive.value
        const { data } = await withAbort(({ signal }) =>
            axios.get('/api/factory-orders', { params, signal })
        )
        mergeOrders(ordersActive, data?.orders)
        lastSeenActive.value = data?.meta?.server_time || new Date().toISOString()
    } catch (e) {
        if (e.name !== 'CanceledError' && e.code !== 'ERR_CANCELED') {
            errorActive.value = e?.response?.data?.message || 'Помилка завантаження'
        }
    } finally {
        loadingActive.value = false
    }
}

async function accept(id) {
    busy.value[id] = true
    try {
        await axios.post(`/api/factory-orders/${id}/accept`)
        const idx = ordersNew.value.findIndex(x => x.id === id)
        if (idx !== -1) {
            const o = { ...ordersNew.value[idx], status: 'accepted', accepted_at: new Date().toISOString() }
            ordersNew.value.splice(idx, 1)
            ordersActive.value.unshift(o)
        } else {
            await pollActive()
        }
    } catch (e) {
        errorNew.value = e?.response?.data?.message || 'Не вдалося прийняти замовлення'
        await Promise.allSettled([pollNew(), pollActive()])
    } finally {
        delete busy.value[id]
    }
}

async function complete(id) {
    busy.value[id] = true
    try {
        await axios.post(`/api/factory-orders/${id}/complete`)
        const before = ordersActive.value.length
        ordersActive.value = ordersActive.value.filter(x => x.id !== id)
        if (before === ordersActive.value.length) await pollActive()
    } catch (e) {
        errorActive.value = e?.response?.data?.message || 'Не вдалося завершити замовлення'
        await pollActive()
    } finally {
        delete busy.value[id]
    }
}

/** --- Polling --- */
function startPolling(fn) {
    stopPolling()
    fn() // сразу первый запрос
    pollTimer.value = setInterval(fn, 60_000)
}
function stopPolling() {
    if (pollTimer.value) clearInterval(pollTimer.value)
    pollTimer.value = null
    cancelInFlight()
}

function manualRefreshNew()    { pollNew() }
function manualRefreshActive() { pollActive() }

function switchTab(tab) {
    currentTab.value = tab
    if (tab === 'new') startPolling(pollNew)
    else if (tab === 'active') startPolling(pollActive)
    else stopPolling()
}

/** --- Mount --- */
onMounted(() => {
    // axios.defaults.withCredentials = true // если используешь Sanctum-куки
    switchTab('active')
})

onBeforeUnmount(() => stopPolling())
</script>

<style scoped>
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
.status-toggle.offline { background-color: #9e9e9e; }
.status-toggle .label { font-size: 14px; color: white; }
.status-toggle.offline .label { transform: translateX(10px) scale(0.9); transition: transform 0.3s ease; }
.status-toggle .toggle-circle { width: 12px; height: 12px; background-color: white; border-radius: 50%; transition: transform 0.3s ease; }
.status-toggle.offline .toggle-circle { transform: translateX(-80px) scale(0.9); }

.producer-orders {
    background: #00aaff;
    position: relative;
    z-index: 1;
    font-family: 'Montserrat', sans-serif;
}
.producer-orders__topbar {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    color: white;
}

.balance-box {
    display: flex;
    align-items: center;
    background-color: white;
    border-radius: 20px;
    padding: 4px 10px;
    color: #333;
    gap: 4px;
}
.balance-box .amount { font-weight: 600; font-size: 14px; }
.balance-box .plus { background: none; border: none; font-size: 18px; cursor: pointer; color: #007BFF; }

.tabs {
    display: flex;
    padding: 10px;
    border-radius: 15px;
    justify-content: space-around;
    margin-top: 16px;
    border-bottom: 2px solid #eee;
}
.tabs button {
    flex: 1; padding: 12px; background: none; border: none;
    font-size: 15px; font-weight: 500; color: #999; position: relative;
}
.tabs .active { color: #000; }
.tabs .active::after {
    content: ''; position: absolute; bottom: 0; left: 25%; right: 25%;
    height: 3px; background-color: #2196F3; border-radius: 4px;
}

.alert {
    background: #fff4f4;
    border: 1px solid #ffbcbc;
    padding: 12px;
    border-radius: 12px;
    color: #d32f2f;
    font-size: 14px;
    margin: 10px;
    text-align: center;
}

.content { margin-top: 16px; padding: 0 10px 16px; }
.toolbar {
    display: flex; align-items: center; gap: 12px;
    justify-content: flex-start; margin: 0 10px 8px;
}
.muted { color: #7a7a7a; }

.topup-button {
    background-color: #2196F3;
    color: white;
    padding: 10px 16px;
    font-size: 14px;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}

.empty { padding: 16px; text-align: center; color: #777; }
.error { margin: 10px; color: #c62828; text-align: center; }
.loader { margin: 10px; color: #333; text-align: center; }

.order-card {
    background: #fff; border-radius: 12px; padding: 12px;
    margin: 10px; display: flex; justify-content: space-between; align-items: center;
    border: 1px solid #eee;
}
.order-meta { display: grid; gap: 6px; }
.order-title { font-size: 16px; }
.order-sub { color: #666; font-size: 13px; }
.subtle { color: #9c9c9c; }
.badge { display: inline-block; padding: 2px 8px; background:#ececec; border-radius: 8px; font-size: 12px; }
.badge--active { background:#d6f5e0; }

.order-actions { display:flex; gap:10px; }
.btn {
    border: none; border-radius: 10px; padding: 8px 12px; cursor: pointer; font-weight: 600;
}
.btn-accept { background:#2e7d32; color:#fff; }
.btn-complete { background:#1565c0; color:#fff; }
</style>
