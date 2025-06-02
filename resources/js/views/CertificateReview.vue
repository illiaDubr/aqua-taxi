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
    background: #fff;
}

.review__top {
    padding: 20px 16px 8px;
}

.review__back {
    background: none;
    border: none;
    font-size: 15px;
    color: #333;
    font-weight: 500;
    cursor: pointer;
}

.review__image {
    height: 400px;
    display: flex;
    justify-content: center;
    align-items: center;
    padding: 0 16px;
}

.review__pdf {
    width: 100%;
    height: 100%;
    border: none;
    border-radius: 12px;
    box-shadow: 0 2px 12px rgba(0,0,0,0.1);
}

.review__form {
    padding: 20px 16px;
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.review__label {
    font-size: 14px;
    font-weight: 600;
    color: #333;
}

.review__date {
    display: flex;
    gap: 10px;
}

.review__date select {
    flex: 1;
    padding: 12px;
    font-size: 14px;
    border-radius: 12px;
    border: 1px solid #ccc;
    background: #f9f9f9;
    appearance: none;
}

.review__actions {
    display: flex;
    justify-content: space-between;
    gap: 12px;
    margin-top: 12px;
}

.review__btn {
    flex: 1;
    padding: 14px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 12px;
    border: none;
    cursor: pointer;
}

.review__btn.reject {
    background: white;
    border: 1.5px solid #e74c3c;
    color: #e74c3c;
}

.review__btn.approve {
    background: #3498db;
    color: white;
}
</style>
