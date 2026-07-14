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
        <button @click="submitPayment" class="btn-primary" :disabled="isLoading">
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

async function submitPayment() {
  error.value = '';
  const value = Number(amount.value);
  if (!value || isNaN(value) || value <= 0) {
    error.value = 'Введіть коректну суму для поповнення';
    return;
  }

  isLoading.value = true;

  try {
    const res = await axios.post('/api/driver/pay', { amount: value }, { timeout: 15000 });
    const { url, payment_id } = res.data || {};

    if (url && payment_id) {
      // Запускаем polling через 15 секунд
      setTimeout(() => {
        axios.post(`/api/driver/pay/poll/${payment_id}`).catch(err => {
          console.warn('polling error', err);
        });
      }, 15000);

      // Редиректим пользователя на оплату
      window.location.href = url;
      emit('success', { amount: value });
      return;
    }

    error.value = 'Не вдалося отримати посилання на оплату';
  } catch (e) {
    if (e.response) {
      const status = e.response.status;
      const body = e.response.data;
      if (status === 401 || status === 403) {
        error.value = 'Потрібна авторизація. Будь ласка, увійдіть ще раз.';
        emit('unauth');
      } else {
        error.value = body?.error || body?.message || 'Помилка сервера';
      }
    } else {
      error.value = 'Сервер не відповідає. Спробуйте пізніше.';
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
  inset: 0;
  background: rgba(11, 31, 51, .45);
  backdrop-filter: blur(3px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 9999;
  padding: 20px;
}
.modal {
  background: white;
  padding: 24px;
  border-radius: var(--r-lg);
  width: 100%;
  max-width: 360px;
  box-shadow: var(--sh-lg);
}
.modal h2 { font-size: 19px; margin-bottom: 4px; }
.modal-input {
  width: 100%;
  padding: 14px 16px;
  margin: 16px 0;
  font-size: 16px;
  box-sizing: border-box;
  border: 1.5px solid var(--line);
  border-radius: var(--r-sm);
  outline: none;
  transition: border-color .18s, box-shadow .18s;
}
.modal-input:focus { border-color: var(--aqua-400); box-shadow: 0 0 0 4px var(--tap); }
.modal-actions {
  display: flex;
  gap: 10px;
}
.btn-primary {
  flex: 1;
  background: var(--grad-aqua);
  color: white;
  padding: 13px 16px;
  border: none;
  border-radius: var(--r-sm);
  cursor: pointer;
  font-weight: 700;
  font-size: 15px;
  box-shadow: var(--sh-aqua);
}
.btn-primary:disabled {
  opacity: .55;
  box-shadow: none;
  cursor: not-allowed;
}
.btn-secondary {
  flex: 1;
  background: var(--surface-2);
  color: var(--ink-2);
  padding: 13px 16px;
  border: none;
  border-radius: var(--r-sm);
  cursor: pointer;
  font-weight: 600;
  font-size: 15px;
}
.error {
  color: var(--danger);
  margin-top: 12px;
  font-size: 13.5px;
}
</style>
