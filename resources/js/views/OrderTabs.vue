<template>
    <div class="orders">
        <div class="orders__header">
            <img :src="city" alt="background" class="orders__bg" />
            <div class="orders__top-bar">
                <div class="orders__burger">☰</div>
                <div class="orders__balance">
                    <span>0.00грн</span>
                    <button>＋</button>
                </div>
            </div>
        </div>

        <div class="orders__tabs">
            <span :class="{ active: activeTab === 'active' }" @click="activeTab = 'active'">Активні замовлення</span>
            <span :class="{ active: activeTab === 'new' }" @click="activeTab = 'new'">Нові замовлення</span>
        </div>

        <div class="orders__content" v-if="activeTab === 'active'">
            <div class="orders__alert">
                <p>❗ Для прийняття замовлень потрібно ввімкнути повідомлення від Aqua Taxi</p>
            </div>

            <h1 class="orders__title">
                Щоб розмістити послугу,<br />
                поповніть баланс на 100грн
            </h1>

            <a href="#" class="orders__notify">Ввімкнути повідомлення</a>

            <button class="orders__pay">Поповнити баланс</button>

            <!-- 🔵 Новая кнопка -->
            <button class="orders__map" @click="goToMap">Набрати воду</button>
        </div>

        <div class="orders__content" v-else>
            <p>Тут будуть нові замовлення</p>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import city from '@/assets/city.png';

const activeTab = ref('active');
const router = useRouter();

const goToMap = () => {
    router.push('/map');
};
</script>


<style scoped>
.orders {
    font-family: 'Montserrat', sans-serif;
    background: #f8f9fa;
    min-height: 100vh;
    overflow-x: hidden;
}
.orders__map {
    background: #00aaff;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 14px 24px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
    margin-top: 12px;
}

.orders__header {
    position: relative;
    background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 100%);
    padding: 16px;
}

.orders__bg {
    position: absolute;
    top: 60px;
    left: 0;
    width: 100%;
    height: 120px;
    object-fit: cover;
    pointer-events: none;
    z-index: 0;
}

.orders__top-bar {
    display: flex;
    justify-content: space-between;
    align-items: center;
    position: relative;
    z-index: 1;
    margin-bottom: 32px;
}

.orders__burger {
    font-size: 24px;
    cursor: pointer;
}

.orders__balance {
    display: flex;
    align-items: center;
    background: #fff;
    border-radius: 12px;
    padding: 6px 12px;
    box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
    gap: 8px;
    font-weight: 500;
}

.orders__balance button {
    background: #00aaff;
    border: none;
    color: white;
    border-radius: 7px;
    padding: 10px 3px;
    font-size: 16px;
    line-height: 0;
    cursor: pointer;
    text-align: center;
}

.orders__tabs {
    display: flex;
    justify-content: space-around;
    padding: 12px 0;
    background: white;
    border-top-left-radius: 24px;
    border-top-right-radius: 24px;
    margin: 24px 0px;
    z-index: 1;
    position: relative;
}

.orders__tabs span {
    font-size: 16px;
    font-weight: 600;
    color: #ccc;
    padding: 8px 0;
    cursor: pointer;
}

.orders__tabs .active {
    color: #3498db;
    border-bottom: 2px solid #3498db;
}

.orders__content {
    padding: 24px;
    height: 70vh;
    text-align: center;
    display: flex;
    flex-direction: column;
    justify-content: space-around;
}

.orders__alert {
    border: 1px solid #e74c3c;
    background: #fff0f0;
    color: #c0392b;
    padding: 12px;
    border-radius: 12px;
    margin-bottom: 24px;
    font-size: 14px;
}

.orders__title {
    font-size: 28px;
    font-weight: 700;
    margin-bottom: 16px;
}

.orders__notify {
    text-decoration: none;
    color: #0095FF;
    display: inline-block;
    margin-bottom:-30px;
    font-size: 14px;

}

.orders__pay {
    background: #3498db;
    color: white;
    border: none;
    border-radius: 12px;
    padding: 14px 24px;
    font-weight: 600;
    font-size: 15px;
    cursor: pointer;
}
</style>
