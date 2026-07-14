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
                    <input class="auth__input" type="email" placeholder="Ваша пошта*" v-model.trim="email" required />
                    <input v-if="activeTab === 'register'" class="auth__input" type="tel" placeholder="Ваш номер телефону*" v-model.trim="phone" required />
                    <input v-if="activeTab === 'register'" class="auth__input" type="text" placeholder="Ім’я" v-model.trim="name" required />
                    <input v-if="activeTab === 'register'" class="auth__input" type="text" placeholder="Прізвище" v-model.trim="surname" required />

                    <div class="auth__password-wrapper">
                        <input
                            class="auth__input"
                            :type="showPassword ? 'text' : 'password'"
                            :placeholder="activeTab === 'register' ? 'Ваш пароль*' : 'Пароль*'"
                            v-model="password"
                            required
                        />
                        <span class="auth__eye-icon" @click="showPassword = !showPassword">
              <!-- иконки оставил как у тебя -->
              <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#999" viewBox="0 0 24 24"><path d="M12 5c-7.633 0-12 6.5-12 6.5s4.367 6.5 12 6.5 12-6.5 12-6.5-4.367-6.5-12-6.5zm0 11c-2.485 0-4.5-2.239-4.5-5s2.015-5 4.5-5 4.5 2.239 4.5 5-2.015 5-4.5 5zm0-8c-1.657 0-3 1.567-3 3s1.343 3 3 3 3-1.567 3-3-1.343-3-3-3z"/></svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#999" viewBox="0 0 24 24"><path d="M2.293 2.293l19.414 19.414-1.414 1.414-2.387-2.387c-1.841.773-3.875 1.266-5.906 1.266-7.633 0-12-6.5-12-6.5 1.337-1.989 3.267-4.129 5.837-5.58l-2.544-2.544 1.414-1.414zm5.163 5.163l1.729 1.729c-.118.282-.185.594-.185.915 0 1.657 1.343 3 3 3 .321 0 .633-.067.915-.185l1.729 1.729c-.81.303-1.676.456-2.644.456-2.485 0-4.5-2.239-4.5-5 0-.968.153-1.834.456-2.644zm6.462-1.066c.795.229 1.553.539 2.271.924l-1.511 1.511c-.226-.063-.462-.098-.707-.098-1.657 0-3 1.343-3 3 0 .245.035.481.098.707l-1.511 1.511c-.385-.718-.695-1.476-.924-2.271.81-1.307 1.964-2.461 3.384-3.384z"/></svg>
            </span>
                    </div>

                  <label v-if="activeTab === 'register'" class="auth__checkbox">
  <input type="checkbox" v-model="agree" />
  <span>
    Реєструючись, ви погоджуєтесь з
    <button type="button" class="auth__link" @click="showOfferModal = true">договором оферти</button>
  </span>
</label>

                    <button type="submit" class="auth__submit">{{ activeTab === 'register' ? 'Наступний крок' : 'Увійти' }}</button>
                </form>
            </transition>
        </div>
        <ModalOffer :visible="showOfferModal" @close="showOfferModal = false" />

    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import logo from '@/assets/logo2.png';
import ModalOffer from '../views/ModalOffer.vue';
const showOfferModal = ref(false);

const router = useRouter();

const activeTab = ref('register');
const email = ref('');
const phone = ref('');
const name = ref('');
const surname = ref('');
const password = ref('');
const agree = ref(false);
const showPassword = ref(false);

const submitForm = async () => {
    if (activeTab.value === 'register') {
        if (!agree.value) {
            alert('Потрібно погодитись з договором оферти');
            return;
        }
        try {
            await axios.post('/api/user/register', {
                email: email.value.trim(),
                phone: phone.value.trim(),
                password: password.value,
                name: name.value.trim(),
                surname: surname.value.trim()
            });
            alert('✅ Реєстрація успішна. Увійдіть.');
            activeTab.value = 'login';
        } catch (e) {
            console.error(e);
            alert('Помилка реєстрації');
        }
    } else {
        try {
            const res = await axios.post('/api/user/login', {
                email: email.value.trim(),
                password: password.value
            });

            const token = res.data?.token;
            if (!token) {
                alert('Сервер не повернув токен');
                return;
            }
            localStorage.setItem('user_token', token);
            localStorage.setItem('active_token', 'user_token'); // <-- ключевой момент для интерцептора
            // axios.defaults.headers.common.Authorization = `Bearer ${token}`; // можно не ставить, если есть интерцептор

            router.push('/orders'); // или твой роут формы заказа
        } catch (e) {
            console.error(e);
            alert('Невірні дані для входу');
        }
    }
};
</script>

<!-- Стилі auth* централізовані в design-system.css -->
