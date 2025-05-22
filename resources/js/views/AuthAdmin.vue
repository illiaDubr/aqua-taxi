<template>
    <div class="auth">
        <div class="auth__bg"></div>

        <div class="auth__top">
            <img src="@/assets/logo 2.png" alt="logo" class="auth__logo" />
        </div>

        <div class="auth__card">
            <h2 class="auth__tabs single">Вхід в адмін панель</h2>

            <form @submit.prevent="submitForm" class="auth__form">
                <input
                    type="email"
                    placeholder="Ваша пошта*"
                    v-model="email"
                    required
                />
                <input
                    type="password"
                    placeholder="Пароль*"
                    v-model="password"
                    required
                />

                <button type="submit" class="auth__submit">Увійти</button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const email = ref('');
const password = ref('');
const router = useRouter();

const submitForm = () => {
    console.log('🛡️ Адмін логін:', { email: email.value, password: password.value });

    router.push('/admin'); // переход после логина
};
</script>
<style>
body {
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    padding: 0;
}
.auth {
    position: relative;
    min-height: 100vh;
    background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 60%);
    display: flex;
    justify-content: center;
    flex-direction: column;
    align-items: center;
    overflow: hidden;
}
.auth__bg {
    position: absolute;
    top: 180px; /* регулируй чтобы опустить фон ниже */
    left: 0;
    width: 100%;
    height: 200px;
    background: url('@/assets/city.png') no-repeat center top;
    background-size: cover;
    z-index: 0;
    pointer-events: none;
}
.auth__top,
.auth__card {
    position: relative;
    z-index: 1;
}

.auth__top {
    padding-top: 0px;
    margin-bottom: 50px;
}

.auth__logo {
    width: 96px;
    height: 96px;
    border-radius: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.auth__card {
    width: 100%;
    max-width: 360px;
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.auth__tabs {

    display: flex;
    justify-content: space-around;
    margin-bottom: 48px;
}

.auth__tabs span {
    font-size: 18px;
    font-weight: 600;
    color: #ccc;
    cursor: pointer;
    padding-bottom: 4px;
    transition: all 0.2s ease;
}

.auth__tabs .active {
    font-size: 24px;
    color: #3498db;
    border-bottom: 2px solid #3498db;
}

.auth__form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: all 0.2s ease;
}

.auth__form input {
    padding: 14px;
    font-size: 15px;
    border: 1px solid #ccc;
    border-radius: 12px;
    outline: none;
}

.auth__checkbox {
    display: flex;
    align-items: flex-start;
    font-size: 13px;
    color: #7f8c8d;
    gap: 8px;
}

.auth__checkbox input {
    width: 16px;
    height: 16px;
    margin-top: 2px;
}

.auth__checkbox a {
    color: #3498db;
    text-decoration: underline;
}

.auth__submit {
    padding: 14px;
    font-size: 15px;
    font-weight: 600;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}

/* Анимация */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
