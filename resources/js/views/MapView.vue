<template>
    <div class="map-page">
        <div ref="mapContainer" class="map-container"></div>

        <!-- ВАЖНО: camelCase имя пропа -->
        <FactoryOrderModal
            v-if="showModal && selectedFactory"
            :factory-id="Number(selectedFactory.id)"
            :water-type="selectedFactory.water_types"
            :on-close="() => { showModal = false; selectedFactory = null }"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import axios from 'axios'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'
import FactoryOrderModal from '@/views/FactoryOrderModal.vue'

const mapContainer = ref(null)
const factories = ref([])
const selectedFactory = ref(null)
const showModal = ref(false)

// подгружаем все фабрики с полем water_types
async function fetchFactories() {
    try {
        // ты уже сделал factoriesWithCertificates — можно сделать похожий эндпоинт,
        // но удобнее отдать все поля для карты сразу.
        // Если хочешь, оставь /factories/coordinates, но добавь в него water_types.
        const { data } = await axios.get('/api/factories/coordinates')
        // ожидаем что в ответе есть id, warehouse_address, lat, lng, email, website, WATER_TYPES
        factories.value = (data || []).map(f => ({
            ...f,
            // на всякий случай нормализуем массив типов
            water_types: Array.isArray(f.water_types)
                ? f.water_types
                : (typeof f.water_types === 'string' ? JSON.parse(f.water_types || '[]') : [])
        }))
    } catch (e) {
        console.error('Не вдалося завантажити координати виробників', e)
        alert('Не вдалося завантажити дані виробників')
    }
}

onMounted(async () => {
    await fetchFactories()

    const map = L.map(mapContainer.value).setView([50.4501, 30.5234], 13)
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map)

    factories.value.forEach((f) => {
        const marker = L.marker([Number(f.lat), Number(f.lng)]).addTo(map)
        marker.bindPopup(`
      <strong>Склад:</strong> ${f.warehouse_address ?? '—'}<br>
      <strong>Email:</strong> ${f.email ?? '—'}<br>
      ${f.website ? `<strong>Сайт:</strong> <a href="${f.website}" target="_blank">${f.website}</a><br>` : ''}
    `)

        marker.on('click', () => {
            // сохраняем именно этот объект фабрики — у него уже есть water_types
            selectedFactory.value = f
            showModal.value = true
        })
    })
})
</script>

<style scoped>
.map-page { height: 100vh; width: 100%; }
.map-container { height: 100%; width: 100%; }
</style>
