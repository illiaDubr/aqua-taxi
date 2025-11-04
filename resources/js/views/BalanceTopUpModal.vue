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

      <p v-if="error" class="error">{{ error }}</p>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';

const props = defineProps({
  visible: Boolean,
});

const amount = ref(null);
const isLoading = ref(false);
const error = ref('');

/**
 * Вспомогательная: возвращаем plain token из localStorage
 */
function getToken() {
  try {
    return localStorage.getItem('token') || null;
  } catch {
    return null;
  }
}

async function submitPayment() {
  error.value = '';

  const value = Number(amount.value);
  if (!value || isNaN(value) || value <= 0) {
    error.value = 'Введіть коректну суму для поповнення';
    return;
  }

  const token = getToken();
  if (!token) {
    error.value = 'Треба увійти в систему';
    // Можно дополнительно эмитить событие для родителя: $emit('unauth')
    return;
  }

  isLoading.value = true;

  try {
    const res = await axios.post(
      '/api/driver/pay',
      { amount: value },
      {
        headers: {
          'Content-Type': 'application/json',
          Authorization: `Bearer ${token}`,
        },
        timeout: 15000,
      }
    );

    // Проверяем структуру ответа
    if (res.status >= 200 && res.status < 300) {
      const data = res.data || {};
      // Если контроллер возвращает url/params (старый HTML-форма подход)
      if (data.url && data.params) {
        // Создаём и сабмитим форму к стороннему сервису (ipay)
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

        // Эмитим событие успеха с информацией (родитель может обновить баланс)
        // Не закрываем модалку, т.к. пользователь уходит на оплату, но можно:
        // $emit('success', { amount: value });
        return;
      }

      // Если API теперь возвращает url напрямую
      if (data.url && !data.params) {
        window.location.href = data.url;
        return;
      }

      // Если контроллер вернул простую подтверждающую структуру
      // Эмитим success, закрываем модалку и передаём сумму
      // Родитель может слушать и обновлять баланс
      // Пример: <BalanceTopUpModal @success="onTopUpSuccess" />
      // where onTopUpSuccess = (payload) => { fetchProfile() }
      const payload = { amount: value, raw: data };
      // emit manually via global event — in script setup use `const emit = defineEmits([...])`
      emitSuccess(payload);
      amount.value = null;
      $emitClose();
    } else {
      error.value = 'Помилка поповнення: ' + (res.statusText || res.status);
    }
  } catch (e) {
    // axios error handling
    if (e.response) {
      // Сервер ответил (4xx,5xx)
      const text = e.response.data ? JSON.stringify(e.response.data) : e.response.statusText;
      error.value = 'Помилка поповнення: ' + text;
    } else if (e.request) {
      // Запрос ушёл, но ответа нет
      error.value = 'Сервер не відповідає. Спробуйте пізніше.';
    } else {
      // Что-то при настройке запроса
      error.value = 'Помилка: ' + e.message;
    }
    // Логируем в консоль для отладки
    console.error('TopUp error:', e);
  } finally {
    isLoading.value = false;
  }
}

/**
 * Небольшие обёртки, т.к. script setup: используем defineEmits
 */
const emit = defineEmits(['close', 'success', 'unauth']);

function emitSuccess(payload) {
  emit('success', payload);
}

function $emitClose() {
  emit('close');
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
