<template>
    <div class="auth">
        <div class="auth__bg"></div>
        <div class="auth__top">
            <img :src="logo" alt="logo" class="auth__logo" />
        </div>

        <div class="auth__card">
            <div class="auth__tabs">
                <span :class="{ active: activeTab === 'register' }" @click="activeTab = 'register'">Реєстрація</span>
                <span :class="{ active: activeTab === 'login' }" @click="activeTab = 'login'">Вхід</span>
            </div>

            <transition name="fade" mode="out-in">
                <form
                    autocomplete="off"
                    @submit.prevent="activeTab === 'register' ? handleRegister() : handleLogin()"
                    class="auth__form"
                    :key="step"
                >
                    <!-- ===================== РЕЄСТРАЦІЯ ===================== -->
                    <template v-if="activeTab === 'register'">
                        <!-- STEP 1 -->
                        <div v-if="step === 1" class="auth__form">
                            <input class="auth__input" type="email" placeholder="Ваша пошта*" v-model="email" required />
                            <input class="auth__input" type="tel" placeholder="Ваш номер телефону*" v-model="phone" required />

                            <div class="auth__password-wrapper">
                                <input
                                    class="auth__input"
                                    :type="showPassword ? 'text' : 'password'"
                                    :placeholder="activeTab === 'register' ? 'Ваш пароль*' : 'Пароль*'"
                                    v-model="password"
                                    required
                                />
                                <span class="auth__eye-icon" @click="showPassword = !showPassword">👁</span>
                            </div>

                            <input class="auth__input" type="text" placeholder="Ваш вебсайт*" v-model="website" required />

                            <label class="auth__checkbox">
                                <input type="checkbox" v-model="agree" />
                                <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                            </label>

                            <button type="button" class="auth__submit" @click="goToStep2">Наступний крок</button>
                        </div>

                        <!-- STEP 2 -->
                        <div v-else class="auth__form">
                            <div class="upload-wrapper">
                                <input class="attacher" type="file" name="certificate" accept="image/png, image/jpeg, application/pdf" @change="handleFile" />
                                <p class="upload-desc">Завантажте фото сертифіката якості у форматі JPG, PNG або PDF</p>
                            </div>

                            <input class="auth__input" type="text" placeholder="Ваша адреса складу" v-model="warehouse" required />

                            <!-- переключатель ручного выбора точки -->
                            <div class="manual-toggle">
                                <label class="auth__checkbox">
                                    <input type="checkbox" v-model="manualMode" />
                                    <span>Вказати точку на карті вручну</span>
                                </label>
                            </div>

                            <!-- Блок выбора точки: показывается при manualMode ИЛИ при geoError -->
                            <div v-if="manualMode || geoError" class="geo-select">
                                <p class="geo-hint">
                                    Клікніть по карті, щоб обрати місцезнаходження складу.
                                    <span v-if="geoError"> (Автовизначення не вдалося — встановіть точку вручну.)</span>
                                </p>
                                <div ref="mapRef" class="map-container"></div>

                                <div class="coords">
                                    <input class="auth__input" type="number" step="0.000001" placeholder="Широта" v-model.number="lat" />
                                    <input class="auth__input" type="number" step="0.000001" placeholder="Довгота" v-model.number="lng" />
                                    <button type="button" class="mini-btn" @click="useMyLocation">Моє місцезнаходження</button>
                                    <button type="button" class="mini-btn" @click="centerKyiv">Київ</button>
                                </div>

                                <p v-if="lat && lng" class="coords-view">📍 Обрані координати: {{ lat.toFixed(6) }}, {{ lng.toFixed(6) }}</p>
                            </div>

                            <!-- ====== Види води ====== -->
                            <div class="water-types">
                                <div class="wt-header">
                                    <h4>Види води</h4>
                                    <button type="button" class="wt-add" @click="addType">＋ Додати</button>
                                </div>

                                <div v-if="!waterTypesList.length" class="wt-empty">Поки не додано жодного типу. Додайте хоча б один.</div>

                                <div v-for="(row, i) in waterTypesList" :key="row.uid" class="wt-row">
                                    <div class="wt-col">
                                        <label class="wt-label">Шаблон</label>
                                        <select v-model="row.preset" class="wt-input" @change="applyPreset(row)">
                                            <option disabled value="">— оберіть —</option>
                                            <option v-for="p in presets" :key="p.value" :value="p.value">{{ p.label }}</option>
                                        </select>
                                    </div>

                                    <div class="wt-col" v-if="row.preset === 'custom'">
                                        <label class="wt-label">Назва</label>
                                        <input class="wt-input" type="text" v-model.trim="row.name" placeholder="Напр., Артезіанська" @input="row.code = slugify(row.name)" />
                                    </div>

                                    <div class="wt-col">
                                        <label class="wt-label">Код</label>
                                        <input class="wt-input" type="text" v-model.trim="row.code" placeholder="auto-code" @blur="row.code = slugify(row.code)" />
                                    </div>

                                    <div class="wt-col">
                                        <label class="wt-label">Ціна за бутиль (грн)</label>
                                        <input class="wt-input" type="number" min="0" step="0.01" v-model.number="row.price" />
                                    </div>

                                    <button type="button" class="wt-remove" @click="removeType(i)">✕</button>

                                    <div class="wt-errors" v-if="row._err">
                                        <span v-for="(msg, k) in row._err" :key="k" class="wt-err">{{ msg }}</span>
                                    </div>
                                </div>

                                <div v-if="typesError" class="wt-errors" style="margin-top:6px;">
                                    <span class="wt-err">{{ typesError }}</span>
                                </div>
                            </div>
                            <!-- ====== / Види води ====== -->

                            <label class="auth__checkbox">
                                <input type="checkbox" v-model="agree" />
                                <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                            </label>

                            <button type="submit" class="auth__submit">Завершити реєстрацію</button>
                        </div>
                    </template>

                    <!-- ===================== ВХІД ===================== -->
                    <template v-else>
                        <input class="auth__input" type="email" placeholder="Ваша пошта*" v-model="email" required />
                        <div class="auth__password-wrapper">
                            <input
                                class="auth__input"
                                :type="showPassword ? 'text' : 'password'"
                                :placeholder="activeTab === 'register' ? 'Ваш пароль*' : 'Пароль*'"
                                v-model="password"
                                required
                            />
                            <span class="auth__eye-icon" @click="showPassword = !showPassword">👁</span>
                        </div>
                        <button type="submit" class="auth__submit">Увійти</button>
                    </template>
                </form>
            </transition>
        </div>
    </div>
