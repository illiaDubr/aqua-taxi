<template>
  <div v-if="visible" class="modal-overlay" @keydown.esc="$emit('close')">
    <div class="modal" role="dialog" aria-modal="true">
      <h2>Поповнення балансу</h2>

      <input
        type="number"
        v-model.number="amount"
        placeholder="Сума в грн"
        class="modal-input"
        inputmode="decimal"
        min="1"
        @keyup.enter="submitPayment"
        :disabled="isLoading"
      />

      <div class="modal-actions">
        <button
          @click="submitPayment"
          class="btn-primary"
          :disabled="isLoading"
        >
          {{ isLoading ? 'Обробка...' : 'Поповнити' }}
        </button>

        <button @click="$emit('close')" class="btn-secondary" :disabled="isLoading">
          Назад
        </button>
      </div>

      <p v-if="error" class="error" role="alert">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
const props = defineProps({ visible: Boolean });
const emit = defineEmits(['close', 'success', 'unauth']);

const amount = ref(null);
const isLoading = ref(false);
const error = ref('');

/**
 * Модалка не делает локальную авторизационную проверку.
 * Сервер возвращает 401/403 при отсутствии/некорректном токене.
 */
async function submitPayment() {
  error.value = '';

  const value = Number(amount.value);
  if (!value || isNaN(value) || value <= 0) {
    error.value = 'Введіть коректну суму для поповнення';
    return;
  }

  isLoading.value = true;

  try {
    // Отправляем запрос — доверяем бэкенду авторизацию и валидацию
    const res = await axios.post('/api/driver/pay', { amount: value }, { timeout: 15000 });

    // Ожидаем структуру { url, params } или { url }
    const data = res.data || {};

    if (data.url && data.params) {
      // старый подход: POST-форма к iPay
      const form = document.createElement('form');
      form.method = 'POST';
      form.action = data.url;
      Object.entries(data.params).forEach(([k, v]) => {
        const input = document.createElement('input');
        input.type = 'hidden';
        input.name = k;
        input.value = String(v);
        form.appendChild(input);
      });
      document.body.appendChild(form);
      form.submit();

      // опционально эмитим success — родитель может обновить баланс
      emit('success', { amount: value });
      // не закрываем модалку принудительно, т.к. пользователь перенаправлен
      return;
    }

    if (data.url && !data.params) {
      // редирект на url
      window.location.href = data.url;
      emit('success', { amount: value });
      return;
    }

    // Если сервер вернул простую структуру — эмитим успех и закрываем модалку
    emit('success', { amount: value, raw: data });
    amount.value = null;
    emit('close');
  } catch (e) {
    // Обработка axios ошибок
    if (e.response) {
      // Сервер ответил статусом
      const status = e.response.status;
      const body = e.response.data;
      if (status === 401 || status === 403) {
        error.value = 'Потрібна авторизація. Будь ласка, увійдіть ще раз.';
        // уведомляем родителя, чтобы он показал логин
        emit('unauth');
      } else if (body && typeof body === 'object') {
        // читаем человеко-дружелюбное сообщение если есть
        error.value = body.error || body.message || JSON.stringify(body);
      } else {
        error.value = String(body || e.response.statusText || 'Помилка сервера');
      }
    } else if (e.request) {
      error.value = 'Сервер не відповідає. Спробуйте пізніше.';
    } else {
      error.value = 'Помилка: ' + e.message;
    }
    console.error('TopUp error:', e);
  } finally {
    isLoading.value = false;
  }
}
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100%;
  height: 100%;
  background: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
}
.modal {
  background: white;
  padding: 1.5rem;
  border-radius: 8px;
  width: 320px;
  box-shadow: 0 4px 20px rgba(0, 0, 0, 0.25);
}
.modal-input {
  width: 100%;
  padding: 0.5rem;
  margin: 0.75rem 0;
  font-size: 1rem;
  box-sizing: border-box;
}
.modal-actions {
  display: flex;
  justify-content: space-between;
  gap: 8px;
}
.btn-primary {
  background: #007bff;
  color: white;
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.btn-primary:disabled {
  background: #7aaefc;
  cursor: not-allowed;
}
.btn-secondary {
  background: #e0e0e0;
  color: #222;
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
  cursor: pointer;
}
.error {
  color: #c0392b;
  margin-top: 0.5rem;
  font-size: 0.9rem;
}
</style>
