<template>
    <div class="order">
        <div class="order__header">
            <img :src="city" alt="background" class="order__bg" />
        </div>

        <div class="order__tabs">
            <span :class="{ active: activeTab === 'order' }" @click="activeTab = 'order'">Замовити</span>
            <span :class="{ active: activeTab === 'active' }" @click="activeTab = 'active'">Активні<br> замовлення</span>
        </div>

        <div class="order__content" v-if="activeTab === 'order'">
            <div v-for="(product, index) in products" :key="index" class="order__card">
                <img :src="product.image" alt="product" class="order__image" />
                <div class="order__info">
                    <h3 class="order__title">{{ product.title }}</h3>
                    <p>{{ product.price }} грн</p>
                    <button class="order__btn" @click="goToOrderForm(product)">
                        Замовити
                    </button>
                </div>
            </div>
<!--            <button class="order__create">Робота бариста</button>-->
        </div>

        <div class="order__content" v-else>
            <p class="order__placeholder">Тут будуть ваші активні замовлення</p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import water1 from '@/assets/1.png';
import city from '@/assets/city.png';
import water2 from '@/assets/2.png';
import { useRouter } from 'vue-router';
const router = useRouter();

const goToOrderForm = (product) => {
    router.push({ name: 'orderForm', params: { productId: encodeURIComponent(product.title) } });
};


const activeTab = ref('order');

const products = ref([
    {
        title: 'Глибокого\nочищення, 19л',
        price: 120,
        image: water1,
    },
    {
        title: 'Срібна вода, 19л',
        price: 130,
        image: water2,
    },
]);
</script>

<style>
body {
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    padding: 0;
}
.order__title {
    font-size: 15px;
    font-weight: 600;
    margin: 0;
    white-space: pre-line;
    text-align: center;
}
.order {
    min-height: 100vh;
    background-color: #f9f9f9;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.order__header {
    position: relative;
    height: 140px;
    width: 100%;
    background-color: #00aaff;
    overflow: hidden;
}

.order__bg {
    position: absolute;
    top: 16px;
    left: 0;
    width: 100%;
    height: 100%;
    object-fit: contain;
    pointer-events: none;
    z-index: 1;
}

.order__tabs {
    display: flex;
    justify-content: space-around;
    width: 100%;
    background: white;
    padding: 32px 0;
    border-radius: 16px 16px 0 0;
    margin-top: -24px;
    position: relative;
    z-index: 2;
    box-shadow: 0 -4px 12px rgba(0, 0, 0, 0.1);
}

.order__tabs span {
    text-align: center;
    font-weight: 600;
    color: #ccc;
    font-size: 20px;
    cursor: pointer;
    padding-bottom: 6px;
}

.order__tabs .active {
    color: #3498db;
    border-bottom: 2px solid #3498db;
}

.order__content {
    width: 100%;
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 20px;
}

.order__card {
    height: 300px;
    background: white;
    border-radius: 16px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    display: flex;
    gap: 12px;
    padding: 12px;
    align-items: center;
}

.order__image {
    width: 150px;
    height: 220px;
    object-fit: contain;
}

.order__info {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 16px;
}

.order__info h3 {
    font-size: 20px;
    font-weight: 700;
    text-align: center;
    margin: 0;
}

.order__info p {
    font-size: 20px;
    font-weight: 500;
    color: #555;
    margin: 0;
}

.order__btn {
    font-size: 16px;
    background-color: #3498db;
    color: white;
    border-radius: 10px;
    border: none;
    padding: 16px 60px;

}

.order__create {
    width: 100%;
    padding: 16px;
    background-color: #3498db;
    color: white;
    font-size: 16px;
    font-weight: 600;
    border: none;
    border-radius: 12px;
    cursor: pointer;
    margin: 12px 5px;
    overflow: hidden;
}

.order__placeholder {
    text-align: center;
    color: #777;
    margin-top: 40px;
}
</style>