</template>

<script setup>
import logo from '@/assets/logo2.png'
import { ref, watchEffect, nextTick, onBeforeUnmount } from 'vue'
import { useRouter } from 'vue-router'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

const router = useRouter()
const activeTab = ref('register')
const step = ref(1)

const email = ref('')
const phone = ref('')
const password = ref('')
const website = ref('')
const warehouse = ref('')
const agree = ref(false)
const file = ref(null)
const showPassword = ref(false)

const manualMode = ref(false)
const geoError = ref(false)
const lat = ref(null)
const lng = ref(null)
const map = ref(null)
const marker = ref(null)
const mapRef = ref(null)

/** ======= Види води ======= */
const presets = [
    { value: 'silver',  label: 'Срібна',                name: 'Срібна',                price: 33.5 },
    { value: 'deep',    label: 'Глибокого очищення',    name: 'Глибокого очищення',    price: 28.0 },
    { value: 'mineral', label: 'Мінеральна',           name: 'Мінеральна',            price: 35.0 },
    { value: 'custom',  label: 'Інше' }
]

const waterTypesList = ref([
    { uid: cryptoRand(), preset: 'silver', name: 'Срібна', code: 'silver', price: 33.5, _err: null }
])
const typesError = ref('')

function cryptoRand() { return Math.random().toString(36).slice(2) + Date.now().toString(36) }
function slugify(s) {
    return String(s || '')
        .toLowerCase()
        .replace(/[а-яёіїєґ]/gi, (ch) => mapCyrToLat[ch] ?? ch)
        .replace(/[^a-z0-9]+/g, '-')
        .replace(/^-+|-+$/g, '')
}
const mapCyrToLat = {
    'а':'a','б':'b','в':'v','г':'g','ґ':'g','д':'d','е':'e','є':'ye','ж':'zh','з':'z','и':'y','і':'i','ї':'yi','й':'y',
    'к':'k','л':'l','м':'m','н':'n','о':'o','п':'p','р':'r','с':'s','т':'t','у':'u','ф':'f','х':'h','ц':'ts','ч':'ch','ш':'sh','щ':'sch','ь':'','ю':'yu','я':'ya','ё':'yo'
}

