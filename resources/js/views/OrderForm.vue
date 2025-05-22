<script setup>

import { ref } from 'vue';
import { computed } from 'vue';
import { useRoute } from 'vue-router';
const route = useRoute();

const productName = decodeURIComponent(route.params.productId || ''); // example: 'Срібна вода, 19л'

const pricePerBottle = 120;

const totalAmount = computed(() => {
    const qty = parseInt(quantity.value, 10);
    return isNaN(qty) ? 0 : qty * pricePerBottle;
});
    const address = ref('');
    const quantity = ref('');
    const bottlesType = ref('');
    const buyBottles = ref('');
    const deliveryTime = ref('');
    const paymentMethod = ref('cash');        // 'cash' | 'card'
    const bottleOption = ref('own');          // 'own' | 'buy'
    const timeOption = ref('now');            // 'now' | 'custom'
    const customTime = ref('');

</script>
<template>
    <div class="form">
        <div class="form__bg"></div>

        <div class="form__card">
            <h2 class="form__title">Оформлення<br />замовлення</h2>
            <p class="form__subtitle">Срібна вода, 19л</p>

            <div class="form__group">
                <label>Введіть ваші дані</label>
                <input type="text" placeholder="Ваша адреса" v-model="address" />
                <select v-model="quantity">
                    <option disabled value="">Кількість бутелів</option>
                    <option v-for="n in 10" :key="n" :value="n">{{ n }}</option>
                </select>
            </div>

            <div class="form__group">
                <label>Бутелі</label>
                <div class="form__switch">
                    <button
                        :class="{ active: bottleOption === 'own' }"
                        @click="bottleOption = 'own'"
                    >Свої бутелі</button>

                    <button
                        :class="{ active: bottleOption === 'buy' }"
                        @click="bottleOption = 'buy'"
                    >Придбати бутелі</button>
                </div>
            </div>

                <div class="form__group">
                    <label>Час</label>
                    <div class="form__switch">
                        <button
                            :class="{ active: timeOption === 'now' }"
                            @click="timeOption = 'now'"
                        >Привезти в найближчий час</button>

                        <button
                            :class="{ active: timeOption === 'custom' }"
                            @click="timeOption = 'custom'"
                        >Привезти на обраний час</button>
                    </div>

                    <input
                        v-if="timeOption === 'custom'"
                        type="datetime-local"
                        v-model="customTime"
                    />
                </div>


                <!-- Спосіб оплати -->
                <div class="form__group">
                    <label>Спосіб оплати</label>
                    <div class="form__switch">
                        <button
                            :class="{ active: paymentMethod === 'cash' }"
                            @click="paymentMethod = 'cash'"
                        >Готівка</button>

                        <button
                            :class="{ active: paymentMethod === 'card' }"
                            @click="paymentMethod = 'card'"
                        >Оплата карткою</button>
                    </div>
                </div>

            <div class="form__footer">
                <span class="form__total">До сплати:</span>
                <span class="form__amount">{{ totalAmount }} грн</span>
            </div>

            <button class="form__submit">Створити товар</button>
        </div>
    </div>
</template>



<style scoped>
.form__switch {
    display: flex;
    gap: 12px;
    flex-wrap: wrap;
}

.form__switch button {
    flex: 1;
    padding: 12px;
    border-radius: 10px;
    background: #f1f1f1;
    border: none;
    font-size: 15px;
    font-weight: 500;
    color: #555;
    cursor: pointer;
    transition: all 0.2s ease;
}

.form__switch button.active {
    background: #007bff;
    color: white;
}

.form {
    min-height: 100vh;
    background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 60%);
    display: flex;
    justify-content: center;
    align-items: flex-start;
    padding-top: 140px;
    position: relative;
}

.form__bg {
    position: absolute;
    top: 30px;
    width: 100%;
    height: 140px;
    background: url('@/assets/city.png') no-repeat center;
    background-size: cover;
    z-index: 0;
}

.form__card {
    background: #fff;
    border-radius: 20px;
    padding: 24px;
    width: 100%;
    max-width: 400px;
    box-shadow: 0 6px 20px rgba(0, 0, 0, 0.1);
    z-index: 1;
    position: relative;
}

.form__title {
    text-align: center;
    font-size: 20px;
    font-weight: 700;
    line-height: 1.3;
    margin-bottom: 4px;
}

.form__subtitle {
    text-align: center;
    color: #666;
    margin-bottom: 20px;
}

.form__group {
    display: flex;
    flex-direction: column;
    gap: 12px;
    margin-bottom: 20px;
}

.form__group label {
    font-size: 14px;
    font-weight: 600;
    color: #444;
}

.form__group input,
.form__group select {
    padding: 14px;
    font-size: 15px;
    border: 1px solid #ccc;
    border-radius: 12px;
    outline: none;
    background: #f9f9f9;
}

.form__footer {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-bottom: 20px;
}

.form__total {
    font-size: 16px;
    font-weight: 600;
}

.form__amount {
    font-size: 18px;
    font-weight: 700;
}

.form__submit {
    width: 100%;
    padding: 14px;
    font-size: 16px;
    font-weight: 600;
    background: #007bff;
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}
</style>
