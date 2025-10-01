<template>
    <div class="modal">
        <div class="modal__card">
            <h3>Замовлення у виробника</h3>



            <label>Тип води</label>
            <select v-model="selectedType">
                <option disabled value="">— оберіть тип —</option>
                <option v-for="t in types" :key="t.code" :value="t.code">
                    {{ t.name }} — {{ t.price.toFixed(2) }} грн/бут
                </option>
            </select>

            <label>Кількість (бут.)</label>
            <input type="number" min="1" v-model.number="qty" />





            <div class="summary">
                <div>Ціна за бутиль: <b>{{ currentPrice?.toFixed(2) ?? '—' }} грн</b></div>
                <div>Разом: <b>{{ total.toFixed(2) }} грн</b></div>
            </div>











            <div class="actions">
                <button @click="onClose">Скасувати</button>
                <button :disabled="!canSubmit || loading" @click="createOrder">
                    {{ loading ? 'Створення…' : 'Створити замовлення' }}
                </button>
            </div>

            <p v-if="error" class="error">{{ error }}</p>
            <p v-if="success" class="ok">Замовлення створено!</p>

        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from 'axios'

const props = defineProps({
    factoryId: { type: [Number, String], required: true },
    waterType: { type: Array, required: true },   // массив [{code,name,price}]
    onClose:   { type: Function, required: true }
})

const qty = ref(1)
const selectedType = ref('')
const loading = ref(false)
const error = ref('')
const success = ref(false)

const types = computed(() => {
    return (props.waterType || []).map(it => ({
        code: String(it.code ?? it.name ?? '').toLowerCase(),
        name: String(it.name ?? it.code ?? '—'),
        price: Number(it.price ?? 0)
    }))
})

const currentPrice = computed(() => {
    const t = types.value.find(t => t.code === selectedType.value)
    return t ? t.price : null
})

const total = computed(() => {
    const p = currentPrice.value ?? 0
    const q = Number.isFinite(qty.value) ? qty.value : 0
    return +(p * q).toFixed(2)
})

const canSubmit = computed(() => !!selectedType.value && qty.value >= 1 && Number.isFinite(currentPrice.value))

async function createOrder () {
    if (!canSubmit.value) return
    loading.value = true
    error.value = ''
    success.value = false
    try {
        await axios.post('/api/factory-orders', {
            factory_id: Number(props.factoryId),
            water_type: selectedType.value,
            quantity: qty.value
        })
        success.value = true
        setTimeout(() => props.onClose(), 600)
    } catch (e) {
        error.value = e?.response?.data?.message || 'Помилка створення'
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.modal{position:fixed;inset:0;background:rgba(0,0,0,.5);display:flex;align-items:center;justify-content:center;z-index:1000}
.modal__card{background:#fff;border-radius:12px;padding:16px;min-width:320px;max-width:420px;width:100%;box-shadow:0 10px 30px rgba(0,0,0,.2)}
label{display:block;margin:8px 0 4px;font-weight:600}
select,input{width:100%;padding:10px;border:1px solid #ddd;border-radius:8px}
.summary{margin:12px 0;display:grid;gap:6px}
.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:12px}
.error{color:#c62828;margin-top:8px}
.ok{color:#2e7d32;margin-top:8px}














</style>
