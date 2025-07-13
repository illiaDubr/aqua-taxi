<template>
    <div class="modal-overlay" @click.self="close">
        <div class="modal-content">
            <button class="modal-close" @click="close">×</button>
            <h2>Замовлення води</h2>
            <div class="modal-section">
                <p><strong>Тип води:</strong> {{ waterType }}</p>
                <p><strong>Ціна за бутель:</strong> {{ pricePerBottle }} грн</p>
            </div>

            <div class="modal-section">
                <label for="quantity">Кількість бутлів:</label>
                <input
                    id="quantity"
                    type="number"
                    min="1"
                    v-model="quantity"
                    class="input"
                />
            </div>

            <p class="total">Всього: <strong>{{ totalPrice }} грн</strong></p>

            <button class="modal-button" @click="createOrder">
                Замовити
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import axios from 'axios';

const props = defineProps({
    factoryId: Number,
    waterType: String,
    pricePerBottle: Number,
    onClose: Function
});

const quantity = ref(1);

const totalPrice = computed(() => (quantity.value * props.pricePerBottle).toFixed(2));

const createOrder = async () => {
    try {
        await axios.post('/api/factory-orders', {
            factory_id: props.factoryId,
            water_type: props.waterType,
            price_per_bottle: props.pricePerBottle,
            quantity: quantity.value,
        });
        alert('Замовлення створено!');
        props.onClose(); // закрываем после отправки
    } catch (e) {
        console.error('Помилка створення замовлення', e);
        alert('Не вдалося створити замовлення');
    }
};

const close = () => {
    props.onClose();
};
</script>

<style scoped>
.modal-overlay {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background-color: rgba(0, 0, 0, 0.5);
    z-index: 999;
    display: flex;
    justify-content: center;
    align-items: center;
}

.modal-content {
    background: white;
    padding: 24px;
    border-radius: 12px;
    width: 90%;
    max-width: 400px;
    position: relative;
    box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
}

.modal-close {
    position: absolute;
    top: 12px;
    right: 16px;
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
    color: #888;
}

.modal-section {
    margin-bottom: 16px;
}

.input {
    width: 100%;
    padding: 8px 12px;
    font-size: 16px;
    border: 1px solid #ccc;
    border-radius: 6px;
}

.total {
    font-size: 18px;
    margin-bottom: 16px;
    text-align: right;
}

.modal-button {
    width: 100%;
    padding: 12px;
    background-color: #007BFF;
    border: none;
    color: white;
    font-size: 16px;
    border-radius: 6px;
    cursor: pointer;
    transition: background 0.3s ease;
}

.modal-button:hover {
    background-color: #0056b3;
}
</style>
