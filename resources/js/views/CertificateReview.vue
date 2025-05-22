<template>
    <div class="review">
        <div class="review__top">
            <button class="review__back" @click="goBack">← Повернутись назад</button>
        </div>

        <div class="review__image">
            <div class="review__stub">Сертифікат</div>
        </div>

        <div class="review__form">
            <label class="review__label">Вкажіть дату пригодності сертифікату</label>
            <div class="review__date">
                <select v-model="day"><option disabled>День</option><option v-for="d in 31" :key="d">{{ d }}</option></select>
                <select v-model="month">
                    <option disabled>Місяць</option>
                    <option v-for="(m, i) in months" :key="i" :value="m">{{ m }}</option>
                </select>
                <select v-model="year"><option disabled>Рік</option><option v-for="y in years" :key="y">{{ y }}</option></select>
            </div>

            <div class="review__actions">
                <button class="review__btn reject">Заблокувати</button>
                <button class="review__btn approve">Одобрити</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
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
</script>

<style scoped>
.review {
    padding: 0;
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
    background: #e0e0e0;
    height: 240px;
    display: flex;
    justify-content: center;
    align-items: center;
}

.review__stub {
    color: rgba(0, 0, 0, 0.2);
    font-size: 32px;
    font-weight: 600;
    transform: rotate(-30deg);
    user-select: none;
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
