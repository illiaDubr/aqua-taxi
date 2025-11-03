<template>
  <div v-if="visible" class="modal-overlay">
    <div class="modal">
      <h2>Поповнення балансу</h2>
      <input
        type="number"
        v-model="amount"
        placeholder="Сума в грн"
        class="modal-input"
      />
      <div class="modal-actions">
        <button @click="submitPayment" class="btn-primary">Поповнити</button>
        <button @click="$emit('close')" class="btn-secondary">Назад</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue';

const props = defineProps({
  visible: Boolean,
});

const amount = ref('');

async function submitPayment() {
  const response = await fetch('/api/driver/pay', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      Authorization: `Bearer ${localStorage.token}`,
    },
    body: JSON.stringify({
      amount: parseFloat(amount.value || '0'),
    }),
  });

  const { url, params } = await response.json();

  const form = document.createElement('form');
  form.method = 'POST';
  form.action = url;

  Object.entries(params).forEach(([key, value]) => {
    const input = document.createElement('input');
    input.type = 'hidden';
    input.name = key;
    input.value = value;
    form.appendChild(input);
  });

  document.body.appendChild(form);
  form.submit();
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
}
.modal {
  background: white;
  padding: 2rem;
  border-radius: 8px;
  width: 300px;
}
.modal-input {
  width: 100%;
  padding: 0.5rem;
  margin-bottom: 1rem;
}
.modal-actions {
  display: flex;
  justify-content: space-between;
}
.btn-primary {
  background: #007bff;
  color: white;
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
}
.btn-secondary {
  background: #ccc;
  color: black;
  padding: 0.5rem 1rem;
  border: none;
  border-radius: 4px;
}
</style>
