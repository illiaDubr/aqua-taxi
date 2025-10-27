<template>
  <div v-if="visible" class="modal-overlay" @click.self="close">
    <div class="modal">
      <div class="modal__header">
        <h2>Договір оферти</h2>
      </div>

      <div class="modal__body" v-html="content"></div>

      <div class="modal__footer">
        <button class="modal__close" @click="close">Закрити</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { ref, watch } from 'vue';
import { marked } from 'marked';

const markdownUrl = new URL('@/assets/offer.md', import.meta.url).href;

const props = defineProps({
  visible: Boolean
});
const emit = defineEmits(['close']);

const content = ref('');

const loadMarkdown = async () => {
  const res = await fetch(markdownUrl);
  const raw = await res.text();
  content.value = marked.parse(raw);
};

watch(() => props.visible, (val) => {
  if (val) loadMarkdown();
});

const close = () => {
  emit('close');
};
</script>

<style scoped>
.modal-overlay {
  position: fixed;
  top: 0;
  left: 0;
  width: 100vw;
  height: 100vh;
  background-color: rgba(0, 0, 0, 0.5);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}

.modal {
  background-color: white;
  border-radius: 12px;
  width: 100%;
  max-width: 700px;
  max-height: 90vh;
  display: flex;
  flex-direction: column;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
  overflow: hidden;
}

.modal__header {
  padding: 16px 24px 0;
  font-size: 18px;
  font-weight: bold;
  color: #3498db;
}

.modal__body {
  padding: 16px 24px;
  overflow-y: auto;
  flex-grow: 1;
  font-size: 14px;
  color: #2c3e50;
}

.modal__body h1, .modal__body h2 {
  color: #3498db;
  margin-top: 16px;
}

.modal__footer {
  padding: 16px 24px;
  border-top: 1px solid #eee;
  text-align: right;
}

.modal__close {
  padding: 10px 20px;
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
  font-size: 14px;
}

@media (max-width: 480px) {
  .modal {
    width: 100%;
    border-radius: 8px;
  }

  .modal__header,
  .modal__body,
  .modal__footer {
    padding: 12px 16px;
  }

  .modal__close {
    width: 100%;
  }
}
</style>
