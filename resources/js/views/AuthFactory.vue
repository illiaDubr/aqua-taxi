<template>
    <div class="auth">
        <div class="auth__bg"></div>

        <div class="auth__top">
            <img :src="logo" alt="logo" class="auth__logo" />
        </div>

        <div class="auth__card">
            <!-- Глобальный баннер ошибок -->
            <div v-if="formErr" class="alert alert--error">
                <strong>Помилка:</strong> {{ formErr }}
            </div>

            <div class="auth__tabs">
                <span :class="{ active: activeTab === 'register' }" @click="switchTab('register')">Реєстрація</span>
                <span :class="{ active: activeTab === 'login' }" @click="switchTab('login')">Вхід</span>
            </div>

            <transition name="fade" mode="out-in">
                <form
                    autocomplete="off"
                    @submit.prevent="activeTab === 'register' ? handleRegister() : handleLogin()"
                    class="auth__form"
                    :key="activeTab + '-' + step"
                    novalidate
                >
                    <!-- ===================== РЕЄСТРАЦІЯ ===================== -->
                    <template v-if="activeTab === 'register'">
                        <!-- STEP 1 -->
                        <div v-if="step === 1" class="auth__form">
                            <div class="field">
                                <input class="auth__input" type="email" placeholder="Ваша пошта*" v-model.trim="email" />
                                <small v-if="fieldErr.email" class="fld-err">{{ fieldErr.email }}</small>
                            </div>

                            <div class="field">
                                <input class="auth__input" type="tel" placeholder="Ваш номер телефону*" v-model.trim="phone" />
                                <small v-if="fieldErr.phone" class="fld-err">{{ fieldErr.phone }}</small>
                            </div>

                            <div class="field auth__password-wrapper">
                                <input
                                    class="auth__input"
                                    :type="showPassword ? 'text' : 'password'"
                                    placeholder="Ваш пароль*"
                                    v-model.trim="password"
                                />
                                <span class="auth__eye-icon" @click="showPassword = !showPassword">👁</span>
                                <small v-if="fieldErr.password" class="fld-err">{{ fieldErr.password }}</small>
                            </div>

                            <div class="field">
                                <input class="auth__input" type="text" placeholder="Ваш вебсайт*" v-model.trim="website" />
                                <small v-if="fieldErr.website" class="fld-err">{{ fieldErr.website }}</small>
                            </div>

                            <label class="auth__checkbox">
                                <input type="checkbox" v-model="agree" />
                                <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                            </label>
                            <small v-if="fieldErr.agree" class="fld-err">{{ fieldErr.agree }}</small>

                            <button type="button" class="auth__submit" @click="goToStep2">Наступний крок</button>
                        </div>

                        <!-- STEP 2 -->
                        <div v-else class="auth__form">
                            <div class="field upload-wrapper">
                                <input
                                    class="attacher"
                                    type="file"
                                    name="certificate"
                                    accept=".jpg,.jpeg,.png,.pdf,image/jpeg,image/png,application/pdf"
                                    @change="handleFile"
                                />
                                <p class="upload-desc">Завантажте фото сертифіката якості у форматі JPG, PNG або PDF (до 10 МБ)</p>
                                <small v-if="fieldErr.certificate" class="fld-err">{{ fieldErr.certificate }}</small>
                            </div>

                            <div class="field">
                                <input class="auth__input" type="text" placeholder="Ваша адреса складу" v-model.trim="warehouse" />
                                <small v-if="fieldErr.warehouse_address" class="fld-err">{{ fieldErr.warehouse_address }}</small>
                            </div>

                            <!-- переключатель ручного выбора точки -->
                            <div class="manual-toggle">
                                <label class="auth__checkbox">
                                    <input type="checkbox" v-model="manualMode" />
                                    <span>Вказати точку на карті вручну</span>
                                </label>
                            </div>

                            <!-- Блок выбора точки -->
                            <div v-if="manualMode || geoError" class="geo-select">
                                <p class="geo-hint">
                                    Клікніть по карті, щоб обрати місцезнаходження складу.
                                    <span v-if="geoError"> (Автовизначення не вдалося — встановіть точку вручну.)</span>
                                </p>

                                <div ref="mapRef" class="map-container"></div>

                                <div class="coords">
                                    <div class="field-inline">
                                        <input class="auth__input" type="number" step="0.000001" placeholder="Широта" v-model.number="lat" />
                                        <small v-if="fieldErr.lat" class="fld-err">{{ fieldErr.lat }}</small>
                                    </div>
                                    <div class="field-inline">
                                        <input class="auth__input" type="number" step="0.000001" placeholder="Довгота" v-model.number="lng" />
                                        <small v-if="fieldErr.lng" class="fld-err">{{ fieldErr.lng }}</small>
                                    </div>

                                    <button type="button" class="mini-btn" @click="useMyLocation">Моє місцезнаходження</button>
                                    <button type="button" class="mini-btn" @click="centerKyiv">Київ</button>
                                </div>

                                <p v-if="lat && lng" class="coords-view">📍 {{ lat.toFixed(6) }}, {{ lng.toFixed(6) }}</p>
                            </div>

                            <!-- ====== Види води ====== -->
                            <div class="water-types">
                                <div class="wt-header">
                                    <h4>Види води</h4>
                                    <button type="button" class="wt-add" @click="addType">＋ Додати</button>
                                </div>

                                <div v-if="!waterTypesList.length" class="wt-empty">
                                    Поки не додано жодного типу. Додайте хоча б один.
                                </div>

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
                                <small v-if="fieldErr.water_types" class="fld-err">{{ fieldErr.water_types }}</small>
                            </div>
                            <!-- ====== / Види води ====== -->

                            <label class="auth__checkbox">
                                <input type="checkbox" v-model="agree" />
                                <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                            </label>
                            <small v-if="fieldErr.agree" class="fld-err">{{ fieldErr.agree }}</small>

                            <button type="submit" class="auth__submit" :disabled="submitting">
                                <span v-if="!submitting">Завершити реєстрацію</span>
                                <span v-else>Надсилаємо…</span>
                            </button>
                        </div>
                    </template>

                    <!-- ===================== ВХІД ===================== -->
                    <template v-else>
                        <div class="field">
                            <input class="auth__input" type="email" placeholder="Ваша пошта*" v-model.trim="email" />
                            <small v-if="fieldErr.email" class="fld-err">{{ fieldErr.email }}</small>
                        </div>

                        <div class="field auth__password-wrapper">
                            <input
                                class="auth__input"
                                :type="showPassword ? 'text' : 'password'"
                                placeholder="Пароль*"
                                v-model.trim="password"
                            />
                            <span class="auth__eye-icon" @click="showPassword = !showPassword">👁</span>
                            <small v-if="fieldErr.password" class="fld-err">{{ fieldErr.password }}</small>
                        </div>

                        <button type="submit" class="auth__submit" :disabled="submitting">
                            <span v-if="!submitting">Увійти</span>
                            <span v-else>Входимо…</span>
                        </button>
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
const submitting = ref(false)

