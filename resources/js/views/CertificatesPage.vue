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

                        <!-- Медиа превью -->
                        <div v-if="cert.certificate_url" class="cert__media-wrap">
                            <!-- IMG -->
                            <img
                                v-if="isImage(cert.certificate_url) && !cert._imgError"
                                :src="cert.certificate_url"
                                alt="Сертифікат"
                                class="cert__media"
                                @error="markImgError(cert)"
                            />

                            <!-- PDF -->
                            <object
                                v-else-if="isPdf(cert.certificate_url)"
                                :data="cert.certificate_url"
                                type="application/pdf"
                                class="cert__pdf"
                            >
                                <div class="cert__fallback">
                                    Не вдалось показати PDF. Спробуйте завантажити файл:
                                    <code>{{ cert.certificate_url }}</code>
                                </div>
                            </object>

                            <!-- Фолбек -->
                            <div v-else class="cert__fallback">
                                Не вдалось показати зображення. Шлях:
                                <code>{{ cert.certificate_url }}</code>
                            </div>
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
                                <button class="cert__btn cert__btn--success" @click="verify(cert)">Верифікувати виробника</button>
                                <button class="cert__btn cert__btn--danger" @click="deleteCert(cert)">Видалити сертифікат</button>
                            </div>
                        </div>
                    </div>
                </div>
                <div v-else class="cert__placeholder">
                    <p>Сертифікатів на перевірку немає</p>
                </div>
            </div>

            <!-- Verified -->
            <div v-else>
                <div v-if="verifiedCertificates.length" class="cert__list">
                    <div class="cert__item" v-for="cert in verifiedCertificates" :key="cert.id">
                        <h3>Верифікований виробник</h3>
                        <p>{{ cert.email }}</p>
                        <p>До: {{ formatDate(cert.verified_until) }}</p>
                        <p>Адреса складу: {{ cert.warehouse_address }}</p>

                        <div class="actions" style="margin-top:10px;">
                            <button class="cert__btn cert__btn--warning" @click="revokeVerify(cert)">Скасувати верифікацію</button>
                            <button
                                v-if="cert.certificate_url"
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

/** Тип файла по расширению */
const isImage = (p) => /(\.jpe?g|\.png|\.gif|\.webp|\.svg)(\?.*)?$/i.test(String(p || ''))
const isPdf   = (p) => /\.pdf(\?.*)?$/i.test(String(p || ''))

const formatDate = (date) => (date ? new Date(date).toLocaleDateString('uk-UA') : '—')
const markImgError = (cert) => { cert._imgError = true }

/** CRUD действий модерации */
async function moderate(cert) {
    try {
        await axios.post(`/api/admin/factories/${cert.id}/moderate-certificate`, {
            status: cert.certificate_status,
            expiration_date: cert.certificate_expiration,
        })
        await loadUnverified(); await loadVerified()
    } catch (err) {
        console.error('❌ Помилка оновлення сертифіката', err)
        alert('Не вдалося оновити сертифікат.')
    }
}

async function verify(cert) {
    let verified_until = cert.certificate_expiration
    if (!verified_until) {
        verified_until = prompt('Вкажіть дату закінчення верифікації (YYYY-MM-DD):', new Date().toISOString().slice(0,10))
        if (!verified_until) return
    }
    try {
        await axios.post(`/api/admin/factories/${cert.id}/approve`, { verified_until })
        await loadVerified(); await loadUnverified()
    } catch (err) {
        console.error('❌ Помилка верифікації', err)
        alert('Не вдалося верифікувати виробника.')
    }
}

async function revokeVerify(cert) {
    if (!confirm('Скасувати верифікацію цього виробника?')) return
    try {
        await axios.post(`/api/admin/factories/${cert.id}/unverify`)
        await loadVerified(); await loadUnverified()
    } catch (err) {
        console.error('❌ Помилка скасування верифікації', err)
        alert('Не вдалося скасувати верифікацію.')
    }
}

async function deleteCert(cert) {
    if (!confirm('Видалити сертифікат? Файл буде видалено безповоротно.')) return
    try {
        await axios.delete(`/api/admin/factories/${cert.id}/certificate`)
        await loadUnverified(); await loadVerified()
    } catch (err) {
        console.error('❌ Помилка видалення сертифіката', err)
        alert('Не вдалося видалити сертифікат.')
    }
}

