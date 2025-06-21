<template>
    <div class="auth">
        <div class="auth__bg"></div>
        <div class="auth__top">
            <img :src="logo" alt="logo" class="auth__logo" />
        </div>

        <div class="auth__card">
            <div class="auth__tabs">
                <span :class="{ active: activeTab === 'register' }" @click="activeTab = 'register'">Реєстрація</span>
                <span :class="{ active: activeTab === 'login' }" @click="activeTab = 'login'">Вхід</span>
            </div>

            <transition name="fade" mode="out-in">
                <form @submit.prevent="activeTab === 'register' ? handleRegister() : handleLogin()" class="auth__form" :key="step">
                    <template v-if="activeTab === 'register'">
                        <!-- STEP 1 -->
                        <div v-if="step === 1" class="auth__form">
                            <input type="email" placeholder="Ваша пошта*" v-model="email" required />
                            <input type="tel" placeholder="Ваш номер телефону*" v-model="phone" required />
                            <input type="password" placeholder="Ваш пароль*" v-model="password" required />
                            <input type="text" placeholder="Ваш вебсайт*" v-model="website" required />
                            <label class="auth__checkbox">
                                <input type="checkbox" v-model="agree" />
                                <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                            </label>
                            <button type="button" class="auth__submit" @click="goToStep2">Наступний крок</button>
                        </div>

                        <!-- STEP 2 -->
                        <div v-else class="auth__form">
                            <div class="upload-wrapper">
<!--                                <label class="upload-label">-->
<!--                                    <span class="attacher">-->
<!--                                    <i class="icon attach"></i>-->
<!--                                    Прикріпіть сертифікат якості*</span>-->
<!--                                </label>-->
                                <input type="file" name="certificate" accept="image/png, image/jpeg" @change="handleFile"/>
                                <p class="upload-desc">Завантажте фото сертифіката якості у форматі JPG або PNG</p>


                            </div>

                            <input type="text" placeholder="Ваша адреса складу" v-model="warehouse" required />
                            <input type="text" placeholder="Додайте види води" v-model="waterTypes" />
                            <label class="auth__checkbox">
                                <input type="checkbox" v-model="agree" />
                                <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                            </label>
                            <button type="submit" class="auth__submit">Завершити реєстрацію</button>
                        </div>
                    </template>

                    <!-- ВХІД -->
                    <template v-else>
                        <input type="email" placeholder="Ваша пошта*" v-model="email" required />
                        <input type="password" placeholder="Пароль*" v-model="password" required />
                        <button type="submit" class="auth__submit">Увійти</button>
                    </template>
                </form>
            </transition>
        </div>
    </div>
</template>


<script setup>
import logo from '@/assets/logo2.png'
import { ref } from 'vue';
import { useRouter } from 'vue-router';

const router = useRouter();
const activeTab = ref('register');
const step = ref(1);

const email = ref('');
const phone = ref('');
const password = ref('');
const website = ref('');
const warehouse = ref('');
const waterTypes = ref('');
const agree = ref(false);
const file = ref(null);

import axios from 'axios';

const handleRegister = async () => {
    if (step.value === 1) {
        goToStep2();
        return;
    }

    if (!warehouse.value || !file.value || !agree.value) {
        alert('Будь ласка, заповніть усі поля та погодьтесь з умовами.');
        return;
    }
    if (!file.value) {
        alert("Будь ласка, додайте фото сертифіката.");
        return;
    }

    const formData = new FormData();
    formData.append('email', email.value);
    formData.append('phone', phone.value);
    formData.append('password', password.value);
    formData.append('website', website.value);
    formData.append('warehouse_address', warehouse.value);
    formData.append('water_types', waterTypes.value);
    formData.append('certificate', file.value);

    try {
        const res = await axios.post('/api/factory/register', formData);
        alert('Реєстрація успішна!');
        activeTab.value = 'login';
    } catch (err) {
        console.error(err);
        alert('Помилка при реєстрації');
    }
};
const goToStep2 = () => {
    if (!email.value || !phone.value || !password.value || !website.value || !agree.value) {
        alert('Будь ласка, заповніть усі поля та підтвердіть згоду.');
        return;
    }
    step.value = 2;
};

const handleFile = (e) => {
    file.value = e.target.files[0];
};


const handleLogin = async () => {
    if (!email.value || !password.value) {
        alert('Введіть пошту та пароль');
        return;
    }

    try {
        const res = await axios.post('/api/factory/login', {
            email: email.value,
            password: password.value
        });

        const token = res.data.token;
        const factory = res.data.user; // предполагаем, что возвращается объект пользователя

        localStorage.setItem('token', token);
        localStorage.setItem('factory', JSON.stringify(factory)); // сохраняем данные производителя
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        alert('Успішний вхід!');
        router.push('/factory-page');
    } catch (err) {
        console.error(err);
        alert('Невірна пошта або пароль');
    }
};

</script>


<style>
body {
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    padding: 0;
}
.attacher{
    padding: 14px;
    font-size: 15px;
    border: 1px solid #ccc;
    border-radius: 12px;
    outline: none;
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
