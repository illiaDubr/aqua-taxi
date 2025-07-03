<template>
    <div class="cert">
        <div class="cert__bg"></div>

        <div class="cert__top">
            <button class="cert__role">Зайти як доставник</button>
        </div>

        <div class="cert__card">
            <div class="cert__tabs">
                <span
                    :class="{ active: activeTab === 'certificates' }"
                    @click="activeTab = 'certificates'"
                >
                    Прийняття сертифікатів
                </span>
                <span
                    :class="{ active: activeTab === 'prices' }"
                    @click="activeTab = 'prices'"
                >
                    Ціни + база сертифікатів
                </span>
            </div>

            <!-- НЕверифіковані -->
            <div v-if="activeTab === 'certificates'">
                <div v-if="unverifiedCertificates.length" class="cert__list">
                    <div
                        class="cert__item"
                        v-for="cert in unverifiedCertificates"
                        :key="cert.id"
                    >
                        <h3>Новий сертифікат</h3>

                        <p><strong>Email:</strong> {{ cert.email }}</p>
                        <p v-if="cert.phone"><strong>Телефон:</strong> {{ cert.phone }}</p>
                        <p v-if="cert.website">
                            <strong>Сайт:</strong>
                            <a :href="cert.website" target="_blank">{{ cert.website }}</a>
                        </p>
                        <p v-if="cert.warehouse_address"><strong>Адреса складу:</strong> {{ cert.warehouse_address }}</p>
                        <p v-if="cert.lat && cert.lng">
                            <strong>Координати:</strong> {{ cert.lat }}, {{ cert.lng }}
                        </p>

                        <div v-if="cert.certificate_path">
                            <img
                                v-if="isImage(cert.certificate_path)"
                                :src="fullUrl(cert.certificate_path)"
                                alt="Certificate"
                                style="max-width: 100%; margin: 10px 0;"
                            />
                            <a
                                v-else
                                :href="fullUrl(cert.certificate_path)"
                                target="_blank"
                                style="display: block; margin: 10px 0;"
                            >Скачати сертифікат (PDF)</a>
                        </div>

                        <div class="moderation-controls">
                            <label>Дата закінчення дії сертифіката:</label>
                            <input type="date" v-model="cert.certificate_expiration" />

                            <label>Статус сертифіката:</label>
                            <select v-model="cert.certificate_status">
                                <option value="valid">Валідний</option>
                                <option value="invalid">Невалідний</option>
                            </select>

                            <button class="cert__btn" @click="moderate(cert)">
                                Зберегти
                            </button>
                        </div>
                    </div>
                </div>
                <div v-else class="cert__placeholder">
                    <p>Сертифікатів на перевірку немає</p>
                </div>
            </div>

            <!-- Верифіковані -->
            <div v-else>
                <div v-if="verifiedCertificates.length" class="cert__list">
                    <div
                        class="cert__item"
                        v-for="cert in verifiedCertificates"
                        :key="cert.id"
                    >
                        <h3>Верифікований виробник</h3>
                        <p>{{ cert.email }}</p>
                        <p>До: {{ formatDate(cert.certificate_expiration) }}</p>
                        <p>Адреса складу: {{ cert.warehouse_address }}</p>
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
import { ref, onMounted, watch } from 'vue';
import axios from 'axios';

const activeTab = ref('certificates');
const unverifiedCertificates = ref([]);
const verifiedCertificates = ref([]);

const isImage = (path) => /\.(jpeg|jpg|png)$/i.test(path);
const fullUrl = (path) => window.location.origin + '/storage/' + path.replace(/^storage\//, '');

const formatDate = (date) => new Date(date).toLocaleDateString('uk-UA');

const moderate = async (cert) => {
    try {
        await axios.post(`/api/admin/factories/${cert.id}/moderate-certificate`, {
            status: cert.certificate_status,
            expiration_date: cert.certificate_expiration,
        });
        alert('Сертифікат успішно оновлено.');
        loadUnverified();
    } catch (err) {
        console.error('❌ Помилка оновлення сертифіката', err);
        alert('Не вдалося оновити сертифікат.');
    }
};

const loadUnverified = async () => {
    try {
        const res = await axios.get('/api/admin/factories-with-certificates');
        // фильтруем только с сертификатами в статусе pending
        unverifiedCertificates.value = res.data.filter(cert => cert.certificate_status === 'pending');
    } catch (err) {
        console.error('❌ Помилка завантаження неварифікованих', err);
    }
};

const loadVerified = async () => {
    try {
        const res = await axios.get('/api/admin/factories-with-certificates');
        verifiedCertificates.value = res.data.filter(cert => cert.certificate_status === 'valid');
    } catch (err) {
        console.error('❌ Помилка завантаження верифікованих', err);
    }
};

watch(activeTab, (tab) => {
    if (tab === 'certificates') loadUnverified();
    else loadVerified();
});

onMounted(() => {
    loadUnverified();
});
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

.cert__bg {
    position: absolute;
    top: 10px;
    width: 100%;
    height: 140px;
    background-size: cover;
    z-index: 0;
}

.cert__top {
    position: absolute;
    top: 60px;
    right: 16px;
    z-index: 2;
}

.cert__role {
    background: white;
    padding: 8px 14px;
    border-radius: 12px;
    border: none;
    font-weight: 500;
    font-size: 13px;
    color: #333;
    box-shadow: 0 2px 6px rgba(0, 0, 0, 0.1);
    cursor: pointer;
}

.cert__card {
    width: 100%;
    max-width: 400px;
    background: #fff;
    border-radius: 20px 20px 0 0;
    box-shadow: 0 -2px 20px rgba(0, 0, 0, 0.1);
    z-index: 1;
    padding: 24px 16px;
}

.cert__tabs {
    display: flex;
    justify-content: space-between;
    margin-bottom: 24px;
}

.cert__tabs span {
    flex: 1;
    text-align: center;
    font-weight: 600;
    font-size: 15px;
    color: #aaa;
    cursor: pointer;
    padding-bottom: 6px;
    position: relative;
}

.cert__tabs .active {
    color: #000;
}

.cert__tabs .active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 20%;
    width: 60%;
    height: 2px;
    background: #3498db;
    border-radius: 4px;
}

.cert__list {
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.cert__item {
    background: #fff;
    border-radius: 16px;
    border: 1px solid #eee;
    padding: 16px;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.05);
    text-align: center;
}

.cert__item h3 {
    font-size: 16px;
    font-weight: 700;
    margin-bottom: 12px;
}

.cert__btn {
    background: #3498db;
    color: white;
    border: none;
    border-radius: 10px;
    padding: 16px 45px;
    font-size: 14px;
    font-weight: 600;
    cursor: pointer;
    margin-top: 10px;
}

.cert__placeholder {
    text-align: center;
    color: #777;
    margin-top: 40px;
}
</style>