/** Данные */
async function loadUnverified() {
    try {
        const res = await axios.get('/api/admin/factories-with-certificates')
        // Бэк теперь должен отдавать certificate_url
        const rows = (res.data || []).map(f => ({
            ...f,
            certificate_url: f.certificate_url || null,
        }))
        unverifiedCertificates.value = rows.filter(
            f => (f.certificate_status === 'pending' || f.certificate_status === 'invalid')
        )
    } catch (err) {
        console.error('❌ Помилка завантаження неварифікованих', err)
    }
}

async function loadVerified() {
    try {
        const res = await axios.get('/api/factories/verified')
        // тут также ожидаем certificate_url
        verifiedCertificates.value = (res.data || []).map(f => ({
            ...f,
            certificate_url: f.certificate_url || null,
        }))
    } catch (err) {
        console.error('❌ Помилка завантаження верифікованих', err)
    }
}

watch(activeTab, (tab) => {
    if (tab === 'certificates') loadUnverified()
    else loadVerified()
})

onMounted(() => { loadUnverified() })
</script>

<style scoped>
.cert {
    min-height: 100vh;
    max-width: var(--maxw);
    margin: 0 auto;
    background: var(--bg);
    display: flex;
    flex-direction: column;
    align-items: center;
    padding-top: 108px;
    position: relative;
}
.cert__bg { position: absolute; top: 0; left: 0; width: 100%; height: 150px; background: var(--grad-hero); border-radius: 0 0 28px 28px; z-index: 0; }
.cert__top { position: absolute; top: calc(52px + env(safe-area-inset-top, 0px)); right: 16px; z-index: 2; }

.cert__role {
    background: rgba(255,255,255,.94); padding: 9px 16px; border-radius: var(--r-pill); border: none;
    font-weight: 700; font-size: 13px; color: var(--aqua-700); box-shadow: var(--sh-xs);
    cursor: pointer;
}

.cert__card {
    width: 100%; max-width: 440px; background: var(--surface); border-radius: 28px 28px 0 0;
    box-shadow: var(--sh-md); z-index: 1; padding: 22px 16px; min-height: 60vh;
}

.cert__tabs { display: flex; gap: 4px; padding: 5px; background: var(--surface-2); border-radius: var(--r-pill); margin-bottom: 22px; }
.cert__tabs span {
    flex: 1; text-align: center; font-weight: 600; font-size: 13.5px; color: var(--muted); cursor: pointer;
    padding: 11px 8px; border-radius: var(--r-pill); transition: all .2s var(--ease);
}
.cert__tabs .active { color: var(--aqua-700); background: var(--surface); box-shadow: var(--sh-xs); }

.cert__list { display: flex; flex-direction: column; gap: 14px; }
.cert__item {
    background: var(--surface); border-radius: var(--r-lg); border: 1px solid var(--line-2); padding: 18px;
    box-shadow: var(--sh-sm); text-align: center;
}
.cert__item h3 { font-size: 16px; font-weight: 700; margin-bottom: 12px; color: var(--ink); }
.cert__item p { font-size: 14px; color: var(--ink-2); margin: 6px 0; }
.cert__item a { color: var(--aqua-600); }

.cert__media-wrap { margin: 12px 0; }
.cert__media { max-width: 100%; border-radius: var(--r-sm); display: block; }
.cert__pdf { width: 100%; height: 420px; border: none; border-radius: var(--r-sm); }
.cert__fallback { font-size: 12px; color: var(--muted); word-break: break-all; background: var(--surface-2); padding: 10px; border-radius: var(--r-sm); }

.moderation-controls { display: grid; gap: 10px; margin-top: 14px; text-align: left; }
.moderation-controls label { font-size: 13px; font-weight: 700; color: var(--ink); }
.moderation-controls input,
.moderation-controls select {
    width: 100%; box-sizing: border-box; padding: 12px 14px; font-size: 15px;
    border: 1.5px solid var(--line); border-radius: var(--r-sm); background: var(--surface); outline: none; color: var(--ink);
}
.moderation-controls input:focus,
.moderation-controls select:focus { border-color: var(--aqua-400); box-shadow: 0 0 0 4px var(--tap); }
.moderation-controls .actions { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-top: 4px; }

.cert__btn {
    background: var(--grad-aqua); color: white; border: none; border-radius: var(--r-sm); padding: 11px 18px;
    font-size: 14px; font-weight: 700; cursor: pointer; text-decoration: none; display: inline-block; box-shadow: var(--sh-aqua);
}
.cert__btn:active { transform: scale(.98); }
.cert__btn--danger { background: var(--danger); box-shadow: none; }
.cert__btn--warning { background: var(--warn); box-shadow: none; }
.cert__btn--success { background: var(--success); box-shadow: none; }

.cert__placeholder { text-align: center; color: var(--muted); margin-top: 48px; }
</style>
