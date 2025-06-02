<template>
    <div class="map-page">
        <div ref="mapContainer" class="map-container"></div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const mapContainer = ref();
const factories = ref([]);

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
        marker.bindPopup(`<strong>Склад</strong><br>${factory.warehouse_address}`);
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
