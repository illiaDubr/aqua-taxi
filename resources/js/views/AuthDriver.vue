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
                <form @submit.prevent="submitForm" class="auth__form" :key="activeTab">
                    <input class="auth__input" type="email" placeholder="Ваша пошта*" v-model="email" required />
                    <input class="auth__input" v-if="activeTab === 'register'" type="tel" placeholder="Ваш номер телефону*" v-model="phone" required />
                    <input class="auth__input" v-if="activeTab === 'register'" type="text" placeholder="Ім’я" v-model="name" required />
                    <input class="auth__input" v-if="activeTab === 'register'" type="text" placeholder="Прізвище" v-model="surname" required />
                    <div class="auth__password-wrapper">
                        <input
                            class="auth__input"
                            :type="showPassword ? 'text' : 'password'"
                            :placeholder="activeTab === 'register' ? 'Ваш пароль*' : 'Пароль*'"
                            v-model="password"
                            required
                        />
                        <span class="auth__eye-icon" @click="showPassword = !showPassword">
    <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#999" viewBox="0 0 24 24"><path d="M12 5c-7.633 0-12 6.5-12 6.5s4.367 6.5 12 6.5 12-6.5 12-6.5-4.367-6.5-12-6.5zm0 11c-2.485 0-4.5-2.239-4.5-5s2.015-5 4.5-5 4.5 2.239 4.5 5-2.015 5-4.5 5zm0-8c-1.657 0-3 1.567-3 3s1.343 3 3 3 3-1.567 3-3-1.343-3-3-3z"/></svg>
    <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#999" viewBox="0 0 24 24"><path d="M2.293 2.293l19.414 19.414-1.414 1.414-2.387-2.387c-1.841.773-3.875 1.266-5.906 1.266-7.633 0-12-6.5-12-6.5 1.337-1.989 3.267-4.129 5.837-5.58l-2.544-2.544 1.414-1.414zm5.163 5.163l1.729 1.729c-.118.282-.185.594-.185.915 0 1.657 1.343 3 3 3 .321 0 .633-.067.915-.185l1.729 1.729c-.81.303-1.676.456-2.644.456-2.485 0-4.5-2.239-4.5-5 0-.968.153-1.834.456-2.644zm6.462-1.066c.795.229 1.553.539 2.271.924l-1.511 1.511c-.226-.063-.462-.098-.707-.098-1.657 0-3 1.343-3 3 0 .245.035.481.098.707l-1.511 1.511c-.385-.718-.695-1.476-.924-2.271.81-1.307 1.964-2.461 3.384-3.384z"/></svg>
  </span>
                    </div>


                    <label v-if="activeTab === 'register'" class="auth__checkbox">
                        <input type="checkbox" v-model="agree" />
                        <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                    </label>

                    <button type="submit" class="auth__submit">
                        {{ activeTab === 'register' ? 'Наступний крок' : 'Увійти' }}
                    </button>
                </form>
            </transition>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import logo from '@/assets/logo2.png';

const router = useRouter();

const activeTab = ref('register');
const email = ref('');
const phone = ref('');
const password = ref('');
const name = ref('');
const surname = ref('');
const agree = ref(false);
const showPassword = ref(false);

axios.defaults.withCredentials = true;

const submitForm = async () => {
    try {
        await axios.get('/sanctum/csrf-cookie');

        if (activeTab.value === 'register') {
            if (!agree.value) {
                alert('Потрібно погодитись з договором оферти');
                return;
            }

            await axios.post('/api/driver/register', {
                email: email.value,
                phone: phone.value,
                password: password.value,
                name: name.value,
                surname: surname.value
            });

            alert('✅ Реєстрація успішна. Увійдіть');
            activeTab.value = 'login';
        } else {
            const res = await axios.post('/api/driver/login', {
                email: email.value,
                password: password.value,
            });

            if (res.status === 200) {
                localStorage.setItem('driver_token', res.data.token);
                localStorage.setItem('driver_balance', res.data.balance);
                router.push('/ordersDrive');
            }
        }
    } catch (error) {
        console.error(error);
        alert('❌ Помилка при відправці даних. Перевірте введене та спробуйте ще раз.');
    }
};
</script>


<style scoped>
.auth__password-wrapper {
    position: relative;
}

.auth__password-wrapper input {
    width: 100%;
    padding-left: 5px !important;
    padding-right: 0px !important; /* место для глазика */
}

.auth__eye-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
}
body {
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    padding: 0;
}
.auth {
    position: relative;
    min-height: 100vh;
    padding: 60px 0px 0px 0px;
    background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 60%);
    display: flex;
    flex-direction: column;
    align-items: center;
}
.auth__bg {
    position: absolute;
    top: 180px;
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

.auth__input {
    width: 100%;
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
.fade-enter-active,
.fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from,
.fade-leave-to {
    opacity: 0;
}
</style>
