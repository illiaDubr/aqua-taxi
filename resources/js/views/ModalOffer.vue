<template>
  <div v-if="visible" class="modal-overlay" @click.self="close">
    <div class="modal">
      <h2>Договір оферти</h2>
      <div class="modal__content" v-html="content"></div>
      <button class="modal__close" @click="close">Закрити</button>
    </div>
  </div>
</template>

<script setup>
import { ref, watch, onMounted } from 'vue';
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
}

.modal {
  background-color: white;
  padding: 24px;
  border-radius: 12px;
  max-width: 700px;
  width: 90%;
  max-height: 80vh;
  overflow-y: auto;
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.2);
}

.modal__content {
  margin-top: 16px;
  font-size: 14px;
  color: #2c3e50;
}

.modal__content h1, .modal__content h2 {
  color: #3498db;
  margin-top: 16px;
}

.modal__close {
  margin-top: 16px;
  padding: 10px 20px;
  background-color: #3498db;
  color: white;
  border: none;
  border-radius: 8px;
  cursor: pointer;
}
</style>
