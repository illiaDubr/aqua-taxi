<template>
    <div class="map-page">
        <div ref="mapContainer" class="map-container"></div>

        <FactoryOrderModal
            v-if="showModal"
            :factory-id="selectedFactory.id"
            :water-type="selectedFactory.water_types"
            :price-per-bottle="33.5"
            :on-close="() => showModal = false"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import FactoryOrderModal from '@/views/FactoryOrderModal.vue';


const mapContainer = ref();
const factories = ref([]);

const selectedFactory = ref(null);
const showModal = ref(false);

const fetchFactories = async () => {
    try {
        const res = await axios.get('/api/factories/coordinates');
        factories.value = res.data;
    } catch (e) {
        console.error('Не вдалося завантажити координати виробників', e);
    }
};

onMounted(async () => {
    await fetchFactories();

    const map = L.map(mapContainer.value).setView([50.4501, 30.5234], 13);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map);

    factories.value.forEach(factory => {
        const marker = L.marker([factory.lat, factory.lng]).addTo(map);
        marker.bindPopup(`
      <strong>Склад:</strong> ${factory.warehouse_address}<br>
      <strong>Email:</strong> ${factory.email}<br>
      ${factory.website ? `<strong>Сайт:</strong> <a href="${factory.website}" target="_blank">${factory.website}</a><br>` : ''}
    `);

        // 💡 Обработка клика по маркеру
        marker.on('click', () => {
            selectedFactory.value = factory;
            showModal.value = true;
        });
    });
});
</script>

<style scoped>
.map-page {
    height: 100vh;
    width: 100%;
}
.map-container {
    height: 100%;
    width: 100%;
}
</style>
