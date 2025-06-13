<template>
    <div class="welcome">
        <img :src="illustrarion" alt="Welcome" class="welcome__img" />

        <div class="welcome__content">
            <h1 class="welcome__title">Вітаю! Радий бачити Вас!</h1>
            <p class="welcome__subtitle">Виберіть роль, ким ви хочете стати для Aqua Taxi</p>
        </div>

        <div class="welcome__buttons">
            <button class="welcome__btn" @click="goToAuthUser">Замовити доставку води</button>
            <button class="welcome__btn" @click="goToAuthDriver">Стати водієм Aqua Taxi</button>
            <button class="welcome__btn" @click="goToAuthFactory">Стати виробником</button>

        </div>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import { useAuthStore } from '@/store/auth';
import illustrarion from '@/assets/illustration.png'
const router = useRouter();
const auth = useAuthStore();

const selectRole = (role) => {
    auth.user.role = role;
    if (role === 'client') {
        router.push('/client');
    } else {
        router.push('/driver');
    }
};
    const goToAuthUser = () => {
    router.push({ name: 'authUser' });
};
    const goToAuthDriver = () => {
    router.push({ name: 'authdriver' });
};
const goToAuthFactory = () => {
    router.push({ name: 'authFactory' });
};

</script>

<style>
body {

    margin: 0;
    padding: 0;
    font-family: 'Montserrat', sans-serif;
}
.welcome {
 height: 100%;
    display: flex;
    justify-content: space-around;
    padding-bottom: 150px;
    padding-top: 50px;
    flex-direction: column;
    background-color: #ffffff;
    font-family: 'Montserrat', sans-serif;
    box-sizing: border-box;
    overflow: hidden;
}

.welcome__img {
    width: 100%;
    max-width: 100%;
    object-fit: contain;
    margin-top: 80px;
    margin-bottom: 32px;
}

.welcome__title {
    font-size: 28px;
    font-weight: 700;
    letter-spacing: -1.5px;
    color: #2c3e50;
    line-height: 100%;
    text-align: center;
    margin-bottom: 8px;
}

.welcome__subtitle {
    font-size: 15px;
    color: #7f8c8d;
    text-align: center;
    margin: 0 0 40px 0;
    padding: 0 24px;
}

.welcome__content {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
}

.welcome__buttons {
    margin: 0 auto;
    width: 296px;
    padding: 0 24px 32px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.welcome__btn {
    padding: 14px;
    font-size: 15px;
    font-weight: 600;
    border-radius: 12px;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
    background-color: white;
    color: #3498db;
    border: 2px solid #3498db;
}

.welcome__btn:hover {
    background-color: #3498db;
    color: white;
    border: 2px solid #3498db;
}





</style>
