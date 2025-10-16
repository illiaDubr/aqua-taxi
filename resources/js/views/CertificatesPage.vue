<template>
    <div class="cert">
        <div class="cert__bg"></div>

        <div class="cert__top">
            <button class="cert__role">Зайти як доставник</button>
        </div>

        <div class="cert__card">
            <div class="cert__tabs">
        <span :class="{ active: activeTab === 'certificates' }" @click="activeTab = 'certificates'">
          Прийняття сертифікатів
        </span>
                <span :class="{ active: activeTab === 'prices' }" @click="activeTab = 'prices'">
          Верифіковані виробники
        </span>
            </div>

            <!-- Pending/Invalid -->
            <div v-if="activeTab === 'certificates'">
                <div v-if="unverifiedCertificates.length" class="cert__list">
                    <div class="cert__item" v-for="cert in unverifiedCertificates" :key="cert.id">
                        <h3>Новий сертифікат</h3>

                        <p><strong>Email:</strong> {{ cert.email }}</p>
                        <p v-if="cert.phone"><strong>Телефон:</strong> {{ cert.phone }}</p>
                        <p v-if="cert.website">
                            <strong>Сайт:</strong>
                            <a :href="cert.website" target="_blank" rel="noopener">{{ cert.website }}</a>
                        </p>
                        <p v-if="cert.warehouse_address"><strong>Адреса складу:</strong> {{ cert.warehouse_address }}</p>
                        <p v-if="cert.lat && cert.lng"><strong>Координати:</strong> {{ cert.lat }}, {{ cert.lng }}</p>

                        <div v-if="displayPath(cert)" style="margin:10px 0;">
                            <!-- Изображения -->
                            <template v-if="isImage(displayPath(cert))">
                                <img
                                    :src="publicUrl(displayPath(cert))"
                                    alt="Certificate"
                                    style="max-width:100%; border-radius:8px;"
                                    @error="markImgError(cert)"
                                    v-if="!cert._imgError"
                                />
                                <a
                                    v-else
                                    :href="publicUrl(displayPath(cert))"
                                    target="_blank"
                                    rel="noopener"
                                >Відкрити зображення</a>
                            </template>

                            <!-- PDF -->
                            <template v-else-if="isPdf(displayPath(cert))">
                                <a
                                    :href="publicUrl(displayPath(cert))"
                                    class="cert__btn"
                                    target="_blank"
                                    rel="noopener"
                                >Скачати сертифікат (PDF)</a>
                            </template>

                            <!-- Прочие файлы -->
                            <template v-else>
                                <a
                                    :href="publicUrl(displayPath(cert))"
                                    target="_blank"
                                    rel="noopener"
                                >Відкрити файл сертифіката</a>
                            </template>
                        </div>

                        <div class="moderation-controls">
                            <label>Дата закінчення дії сертифіката:</label>
                            <input type="date" v-model="cert.certificate_expiration" />

                            <label>Статус сертифіката:</label>
                            <select v-model="cert.certificate_status">
                                <option value="valid">Валідний</option>
                                <option value="invalid">Невалідний</option>
                                <option value="pending">Очікує перевірки</option>
                            </select>

                            <div class="actions">
                                <button class="cert__btn" @click="moderate(cert)">Зберегти</button>
                                <button class="cert__btn cert__btn--success" @click="verify(cert)">
                                    Верифікувати виробника
                                </button>
                                <button class="cert__btn cert__btn--danger" @click="deleteCert(cert)">
                                    Видалити сертифікат
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="cert__placeholder">
                    <p>Сертифікатів на перевірку немає</p>
                </div>
            </div>

            <!-- Verified (по is_verified) -->
            <div v-else>
                <div v-if="verifiedCertificates.length" class="cert__list">
                    <div class="cert__item" v-for="cert in verifiedCertificates" :key="cert.id">
                        <h3>Верифікований виробник</h3>
                        <p>{{ cert.email }}</p>
                        <p>До: {{ formatDate(cert.verified_until) }}</p>
                        <p>Адреса складу: {{ cert.warehouse_address }}</p>

                        <div class="actions" style="margin-top:10px;">
                            <button class="cert__btn cert__btn--warning" @click="revokeVerify(cert)">
                                Скасувати верифікацію
                            </button>
                            <button
                                v-if="displayPath(cert)"
                                class="cert__btn cert__btn--danger"
                                @click="deleteCert(cert)"
                            >
                                Видалити сертифікат
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="cert__placeholder">
                    <p>Ще немає верифікованих виробників</p>
                </div>
            </div>
        </div>
    </div>
