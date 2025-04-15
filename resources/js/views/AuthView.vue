<template>
    <div class="auth">
        <h2>Вход в Aqua Taxi</h2>

        <form @submit.prevent="handleSubmit">
            <div v-if="step === 1">
                <input v-model="phone" type="text" placeholder="Введите номер телефона" />
                <button type="submit">Отправить код</button>
            </div>

            <div v-else>
                <input v-model="code" type="text" placeholder="Введите код из SMS" />
                <button type="submit">Подтвердить</button>
            </div>
        </form>

        <div v-if="error" class="error">{{ error }}</div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { requestCode, verifyCode } from '../api/auth';
import { useRouter } from 'vue-router';
import { useAuthStore } from '../store/auth';

const phone = ref('');
const code = ref('');
const step = ref(1);
const error = ref(null);

const router = useRouter();
const authStore = useAuthStore();

const handleSubmit = async () => {
    error.value = null;
    try {
        if (step.value === 1) {
            await requestCode(phone.value);
            step.value = 2;
        } else {
            const res = await verifyCode(phone.value, code.value);
            authStore.login(res.data.access_token, res.data.user);
            router.push('/'); // redirect to dashboard (позже)
        }
    } catch (e) {
        error.value = e.response?.data?.message || 'Ошибка';
    }
};
</script>

<style scoped>
.auth {
    max-width: 400px;
    margin: 50px auto;
    display: flex;
    flex-direction: column;
    gap: 1rem;
}
input {
    padding: 10px;
    font-size: 1rem;
}
button {
    padding: 10px;
    background: #42b983;
    color: white;
    border: none;
    cursor: pointer;
}
.error {
    color: red;
}
</style>
