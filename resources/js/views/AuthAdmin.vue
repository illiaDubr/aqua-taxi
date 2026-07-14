<template>
    <div class="auth">
        <div class="auth__bg"></div>

        <div class="auth__top">
            <img :src="logo" alt="logo" class="auth__logo" />
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
import logo from '@/assets/logo2.png';
import { useAdminStore } from '@/store/admin';

const email = ref('');
const password = ref('');
const router = useRouter();
const admin = useAdminStore();

const submitForm = async () => {
    try {
        await admin.login(email.value, password.value);
        router.push('/admin'); // переход в панель
    } catch (e) {
        alert('Невірна пошта або пароль');
    }
};
</script>
<style>
/* Базові стилі auth* централізовані в design-system.css.
   Тут лише специфіка адмінки: інпути задані через .auth__form input. */
.auth__form input:not([type='checkbox']) {
    width: 100%;
    min-height: 52px;
    padding: 14px 16px;
    font-size: 16px;
    color: var(--ink);
    background: var(--surface);
    border: 1.5px solid var(--line);
    border-radius: var(--r-sm);
    outline: none;
    box-sizing: border-box;
    transition: border-color .18s var(--ease), box-shadow .18s var(--ease), background .18s;
}
.auth__form input:not([type='checkbox'])::placeholder { color: var(--faint); }
.auth__form input:not([type='checkbox']):focus {
    border-color: var(--aqua-400);
    box-shadow: 0 0 0 4px var(--tap);
    background: var(--aqua-50);
}
</style>
