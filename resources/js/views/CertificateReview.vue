<template>
    <div class="review">
        <div class="review__top">
            <button class="review__back" @click="goBack">← Повернутись назад</button>
        </div>

        <div class="review__image" v-if="factory">
            <iframe
                :src="`/storage/${factory.certificate_path}`"
                class="review__pdf"
                frameborder="0"
            ></iframe>
        </div>

        <div class="review__form" v-if="factory">
            <label class="review__label">Вкажіть дату пригодності сертифікату</label>
            <div class="review__date">
                <select v-model="day">
                    <option disabled>День</option>
                    <option v-for="d in 31" :key="d">{{ d }}</option>
                </select>
                <select v-model="month">
                    <option disabled>Місяць</option>
                    <option v-for="(m, i) in months" :key="i" :value="m">{{ m }}</option>
                </select>
                <select v-model="year">
                    <option disabled>Рік</option>
                    <option v-for="y in years" :key="y">{{ y }}</option>
                </select>
            </div>

            <div class="review__actions">
                <button class="review__btn reject" @click="reject">Заблокувати</button>
                <button class="review__btn approve" @click="approve">Одобрити</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import { useAdminFactoryStore } from '@/store/adminFactoryStore';
import axios from 'axios';

const router = useRouter();
const route = useRoute();
const adminStore = useAdminFactoryStore();
const factory = ref(null);

const goBack = () => router.back();

const day = ref('');
const month = ref('');
const year = ref('');

const months = [
    'Січень', 'Лютий', 'Березень', 'Квітень', 'Травень', 'Червень',
    'Липень', 'Серпень', 'Вересень', 'Жовтень', 'Листопад', 'Грудень'
];
const currentYear = new Date().getFullYear();
const years = Array.from({ length: 10 }, (_, i) => currentYear + i);

const approve = async () => {
    if (!day.value || !month.value || !year.value) return alert('Вкажіть повну дату');

    const monthIndex = months.indexOf(month.value) + 1;
    const formattedMonth = String(monthIndex).padStart(2, '0');
    const formattedDay = String(day.value).padStart(2, '0');
    const verifiedUntil = `${year.value}-${formattedMonth}-${formattedDay}`;

    await adminStore.approveFactory(route.params.id, verifiedUntil);
    goBack();
};

const reject = async () => {
    await adminStore.rejectFactory(route.params.id);
    goBack();
};

onMounted(async () => {
    const { data } = await axios.get(`/api/factories/${route.params.id}`);
    factory.value = data;
});
</script>

<style scoped>
.review {
    display: flex;
    flex-direction: column;
    min-height: 100vh;
    max-width: var(--maxw);
    margin: 0 auto;
    background: var(--bg);
}
.review__top { padding: calc(20px + env(safe-area-inset-top, 0px)) 16px 8px; }
.review__back {
    background: var(--surface); border: none; font-size: 14px; color: var(--ink-2); font-weight: 600;
    cursor: pointer; padding: 10px 16px; border-radius: var(--r-pill); box-shadow: var(--sh-xs);
}
.review__back:active { transform: scale(.98); }
.review__image { height: 400px; display: flex; justify-content: center; align-items: center; padding: 8px 16px; }
.review__pdf { width: 100%; height: 100%; border: none; border-radius: var(--r-lg); box-shadow: var(--sh-sm); background: var(--surface); }
.review__form { padding: 20px 16px; display: flex; flex-direction: column; gap: 16px; }
.review__label { font-size: 14px; font-weight: 700; color: var(--ink); }
.review__date { display: flex; gap: 10px; }
.review__date select {
    flex: 1; padding: 13px 12px; font-size: 15px; border-radius: var(--r-sm);
    border: 1.5px solid var(--line); background: var(--surface); color: var(--ink); appearance: none; outline: none;
}
.review__date select:focus { border-color: var(--aqua-400); box-shadow: 0 0 0 4px var(--tap); }
.review__actions { display: flex; justify-content: space-between; gap: 12px; margin-top: 12px; }
.review__btn {
    flex: 1; padding: 15px; font-size: 15px; font-weight: 700; border-radius: var(--r-sm);
    border: none; cursor: pointer; transition: transform .15s var(--ease);
}
.review__btn:active { transform: scale(.98); }
.review__btn.reject { background: var(--danger-bg); color: var(--danger); }
.review__btn.approve { background: var(--grad-aqua); color: white; box-shadow: var(--sh-aqua); }
</style>