const email = ref('')
const phone = ref('')
const password = ref('')
const website = ref('')
const warehouse = ref('')
const agree = ref(false)
const file = ref(null)
const showPassword = ref(false)

const formErr = ref('')
const fieldErr = ref({
    email: '', phone: '', password: '', website: '',
    warehouse_address: '', certificate: '', water_types: '',
    lat: '', lng: '', agree: ''
})

const manualMode = ref(false)
const geoError = ref(false)
const lat = ref(null)
const lng = ref(null)
const map = ref(null)
const marker = ref(null)
const mapRef = ref(null)

/** ======= Види води ======= */
const presets = [
    { value: 'silver',  label: 'Срібна',               name: 'Срібна',               price: 33.5 },
    { value: 'deep',    label: 'Глибокого очищення',   name: 'Глибокого очищення',   price: 28.0 },
    { value: 'mineral', label: 'Мінеральна',           name: 'Мінеральна',           price: 35.0 },
    { value: 'custom',  label: 'Інше' }
]
const waterTypesList = ref([{ uid: cryptoRand(), preset: 'silver', name: 'Срібна', code: 'silver', price: 33.5, _err: null }])
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
    if (map.value) { map.value.remove(); map.value = null; marker.value = null }
})

/** ======= Helpers ======= */
const isEmail = (v) => /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(String(v || '').trim())
const normPhone = (v) => String(v || '').replace(/[^\d+]/g, '').replace(/(?!^)\+/g, '')
const isStrongPass = (v) => String(v || '').length >= 6
const normWebsite = (v) => {
    let s = String(v || '').trim()
    if (!s) return s
    if (!/^https?:\/\//i.test(s)) s = 'https://' + s
    return s
}

function resetErrors() {
    formErr.value = ''
    Object.keys(fieldErr.value).forEach(k => { fieldErr.value[k] = '' })
}

function mapValidationErrors(errors) {
    // Laravel: errors = { field: [msg1, msg2], ... }
    Object.entries(errors || {}).forEach(([k, arr]) => {
        const key = (k === 'warehouse' ? 'warehouse_address' : k)
        fieldErr.value[key] = Array.isArray(arr) ? arr[0] : String(arr)
    })
}

/** ======= Сабмиты ======= */
async function handleRegister() {
    resetErrors()

    if (step.value === 1) { goToStep2(); return }

    // Клиентская валидация базово
    if (!warehouse.value) fieldErr.value.warehouse_address = 'Вкажіть адресу складу'
    if (!file.value) fieldErr.value.certificate = 'Додайте файл сертифіката'
    if (!agree.value) fieldErr.value.agree = 'Необхідно погодитись з умовами'

    if (Object.values(fieldErr.value).some(Boolean)) {
        formErr.value = 'Перевірте виділені поля'
        return
    }

    if (!validateTypes()) {
        formErr.value = 'Перевірте «Види води»'
        document.querySelector('.water-types')?.scrollIntoView({ behavior: 'smooth', block: 'center' })
        return
    }

    const formData = new FormData()
    formData.append('email', email.value.trim())
    formData.append('phone', normPhone(phone.value))
    formData.append('password', password.value.trim())
    formData.append('website', normWebsite(website.value))
    formData.append('warehouse_address', warehouse.value.trim())
    formData.append('certificate', file.value)
    formData.append('water_types', JSON.stringify(serializeWaterTypes()))

    try {
        submitting.value = true
        if (manualMode.value) {
            if (lat.value == null || lng.value == null) {
                fieldErr.value.lat = 'Вкажіть координати'
                fieldErr.value.lng = 'Вкажіть координати'
                formErr.value = 'Встановіть точку на карті або вимкніть ручний режим'
                submitting.value = false
                return
            }
            formData.append('lat', String(lat.value))
            formData.append('lng', String(lng.value))
        }

        await axios.post('/api/factory/register', formData, {
            headers: { 'Content-Type': 'multipart/form-data' }
        })

        alert('Реєстрація успішна! Сертифікат відправлено на модерацію.')
        // reset
        activeTab.value = 'login'
        map.value?.remove(); map.value = null; marker.value = null
        lat.value = null; lng.value = null; geoError.value = false; manualMode.value = false
        step.value = 1
        email.value = phone.value = password.value = website.value = warehouse.value = ''
        agree.value = false; file.value = null
        waterTypesList.value = [{ uid: cryptoRand(), preset: 'silver', name: 'Срібна', code: 'silver', price: 33.5, _err: null }]
    } catch (err) {
        handleApiError(err, 'register')
    } finally {
        submitting.value = false
    }
}

async function handleLogin() {
    resetErrors()
    if (!isEmail(email.value)) fieldErr.value.email = 'Невірна пошта'
    if (!isStrongPass(password.value)) fieldErr.value.password = 'Мінімум 6 символів'
    if (Object.values(fieldErr.value).some(Boolean)) {
        formErr.value = 'Перевірте пошту і пароль'
        return
    }

    try {
        submitting.value = true
        const res = await axios.post('/api/factory/login', {
            email: email.value.trim(),
            password: password.value.trim()
        })
        const token = res.data.token
        localStorage.setItem('factory_token', token)
        localStorage.setItem('active_token', 'factory_token')
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`
        alert('Успішний вхід!')
        router.push('/factory-page')
    } catch (err) {
        handleApiError(err, 'login')
    } finally {
        submitting.value = false
    }
}

function handleApiError(err, phase) {
    // Axios error object normalization
    const status = err?.response?.status
    const data = err?.response?.data

    // Сетевые ошибки (нет ответа)
    if (!status) {
        formErr.value = 'Немає з’єднання з сервером. Спробуйте ще раз.'
        return
    }

    // 422: валидация или кастомные ошибки
    if (status === 422) {
        if (data?.error === 'geocoding_failed') {
            // включаем ручной режим и показываем подсказку
            geoError.value = true
            manualMode.value = true
            formErr.value = data?.message || 'Не вдалося визначити координати. Встановіть точку вручну.'
            // Подсветим поля координат
            fieldErr.value.lat = 'Вкажіть широту'
            fieldErr.value.lng = 'Вкажіть довготу'
            return
        }
        if (data?.errors) {
            mapValidationErrors(data.errors)
            // Частые поля: email/phone/password/website/warehouse_address/water_types/certificate
            formErr.value = data?.message || 'Перевірте виділені поля форми'
            return
        }
        formErr.value = data?.message || 'Невірні дані запиту'
        return
    }

    // 401: неверные креды
    if (status === 401) {
        if (phase === 'login') {
            fieldErr.value.email = '—'
            fieldErr.value.password = 'Невірна пошта або пароль'
            formErr.value = 'Невірна пошта або пароль'
        } else {
            formErr.value = data?.message || 'Недостатньо прав'
        }
        return
    }

    // 409: конфликт
    if (status === 409) {
        formErr.value = data?.message || 'Конфлікт запиту. Оновіть сторінку та спробуйте ще раз.'
        return
    }

    // 413: файл слишком большой
    if (status === 413) {
        fieldErr.value.certificate = 'Файл завеликий. Максимум 10 МБ.'
        formErr.value = 'Файл завеликий. Максимум 10 МБ.'
        return
    }

    // 415/422 по MIME или маппим как неподдерживаемый формат
    if (status === 415) {
        fieldErr.value.certificate = 'Непідтримуваний формат файлу. Дозволено JPG, PNG, PDF.'
        formErr.value = 'Непідтримуваний формат файлу.'
        return
    }

    // Прочие 4xx
    if (status >= 400 && status < 500) {
        formErr.value = data?.message || `Помилка запиту (${status}).`
        return
    }

    // 5xx
    if (status >= 500) {
        formErr.value = 'Помилка сервера. Спробуйте пізніше.'
        return
    }

    // Фолбек
    formErr.value = 'Сталася невідома помилка. Спробуйте ще раз.'
}

function goToStep2() {
    resetErrors()
    if (!isEmail(email.value)) fieldErr.value.email = 'Невірна пошта'
    const phoneNorm = normPhone(phone.value)
    if (!phoneNorm || phoneNorm.length < 10) fieldErr.value.phone = 'Невірний телефон'
    if (!isStrongPass(password.value)) fieldErr.value.password = 'Мінімум 6 символів'
    if (!website.value) fieldErr.value.website = 'Вкажіть вебсайт'
    if (!agree.value) fieldErr.value.agree = 'Необхідно погодитись з умовами'

    if (Object.values(fieldErr.value).some(Boolean)) {
        formErr.value = 'Перевірте виділені поля'
        return
    }

    website.value = normWebsite(website.value)
    step.value = 2
}

function handleFile(e) {
    fieldErr.value.certificate = ''
    const f = e.target.files?.[0]
    if (!f) { file.value = null; return }
    const okTypes = ['image/jpeg', 'image/png', 'application/pdf']
    const extOk = /\.(jpg|jpeg|png|pdf)$/i.test(f.name)
    if (!okTypes.includes(f.type) && !extOk) {
        fieldErr.value.certificate = 'Підтримуються лише JPG, PNG або PDF'
        file.value = null
        return
    }
    const maxBytes = 10 * 1024 * 1024 // 10MB
    if (f.size > maxBytes) {
        fieldErr.value.certificate = 'Файл завеликий (до 10 МБ)'
        file.value = null
        return
    }
    file.value = f
}

function switchTab(tab) {
    activeTab.value = tab
    formErr.value = ''
    Object.keys(fieldErr.value).forEach(k => fieldErr.value[k] = '')
    if (tab === 'login') { step.value = 1 }
}
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

.field { display:flex; flex-direction: column; gap:6px; }
.field-inline { display:flex; flex-direction: column; gap:4px; min-width:220px; }
.fld-err { color:#b91c1c; font-size:12px; line-height:1.3; }

/* file */
.upload-wrapper { display:flex; flex-direction:column; gap:6px; }
.upload-desc { font-size: 12px; color:#6b7280; }

/* alert */
.alert { padding: 10px 12px; border-radius: 10px; font-size: 14px; margin-bottom: 8px; }
.alert--error { background:#fee2e2; border:1px solid #fecaca; color:#7f1d1d; }

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
.auth__submit:disabled { opacity: .7; cursor: not-allowed; }

/* ======= Види води ======= */
.water-types { background:#f8fafc; border:1px solid #e5e7eb; border-radius:12px; padding:12px; }
.wt-header { display:flex; justify-content:space-between; align-items:center; margin-bottom:8px; }
.wt-add { border:none; background:#0ea5e9; color:#fff; padding:8px 10px; border-radius:10px; cursor:pointer; font-weight:600; }

.wt-row {
    position:relative;
    display:grid;
    grid-template-columns: repeat(4, minmax(120px, 1fr)) auto;
    gap:8px;
    align-items:end;
    background:#fff;
    border:1px solid #e5e7eb;
    border-radius:10px;
    padding:10px;
    margin-bottom:8px;
    overflow: hidden;
}
.wt-col { display:flex; flex-direction:column; gap:6px; min-width: 0; }
.wt-label { font-size:12px; color:#6b7280; }
.wt-input { width: 100%; min-width: 0; box-sizing: border-box; padding:10px; border:1px solid #d1d5db; border-radius:8px; font-size:14px; }
.wt-input, .wt-input select, .wt-input input { line-height: 1.2; height: 38px; }

.wt-remove { border:none; background:#ef4444; color:#fff; border-radius:10px; padding:8px 10px; cursor:pointer; height:38px; align-self:center; }
.wt-empty { font-size:14px; color:#6b7280; background:#fff; border:1px dashed #cbd5e1; padding:10px; border-radius:10px; text-align:center; }
.wt-errors { display:flex; flex-wrap:wrap; gap:8px; margin-top:6px; }
.wt-err { background:#fee2e2; border:1px solid #fecaca; color:#991b1b; padding:4px 8px; border-radius:8px; font-size:12px; }

@media (max-width: 560px) {
    .wt-row { grid-template-columns: 1fr 1fr; }
    .wt-remove { grid-column: 1 / -1; justify-self: end; }
}

/* Анимация */
.fade-enter-active, .fade-leave-active { transition: opacity 0.25s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
