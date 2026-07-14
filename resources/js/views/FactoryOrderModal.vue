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
.modal{position:fixed;inset:0;background:rgba(11,31,51,.45);backdrop-filter:blur(3px);display:flex;align-items:center;justify-content:center;z-index:1000;padding:20px}
.modal__card{background:#fff;border-radius:var(--r-lg);padding:22px;width:100%;max-width:400px;box-shadow:var(--sh-lg)}
.modal__card h3{font-size:18px;margin-bottom:6px}
label{display:block;margin:14px 0 6px;font-weight:700;font-size:14px;color:var(--ink)}
select,input{width:100%;box-sizing:border-box;padding:13px 14px;font-size:16px;border:1.5px solid var(--line);border-radius:var(--r-sm);outline:none;transition:border-color .18s,box-shadow .18s}
select:focus,input:focus{border-color:var(--aqua-400);box-shadow:0 0 0 4px var(--tap)}
.summary{margin:16px 0;display:grid;gap:8px;padding:14px 16px;background:var(--aqua-50);border-radius:var(--r-md);font-size:14px;color:var(--ink-2)}
.summary b{color:var(--aqua-700)}
.actions{display:flex;gap:10px;justify-content:flex-end;margin-top:16px}
.actions button{padding:12px 18px;border-radius:var(--r-sm);border:none;font-weight:700;font-size:14px;cursor:pointer;background:var(--surface-2);color:var(--ink-2)}
.actions button:last-child{background:var(--grad-aqua);color:#fff;box-shadow:var(--sh-aqua)}
.actions button:disabled{opacity:.55;box-shadow:none}
.error{color:var(--danger);margin-top:10px;font-size:13.5px}
.ok{color:var(--success);margin-top:10px;font-size:13.5px}
</style>