function addType() { waterTypesList.value.push({ uid: cryptoRand(), preset: '', name: '', code: '', price: null, _err: null }) }
function removeType(i) { waterTypesList.value.splice(i, 1) }
function applyPreset(row) {
    const p = presets.find(x => x.value === row.preset)
    if (!p) return
    if (row.preset === 'custom') {
        row.name = ''; row.code = ''; row.price = null
    } else {
        row.name = p.name; row.code = row.preset; if (typeof p.price === 'number') row.price = p.price
    }
}
function validateTypes() {
    typesError.value = ''
    let ok = true
    const seen = new Set()
    waterTypesList.value.forEach(r => {
        const errs = {}
        if (!r.preset) errs.preset = 'Оберіть шаблон'
        if (r.preset === 'custom' && !r.name) errs.name = 'Вкажіть назву'
        if (!r.code) errs.code = 'Вкажіть код'
        if (r.code && seen.has(r.code.toLowerCase())) errs.code2 = 'Код має бути унікальним'
        if (r.code) seen.add(r.code.toLowerCase())
        if (!Number.isFinite(r.price) || r.price < 0) errs.price = 'Невірна ціна'
        r._err = Object.keys(errs).length ? errs : null
        if (r._err) ok = false
    })
    if (!waterTypesList.value.length) { typesError.value = 'Додайте хоча б один тип води'; ok = false }
    return ok
}
function serializeWaterTypes() {
    return waterTypesList.value.map(r => ({
        code: String(r.code).trim().toLowerCase(),
        name: String(r.name || r.code).trim(),
        price: Number.isFinite(Number(r.price)) ? Number(r.price) : 0
    }))
}

