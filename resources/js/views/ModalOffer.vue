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
  top: 0; left: 0;
  width: 100vw; height: 100vh;
  background-color: rgba(11, 31, 51, 0.5);
  backdrop-filter: blur(3px);
  display: flex;
  align-items: center;
  justify-content: center;
  z-index: 999;
  padding: 16px;
}
.modal {
  background-color: white;
  border-radius: var(--r-lg);
  width: 100%;
  max-width: 640px;
  max-height: 88vh;
  display: flex;
  flex-direction: column;
  box-shadow: var(--sh-lg);
  overflow: hidden;
}
.modal__header {
  padding: 18px 24px 0;
  font-size: 18px;
  font-weight: 700;
  color: var(--aqua-700);
}
.modal__body {
  padding: 16px 24px;
  overflow-y: auto;
  flex-grow: 1;
  font-size: 14px;
  color: var(--ink-2);
  line-height: 1.55;
}
.modal__body h1, .modal__body h2 {
  color: var(--aqua-700);
  margin-top: 16px;
}
.modal__footer {
  padding: 16px 24px;
  border-top: 1px solid var(--line-2);
  text-align: right;
}
.modal__close {
  padding: 12px 22px;
  background: var(--grad-aqua);
  color: white;
  border: none;
  border-radius: var(--r-sm);
  cursor: pointer;
  font-size: 15px;
  font-weight: 600;
  box-shadow: var(--sh-aqua);
}
.modal__close:active { transform: scale(.98); }

@media (max-width: 480px) {
  .modal { max-width: 100%; border-radius: var(--r-md); }
  .modal__header, .modal__body, .modal__footer { padding: 14px 16px; }
  .modal__close { width: 100%; }
}
</style>
