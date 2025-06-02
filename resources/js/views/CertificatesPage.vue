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
                        <p>{{ cert.email }}</p>
                        <button class="cert__btn" @click="goToReview(cert.id)">
                            Переглянути сертифікат
                        </button>
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
                        <p>До: {{ cert.verified_until }}</p>
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
import { useRouter } from 'vue-router';
import axios from 'axios';

const router = useRouter();
const activeTab = ref('certificates');

const unverifiedCertificates = ref([]);
const verifiedCertificates = ref([]);

const goToReview = (id) => {
    router.push({ name: 'certificateReview', params: { id } });
};

const loadUnverified = async () => {
    try {
        const res = await axios.get('/api/factories?is_verified=0');
        unverifiedCertificates.value = Array.isArray(res.data.data)
            ? res.data.data
            : Array.isArray(res.data)
                ? res.data
                : [];
    } catch (err) {
        console.error('❌ Помилка завантаження неварифікованих', err);
    }
};

const loadVerified = async () => {
    try {
        const res = await axios.get('/api/factories?is_verified=1');
        verifiedCertificates.value = Array.isArray(res.data.data)
            ? res.data.data
            : Array.isArray(res.data)
                ? res.data
                : [];
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
}

.cert__placeholder {
    text-align: center;
    color: #777;
    margin-top: 40px;
}
</style>