/** ======= Карта / выбор точки ======= */
watchEffect(async () => {
    if ((manualMode.value || geoError.value) && mapRef.value && !map.value) {
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

    // убрать карту, если не нужна
    if (!(manualMode.value || geoError.value) && map.value) {
        map.value.remove()
        map.value = null
        marker.value = null
    }
})

function useMyLocation() {
    if (!map.value) return
    map.value.locate({ setView: true, maxZoom: 16 })
    map.value.once('locationfound', (e) => {
        lat.value = e.latlng.lat
        lng.value = e.latlng.lng
        if (marker.value) marker.value.setLatLng(e.latlng)
        else marker.value = L.marker(e.latlng).addTo(map.value)
    })
}
function centerKyiv() { if (map.value) map.value.setView([50.4501, 30.5234], 13) }

onBeforeUnmount(() => {
    if (map.value) {
        map.value.remove()
        map.value = null
        marker.value = null
    }
})

/** ======= Сабмиты ======= */
const handleRegister = async () => {
    if (step.value === 1) { goToStep2(); return }

    if (!warehouse.value || !file.value || !agree.value) {
        alert('Будь ласка, заповніть усі поля та погодьтесь з умовами.')
        return
    }

    if (!validateTypes()) {
        document.querySelector('.water-types')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
        return
    }

    const formData = new FormData()
    formData.append('email', email.value)
    formData.append('phone', phone.value)
    formData.append('password', password.value)
    formData.append('website', website.value)
    formData.append('warehouse_address', warehouse.value)
    formData.append('certificate', file.value)
    formData.append('water_types', JSON.stringify(serializeWaterTypes()))

    try {
        if (manualMode.value) {
            if (lat.value === null || lng.value === null) {
                alert('Встановіть точку на карті або вимкніть ручний режим.')
                return
            }
            formData.append('lat', lat.value)
            formData.append('lng', lng.value)
        }
        await axios.post('/api/factory/register', formData)
        alert('Реєстрація успішна!')
        activeTab.value = 'login'

        // reset map state
        map.value?.remove(); map.value = null; marker.value = null
        lat.value = null; lng.value = null
        geoError.value = false; manualMode.value = false
    } catch (err) {
        if (err.response?.data?.error === 'geocoding_failed') {
            geoError.value = true
            manualMode.value = true
        }
        console.error(err)
        alert('Помилка при реєстрації')
    }
}

const handleLogin = async () => {
    if (!email.value || !password.value) { alert('Введіть пошту та пароль'); return }
    try {
        const res = await axios.post('/api/factory/login', { email: email.value, password: password.value })
        const token = res.data.token
        const factory = res.data.user
        localStorage.setItem('token', token)
        localStorage.setItem('factory', JSON.stringify(factory))
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
        alert('Успішний вхід!')
        router.push('/factory-page')
    } catch (err) {
        console.error(err)
        alert('Невірна пошта або пароль')
    }
}

const goToStep2 = () => {
    if (!email.value || !phone.value || !password.value || !website.value || !agree.value) {
        alert('Будь ласка, заповніть усі поля та підтвердіть згоду.')
        return
    }
    step.value = 2
}

const handleFile = (e) => { file.value = e.target.files[0] }
</script>

<style>
/* карта/гео */
.geo-warning {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    padding: 10px;
    border-radius: 8px;
    font-size: 14px;
    color: #92400e;
    margin-top: 20px;
}
.map-container { height: 250px; margin-top: 10px; border-radius: 8px; overflow: hidden; }
.manual-toggle { margin-top: 8px; }
.geo-select { margin-top: 10px; }
.geo-hint { font-size: 14px; color:#555; margin: 0 0 6px; }
.coords { display:flex; gap:8px; align-items: center; margin-top: 8px; flex-wrap: wrap; }
.mini-btn { padding: 8px 10px; border: none; border-radius: 8px; background:#e5f2ff; color:#1663c7; cursor: pointer; font-weight: 600; }
.coords-view { color:#444; font-size: 13px; margin-top: 6px; }

/* auth */
.auth__password-wrapper { position: relative; }
.auth__password-wrapper input { width: 100%; padding-left: 5px !important; padding-right: 0px !important; }
.auth__eye-icon { position: absolute; right: 12px; top: 50%; transform: translateY(-50%); cursor: pointer; }

body { font-family: 'Montserrat', sans-serif; margin: 0; padding: 0; }
.attacher { padding: 14px; font-size: 15px; border: 1px solid #ccc; border-radius: 12px; outline: none; }

.auth { position: relative; min-height: 100vh; padding: 60px 0 0 0; background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 60%); display: flex; flex-direction: column; align-items: center; }
.auth__bg { position: absolute; top: 180px; left: 0; width: 100%; height: 200px; background: url('@/assets/city.png') no-repeat center top; background-size: cover; z-index: 0; pointer-events: none; }
.auth__top, .auth__card { position: relative; z-index: 1; }
.auth__top { padding-top: 0; margin-bottom: 50px; }
.auth__logo { width: 96px; height: 96px; border-radius: 24px; box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2); }

.auth__card { width: 100%; max-width: 360px; background: white; border-radius: 24px; padding: 24px; box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15); display: flex; flex-direction: column; gap: 16px; }

.auth__tabs { display: flex; justify-content: space-around; margin-bottom: 48px; }
.auth__tabs span { font-size: 18px; font-weight: 600; color: #ccc; cursor: pointer; padding-bottom: 4px; transition: all 0.2s ease; }
.auth__tabs .active { font-size: 24px; color: #3498db; border-bottom: 2px solid #3498db; }

.auth__form { display: flex; flex-direction: column; gap: 16px; transition: all 0.2s ease; }
.auth__input { width: 100%; padding: 14px; font-size: 15px; border: 1px solid #ccc; border-radius: 12px; outline: none; }

/* чекбокс */
.auth__checkbox { display: flex; align-items: flex-start; font-size: 13px; color: #7f8c8d; gap: 8px; }
.auth__checkbox input { width: 16px; height: 16px; margin-top: 2px; }
.auth__checkbox a { color: #3498db; text-decoration: underline; }

/* кнопка */
.auth__submit { padding: 14px; font-size: 15px; font-weight: 600; background: #3498db; color: white; border: none; border-radius: 12px; cursor: pointer; }

/* ======= Види води ======= */
.water-types { background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:12px; }
.wt-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.wt-add { border:none; background:#0ea5e9; color:#fff; padding:8px 10px; border-radius:10px; cursor:pointer; font-weight:600; }

/* фикс переполнения: поля не «вылезают» из карточки */
.wt-row {
    position:relative;
    display:grid;
    grid-template-columns: repeat(4, minmax(120px, 1fr)) auto; /* preset | name | code | price | [x] */
    gap:8px;
    align-items:end;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:10px;
    padding:10px;
    margin-bottom:8px;
    overflow: hidden; /* на всякий случай */
}
.wt-col { display:flex; flex-direction:column; gap:6px; min-width: 0; } /* <-- ключевой фикс */
.wt-label { font-size:12px; color:#6b7280; }
.wt-input {
    width: 100%;
    min-width: 0;               /* <-- ключевой фикс */
    box-sizing: border-box;     /* чтобы padding не раздувал */
    padding:10px;
    border:1px solid #d1d5db;
    border-radius:8px;
    font-size:14px;
}
.wt-input, .wt-input select, .wt-input input { line-height: 1.2; height: 38px; }

.wt-remove { border:none; background:#ef4444; color:#fff; border-radius:10px; padding:8px 10px; cursor:pointer; height:38px; align-self:center; }
.wt-empty { font-size:14px; color:#6b7280; background:#fff; border:1px dashed #cbd5e1; padding:10px; border-radius:10px; text-align:center; }
.wt-errors { display:flex; flex-wrap:wrap; gap:8px; margin-top:6px; }
.wt-err { background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:4px 8px; border-radius:8px; font-size:12px; }

/* адаптив: на узких экранах складываем в 2 колонки */
@media (max-width: 560px) {
    .wt-row { grid-template-columns: 1fr 1fr; }
    .wt-remove { grid-column: 1 / -1; justify-self: end; }
}
\
/* Анимация */
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