</template>
<script setup>
import { ref, onMounted, watch } from 'vue'
import axios from 'axios'

const activeTab = ref('certificates')
const unverifiedCertificates = ref([])
const verifiedCertificates = ref([])

/** Берём путь к файлу из строки заказа */
const displayPath = (row) => row?.certificate_path || row?.certificate_file || null

/** Нормализация путей: \ -> /, убираем лишние префиксы и слеши */
const normalizePath = (raw) => {
    if (!raw) return ''
    let s = String(raw).trim()

    // абсолютный URL — возвращаем как есть
    if (/^https?:\/\//i.test(s)) return s

    // 1) Windows-слеши -> Unix
    s = s.replace(/\\/g, '/')

    // 2) убираем ведущие слеши
    s = s.replace(/^\/+/, '')

    // 3) чистим возможные префиксы
    s = s.replace(/^public\//, '')
    s = s.replace(/^storage\/app\/public\//, '')

    // 4) гарантируем префикс storage/
    if (!s.startsWith('storage/')) s = 'storage/' + s

    // 5) убираем двойные слеши
    s = s.replace(/\/{2,}/g, '/')

    return s
}

/** Детекторы типов — работают на исходной строке (до кодирования) */
const isImage = (p) => /(\.jpe?g|\.png|\.gif|\.webp|\.svg)$/i.test(String(p || '').replace(/\\/g,'/'))
const isPdf   = (p) => /\.pdf$/i.test(String(p || '').replace(/\\/g,'/'))

/** Строим корректный публичный URL под nginx (/storage/...) */
const publicUrl = (raw) => {
    if (!raw) return ''
    // Абсолютный URL?
    if (/^https?:\/\//i.test(String(raw))) return String(raw)

    const clean = normalizePath(raw)
    // Кодируем КАЖДЫЙ сегмент, чтобы пробелы/кириллица не ломали URL
    const encoded = clean
        .split('/')
        .map(seg => encodeURIComponent(decodeURIComponent(seg)))
        .join('/')

    return `${window.location.origin}/${encoded}`
}

/** Формат даты */
const formatDate = (date) => (date ? new Date(date).toLocaleDateString('uk-UA') : '—')

/** Обработчик падения img */
const markImgError = (cert) => { cert._imgError = true }

/** Сохранить статус сертификата/дату */
async function moderate(cert) {
    try {
        await axios.post(`/api/admin/factories/${cert.id}/moderate-certificate`, {
            status: cert.certificate_status,
            expiration_date: cert.certificate_expiration,
        })
        await loadUnverified()
        await loadVerified()
    } catch (err) {
        console.error('❌ Помилка оновлення сертифіката', err)
        alert('Не вдалося оновити сертифікат.')
    }
}

/** Явная верификация виробника */
async function verify(cert) {
    let verified_until = cert.certificate_expiration
    if (!verified_until) {
        verified_until = prompt(
            'Вкажіть дату закінчення верифікації (YYYY-MM-DD):',
            new Date().toISOString().slice(0,10)
        )
        if (!verified_until) return
    }
    try {
        await axios.post(`/api/admin/factories/${cert.id}/approve`, { verified_until })
        await loadVerified()
        await loadUnverified()
    } catch (err) {
        console.error('❌ Помилка верифікації', err)
        alert('Не вдалося верифікувати виробника.')
    }
}

/** Снять верификацию */
async function revokeVerify(cert) {
    if (!confirm('Скасувати верифікацію цього виробника?')) return
    try {
        await axios.post(`/api/admin/factories/${cert.id}/unverify`)
        await loadVerified()
        await loadUnverified()
    } catch (err) {
        console.error('❌ Помилка скасування верифікації', err)
        alert('Не вдалося скасувати верифікацію.')
    }
}

/** Удалить сертификат (и файл) */
async function deleteCert(cert) {
    if (!confirm('Видалити сертифікат? Файл буде видалено безповоротно.')) return
    try {
        await axios.delete(`/api/admin/factories/${cert.id}/certificate`)
        await loadUnverified()
        await loadVerified()
    } catch (err) {
        console.error('❌ Помилка видалення сертифіката', err)
        alert('Не вдалося видалити сертифікат.')
    }
}

/** Pending/Invalid */
async function loadUnverified() {
    try {
        const res = await axios.get('/api/admin/factories-with-certificates')
        const rows = (res.data || []).map(f => ({
            ...f,
            certificate_path: f.certificate_path || f.certificate_file || null
        }))
        unverifiedCertificates.value = rows.filter(
            f => (f.certificate_status === 'pending' || f.certificate_status === 'invalid')
        )
    } catch (err) {
        console.error('❌ Помилка завантаження неварифікованих', err)
    }
}

/** Верифіковані */
async function loadVerified() {
    try {
        const res = await axios.get('/api/factories/verified')
        verifiedCertificates.value = res.data || []
    } catch (err) {
        console.error('❌ Помилка завантаження верифікованих', err)
    }
}

watch(activeTab, (tab) => {
    if (tab === 'certificates') loadUnverified()
    else loadVerified()
})

onMounted(() => {
    loadUnverified()
})
</script>


<style scoped>
.cert {
    min-height: 100vh;
    background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 55%);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 120px;
    position: relative;
}
.cert__bg { position: absolute; top: 10px; width: 100%; height: 140px; background-size: cover; z-index: 0; }
.cert__top { position: absolute; top: 60px; right: 16px; z-index: 2; }

.cert__role {
    background: white; padding: 8px 14px; border-radius: 12px; border: none;
    font-weight: 500; font-size: 13px; color: #333; box-shadow: 0 2px 6px rgba(0,0,0,0.1);
    cursor: pointer;
}

.cert__card {
    width: 100%; max-width: 400px; background: #fff; border-radius: 20px 20px 0 0;
    box-shadow: 0 -2px 20px rgba(0,0,0,0.1); z-index: 1; padding: 24px 16px;
}

.cert__tabs { display: flex; justify-content: space-between; margin-bottom: 24px; }
.cert__tabs span {
    flex: 1; text-align: center; font-weight: 600; font-size: 15px; color: #aaa; cursor: pointer;
    padding-bottom: 6px; position: relative;
}
.cert__tabs .active { color: #000; }
.cert__tabs .active::after {
    content: ''; position: absolute; bottom: 0; left: 20%; width: 60%; height: 2px; background: #3498db; border-radius: 4px;
}

.cert__list { display: flex; flex-direction: column; gap: 16px; }
.cert__item {
    background: #fff; border-radius: 16px; border: 1px solid #eee; padding: 16px;
    box-shadow: 0 2px 8px rgba(0,0,0,0.05); text-align: center;
}
.cert__item h3 { font-size: 16px; font-weight: 700; margin-bottom: 12px; }

.moderation-controls { display: grid; gap: 8px; margin-top: 10px; }
.moderation-controls .actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; }

.cert__btn {
    background: #3498db; color: white; border: none; border-radius: 10px; padding: 10px 16px;
    font-size: 14px; font-weight: 600; cursor: pointer; text-decoration: none; display:inline-block;
}
.cert__btn--danger { background: #e53935; }
.cert__btn--warning { background: #f39c12; }
.cert__btn--success { background: #2e7d32; }

.cert__placeholder { text-align: center; color: #777; margin-top: 40px; }
</style>
