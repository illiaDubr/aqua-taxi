<template>
    <div class="producer-orders">
        <!-- Верхняя панель -->
        <div class="producer-orders__topbar">
            <div class="status-toggle" :class="{ offline: !isOnline }" @click="toggleStatus">
                <span class="label">{{ isOnline ? 'Онлайн' : 'Оффлайн' }}</span>
                <div class="toggle-circle"></div>
            </div>
            <div class="balance-box">
                <span class="amount">0.00грн</span>
                <button class="plus">＋</button>
            </div>
        </div>
        <div class="bg">
        <!-- Табы -->
        <div class="tabs">
            <button :class="{ active: currentTab === 'active' }" @click="switchTab('active')">
                Активні замовлення
            </button>
            <button :class="{ active: currentTab === 'new' }" @click="switchTab('new')">
                Нові замовлення
            </button>
        </div>

        <!-- Алерт -->
        <div class="alert">
            <p>Для прийняття замовлень потрібно ввімкнути повідомлення від Aqua Taxi</p>
        </div>

        <!-- Контент -->
        <div class="content" v-if="currentTab === 'active'">
            <p class="content__text">Щоб розмістити послугу, поповніть баланс на 100грн</p>
            <button class="topup-button">Поповнити баланс</button>
        </div>

        <div class="content" v-else-if="currentTab === 'new'">
            <p class="content__text">Тут з’являться нові замовлення після активації</p>
            <button class="topup-button">Оновити список</button>
        </div>
    </div>
    </div>
</template>




<script setup>
import { ref, onMounted } from 'vue';

const currentTab = ref('active');
const factory = ref(null);

const switchTab = (tab) => {
    currentTab.value = tab;
};

const isOnline = ref(true);

const toggleStatus = () => {
    isOnline.value = !isOnline.value;
};

onMounted(() => {
    const storedFactory = localStorage.getItem('factory');
    if (storedFactory) {
        try {
            factory.value = JSON.parse(storedFactory);
        } catch (e) {
            console.warn('⚠️ Некорректный JSON в localStorage', e);
        }
    }
});
</script>

<style scoped>
.bg{
    margin: 0 auto;
    background: white;
    width: 100%;
    height: 100%;
    overflow: hidden;
    border-radius: 15px 15px 0px 0px;

}
.status-toggle {
    display: flex;
    align-items: center;
    background-color: #00c853;
    border-radius: 24px;
    padding: 10px 12px;
    cursor: pointer;
    transition: all 0.3s ease;
}

.status-toggle.offline {
    background-color: #9e9e9e;
}

.status-toggle .label {
    font-size: 14px;
    color: white;
}
.status-toggle.offline .label{
    transform: translateX(10px) scale(0.9);
    transition: transform 0.3s ease;
}

.status-toggle .toggle-circle {
    width: 12px;
    height: 12px;
    background-color: white;
    border-radius: 50%;
    transition: transform 0.3s ease;
}

.status-toggle.offline .toggle-circle {
    transform: translateX(-80px) scale(0.9);
}
.producer-orders {
    background: #00aaff;
    position: relative;
    z-index: 1;

    font-family: 'Montserrat', sans-serif;
}

.producer-orders__topbar {
    position: relative;
    z-index: 2;
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px;
    color: white;
}

.menu {
    font-size: 24px;
    cursor: pointer;
}

.status-toggle {
    display: flex;
    align-items: center;
    background-color: #00c853;
    border-radius: 24px;
    padding: 4px 12px;
    gap: 8px;
}

.status-toggle .label {
    font-size: 14px;
    color: white;
}

.status-toggle .toggle-circle {
    width: 12px;
    height: 12px;
    background-color: white;
    border-radius: 50%;
}

.balance-box {
    display: flex;
    align-items: center;
    background-color: white;
    border-radius: 20px;
    padding: 4px 10px;
    color: #333;
    gap: 4px;
}

.balance-box .amount {
    font-weight: 600;
    font-size: 14px;
}

.balance-box .plus {
    background: none;
    border: none;
    font-size: 18px;
    cursor: pointer;
    color: #007BFF;
}

.tabs {
    display: flex;
    padding: 10px;
    border-radius: 15px;
    justify-content: space-around;
    margin-top: 16px;
    border-bottom: 2px solid #eee;
}

.tabs button {
    flex: 1;
    padding: 12px;
    background: none;
    border: none;
    font-size: 15px;
    font-weight: 500;
    color: #999;
    position: relative;
}

.tabs .active {
    color: #000;
}

.tabs .active::after {
    content: '';
    position: absolute;
    bottom: 0;
    left: 25%;
    right: 25%;
    height: 3px;
    background-color: #2196F3;
    border-radius: 4px;
}

.alert {
    background: #fff4f4;
    border: 1px solid #ffbcbc;
    padding: 12px;
    border-radius: 12px;
    color: #d32f2f;
    font-size: 14px;
    margin: 10px;
    text-align: center;
}

.content {
    text-align: center;
    margin-top: 24px;
}

.content__text {
    font-size: 18px;
    font-weight: bold;
    margin-bottom: 16px;
}

.topup-button {
    background-color: #2196F3;
    color: white;
    padding: 14px 24px;
    font-size: 16px;
    border: none;
    border-radius: 12px;
    cursor: pointer;

}
</style>
