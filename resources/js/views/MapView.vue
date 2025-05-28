<template>
    <div class="map-page">
        <div ref="mapContainer" class="map-container"></div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import L from 'leaflet'
import 'leaflet/dist/leaflet.css'

// Пример мок-данных, позже заменить на реальные данные из API
const points = [
    { id: 1, lat: 50.4501, lng: 30.5234, price: '32.50₴', distance: '2.5км' },
    { id: 2, lat: 50.4551, lng: 30.5300, price: '32.50₴', distance: '3.2км' },
    { id: 3, lat: 50.4480, lng: 30.5200, price: '32.50₴', distance: '1.8км' }
]

const mapContainer = ref()

onMounted(() => {
    const map = L.map(mapContainer.value).setView([50.4501, 30.5234], 14)

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: 'Map data © OpenStreetMap contributors'
    }).addTo(map)

    points.forEach(point => {
        const marker = L.marker([point.lat, point.lng]).addTo(map)
        marker.bindPopup(`<strong>${point.price}</strong><br>${point.distance}`).openPopup()
    })
})
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
