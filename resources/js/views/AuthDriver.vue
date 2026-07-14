<template>
  <div class="auth">
    <div class="auth__bg" />
    <div class="auth__top">
      <img :src="logo" alt="logo" class="auth__logo" />
    </div>

    <div class="auth__card">
      <div class="auth__tabs">
        <span :class="{ active: activeTab === 'register' }" @click="switchTab('register')">Реєстрація</span>
        <span :class="{ active: activeTab === 'login' }" @click="switchTab('login')">Вхід</span>
      </div>

      <transition name="fade" mode="out-in">
        <form
          autocomplete="off"
          @submit.prevent="submitForm"
          class="auth__form"
          :key="activeTab"
          novalidate
        >
          <input
            class="auth__input"
            type="email"
            placeholder="Ваша пошта*"
            v-model.trim="email"
            required
            :disabled="loading"
          />

          <input
            v-if="activeTab === 'register'"
            class="auth__input"
            type="tel"
            placeholder="Ваш номер телефону*"
            v-model.trim="phone"
            required
            :disabled="loading"
          />

          <input
            v-if="activeTab === 'register'"
            class="auth__input"
            type="text"
            placeholder="Ім’я"
            v-model.trim="name"
            :disabled="loading"
          />

          <input
            v-if="activeTab === 'register'"
            class="auth__input"
            type="text"
            placeholder="Прізвище"
            v-model.trim="surname"
            :disabled="loading"
          />

          <div class="auth__password-wrapper">
            <input
              class="auth__input"
              :type="showPassword ? 'text' : 'password'"
              :placeholder="activeTab === 'register' ? 'Ваш пароль*' : 'Пароль*'"
              v-model="password"
              required
              :disabled="loading"
            />
            <button type="button" class="auth__eye-icon" @click="showPassword = !showPassword" :disabled="loading" aria-label="toggle password">
              <svg v-if="showPassword" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#999" viewBox="0 0 24 24"><path d="M12 5c-7.633 0-12 6.5-12 6.5s4.367 6.5 12 6.5 12-6.5 12-6.5-4.367-6.5-12-6.5zm0 11c-2.485 0-4.5-2.239-4.5-5s2.015-5 4.5-5 4.5 2.239 4.5 5-2.015 5-4.5 5zm0-8c-1.657 0-3 1.567-3 3s1.343 3 3 3 3-1.567 3-3-1.343-3-3-3z"/></svg>
              <svg v-else xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="#999" viewBox="0 0 24 24"><path d="M2.293 2.293l19.414 19.414-1.414 1.414-2.387-2.387c-1.841.773-3.875 1.266-5.906 1.266-7.633 0-12-6.5-12-6.5 1.337-1.989 3.267-4.129 5.837-5.58l-2.544-2.544 1.414-1.414zm5.163 5.163l1.729 1.729c-.118.282-.185.594-.185.915 0 1.657 1.343 3 3 3 .321 0 .633-.067.915-.185l1.729 1.729c-.81.303-1.676.456-2.644.456-2.485 0-4.5-2.239-4.5-5 0-.968.153-1.834.456-2.644zm6.462-1.066c.795.229 1.553.539 2.271.924l-1.511 1.511c-.226-.063-.462-.098-.707-.098-1.657 0-3 1.343-3 3 0 .245.035.481.098.707l-1.511 1.511c-.385-.718-.695-1.476-.924-2.271.81-1.307 1.964-2.461 3.384-3.384z"/></svg>
            </button>
          </div>

          <label v-if="activeTab === 'register'" class="auth__checkbox">
            <input type="checkbox" v-model="agree" :disabled="loading" />
            <span>
              Реєструючись, ви погоджуєтесь з
              <button type="button" class="auth__link" @click="showOfferModal = true">договором оферти</button>
            </span>
          </label>

          <button type="submit" class="auth__submit" :disabled="loading">
            {{ loading ? 'Виконується…' : (activeTab === 'register' ? 'Наступний крок' : 'Увійти') }}
          </button>

          <p v-if="error" class="err" role="alert">{{ error }}</p>
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
const password = ref('');
const name = ref('');
const surname = ref('');
const agree = ref(false);
const showPassword = ref(false);
const loading = ref(false);
const error = ref('');

// Константы localStorage
const LS_TOKEN_KEY = 'driver_token';
const LS_BALANCE_KEY = 'driver_balance';

// Инициализация axios заголовка при загрузке SPA
(function initAxiosFromStorage() {
  try {
    const saved = localStorage.getItem(LS_TOKEN_KEY);
    if (saved) {
      axios.defaults.headers.common['Authorization'] = `Bearer ${saved}`;
    }
  } catch (e) {
    // ignore
  }
})();

function switchTab(tab) {
  activeTab.value = tab;
  error.value = '';
  // Очистка полей при переключении таба
  password.value = '';
  if (tab === 'register') {
    // keep email maybe, but clear others
    phone.value = '';
    name.value = '';
    surname.value = '';
    agree.value = false;
  }
}

/**
 * Вспомогательная функция для сохранения токена и установки axios header
 */
function saveDriverToken(token, balance = null) {
  try {
    localStorage.setItem(LS_TOKEN_KEY, token);
    if (balance !== null) localStorage.setItem(LS_BALANCE_KEY, String(balance));
    axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;
    // пометка активного токена если надо
    localStorage.setItem('active_token', LS_TOKEN_KEY);
  } catch (e) {
    console.warn('Failed to save token', e);
  }
}

async function submitForm() {
  if (loading.value) return;
  error.value = '';
  loading.value = true;

  try {
    if (activeTab.value === 'register') {
      if (!agree.value) {
        error.value = 'Потрібно погодитись з договором оферти';
        loading.value = false;
        return;
      }

      // Клиентская валидация
      if (!email.value || !password.value || !phone.value) {
        error.value = 'Заповніть обов\'язкові поля';
        loading.value = false;
        return;
      }

      await axios.post('/api/driver/register', {
        email: email.value.trim(),
        phone: phone.value.trim(),
        password: password.value,
        name: name.value.trim(),
        surname: surname.value.trim(),
      });

      alert('✅ Реєстрація успішна. Увійдіть.');
      activeTab.value = 'login';
      loading.value = false;
      return;
    }

    // LOGIN
    if (!email.value || !password.value) {
      error.value = 'Вкажіть email та пароль';
      loading.value = false;
      return;
    }

    const res = await axios.post('/api/driver/login', {
      email: email.value.trim(),
      password: password.value,
    });

    const token = res.data?.token;
    if (!token) throw new Error('Токен не повернуто сервером');

    const balance = res.data?.balance ?? null;
    saveDriverToken(token, balance);

    // Переход на маршрут после авторизации
    router.push('/ordersDrive');
  } catch (e) {
    console.error(e);
    // аккуратно парсим разные варианты ошибок
    if (e.response && e.response.data) {
      error.value = e.response.data.message || JSON.stringify(e.response.data);
    } else {
      error.value = e.message || 'Помилка авторизації. Перевірте дані.';
    }
  } finally {
    loading.value = false;
  }
}
</script>

<!-- Стилі auth* централізовані в design-system.css -->
