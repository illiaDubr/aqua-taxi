<template>
    <div class="auth">
        <div class="auth__bg"></div>
        <div class="auth__top">
            <img :src="logo" alt="logo" class="auth__logo" />
        </div>

        <div class="auth__card">
            <div class="auth__tabs">
                <span :class="{ active: activeTab === 'register' }" @click="activeTab = 'register'">Реєстрація</span>
                <span :class="{ active: activeTab === 'login' }" @click="activeTab = 'login'">Вхід</span>
            </div>

            <transition name="fade" mode="out-in">
                <form @submit.prevent="activeTab === 'register' ? handleRegister() : handleLogin()" class="auth__form" :key="step">
                    <template v-if="activeTab === 'register'">
                        <!-- STEP 1 -->
                        <div v-if="step === 1" class="auth__form">
                            <input type="email" placeholder="Ваша пошта*" v-model="email" required />
                            <input type="tel" placeholder="Ваш номер телефону*" v-model="phone" required />
                            <div class="auth__password-wrapper">
                                <input
                                    :type="showPassword ? 'text' : 'password'"
                                    :placeholder="activeTab === 'register' ? 'Ваш пароль*' : 'Пароль*'"
                                    v-model="password"
                                    required
                                />
                                <span class="auth__eye-icon" @click="showPassword = !showPassword">👁</span>
                            </div>
                            <input type="text" placeholder="Ваш вебсайт*" v-model="website" required />
                            <label class="auth__checkbox">
                                <input type="checkbox" v-model="agree" />
                                <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                            </label>
                            <button type="button" class="auth__submit" @click="goToStep2">Наступний крок</button>
                        </div>

                        <!-- STEP 2 -->
                        <div v-else class="auth__form">
                            <div class="upload-wrapper">
                                <input type="file" name="certificate" accept="image/png, image/jpeg, application/pdf" @change="handleFile" />
                                <p class="upload-desc">Завантажте фото сертифіката якості у форматі JPG, PNG або PDF</p>
                            </div>

                            <input type="text" placeholder="Ваша адреса складу" v-model="warehouse" required />
                            <div v-if="geoError" class="geo-warning">
                                <p>⚠️ Не вдалося визначити координати. Вкажіть місцезнаходження вручну, клікнувши по карті:</p>
                                <div ref="mapRef" class="map-container"></div>
                                <p v-if="lat && lng">📍 Обрані координати: {{ lat.toFixed(5) }}, {{ lng.toFixed(5) }}</p>
                            </div>

                            <input type="text" placeholder="Додайте види води" v-model="waterTypes" />
                            <label class="auth__checkbox">
                                <input type="checkbox" v-model="agree" />
                                <span>Реєструючись, ви погоджуєтесь з <a href="#">договором оферти</a></span>
                            </label>
                            <button type="submit" class="auth__submit">Завершити реєстрацію</button>
                        </div>
                    </template>

                    <!-- ВХІД -->
                    <template v-else>
                        <input type="email" placeholder="Ваша пошта*" v-model="email" required />
                        <div class="auth__password-wrapper">
                            <input
                                :type="showPassword ? 'text' : 'password'"
                                :placeholder="activeTab === 'register' ? 'Ваш пароль*' : 'Пароль*'"
                                v-model="password"
                                required
                            />
                            <span class="auth__eye-icon" @click="showPassword = !showPassword">👁</span>
                        </div>
                        <button type="submit" class="auth__submit">Увійти</button>
                    </template>
                </form>
            </transition>
        </div>
    </div>
</template>

<script setup>
import logo from '@/assets/logo2.png'
import { ref, watchEffect, nextTick } from 'vue';
import { useRouter } from 'vue-router';
import axios from 'axios';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

const router = useRouter();
const activeTab = ref('register');
const step = ref(1);

const email = ref('');
const phone = ref('');
const password = ref('');
const website = ref('');
const warehouse = ref('');
const waterTypes = ref('');
const agree = ref(false);
const file = ref(null);
const showPassword = ref(false);
const manualMode = ref(false);
const geoError = ref(false);
const lat = ref(null);
const lng = ref(null);
const map = ref(null);
const marker = ref(null);
const mapRef = ref(null);

watchEffect(async () => {
    if (manualMode.value && geoError.value && mapRef.value && !map.value) {
        await nextTick();
        map.value = L.map(mapRef.value).setView([50.4501, 30.5234], 13);

        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: 'Map data © OpenStreetMap contributors'
        }).addTo(map.value);

        map.value.on('click', function (e) {
            lat.value = e.latlng.lat;
            lng.value = e.latlng.lng;

            if (marker.value) {
                marker.value.setLatLng(e.latlng);
            } else {
                marker.value = L.marker(e.latlng).addTo(map.value);
            }
        });
    }
});

const handleRegister = async () => {
    if (step.value === 1) {
        goToStep2();
        return;
    }

    if (!warehouse.value || !file.value || !agree.value) {
        alert('Будь ласка, заповніть усі поля та погодьтесь з умовами.');
        return;
    }

    const formData = new FormData();
    formData.append('email', email.value);
    formData.append('phone', phone.value);
    formData.append('password', password.value);
    formData.append('website', website.value);
    formData.append('warehouse_address', warehouse.value);
    formData.append('water_types', waterTypes.value);
    formData.append('certificate', file.value);

    try {
        if (manualMode.value && (lat.value === null || lng.value === null)) {
            alert('Введіть координати вручну.');
            return;
        }

        if (manualMode.value) {
            formData.append('lat', lat.value);
            formData.append('lng', lng.value);
        }

        const res = await axios.post('/api/factory/register', formData);
        alert('Реєстрація успішна!');
        activeTab.value = 'login';

        map.value?.remove();
        map.value = null;
        marker.value = null;
        lat.value = null;
        lng.value = null;
        geoError.value = false;
        manualMode.value = false;

    } catch (err) {
        if (err.response?.data?.error === 'geocoding_failed') {
            geoError.value = true;
            manualMode.value = true;
        }
        console.error(err);
        alert('Помилка при реєстрації');
    }
};

const handleLogin = async () => {
    if (!email.value || !password.value) {
        alert('Введіть пошту та пароль');
        return;
    }

    try {
        const res = await axios.post('/api/factory/login', {
            email: email.value,
            password: password.value
        });

        const token = res.data.token;
        const factory = res.data.user;
        localStorage.setItem('token', token);
        localStorage.setItem('factory', JSON.stringify(factory));
        axios.defaults.headers.common['Authorization'] = `Bearer ${token}`;

        alert('Успішний вхід!');
        router.push('/factory-page');
    } catch (err) {
        console.error(err);
        alert('Невірна пошта або пароль');
    }
};

const goToStep2 = () => {
    if (!email.value || !phone.value || !password.value || !website.value || !agree.value) {
        alert('Будь ласка, заповніть усі поля та підтвердіть згоду.');
        return;
    }
    step.value = 2;
};

const handleFile = (e) => {
    file.value = e.target.files[0];
};
</script>


<style>
.geo-warning {
    background: #fef3c7;
    border: 1px solid #fcd34d;
    padding: 10px;
    border-radius: 8px;
    font-size: 14px;
    color: #92400e;
    margin-top: 20px;
}
.map-container {
    height: 250px;
    margin-top: 10px;
    border-radius: 8px;
    overflow: hidden;
}
.manual-coords input {
    margin-top: 8px;
}
.auth__password-wrapper {
    position: relative;
}

.auth__password-wrapper input {
    width: 100%;
    padding-left: 5px !important;
    padding-right: 0px !important;
    /* место для глазика */
}

.auth__eye-icon {
    position: absolute;
    right: 12px;
    top: 50%;
    transform: translateY(-50%);
    cursor: pointer;
}
body {
    font-family: 'Montserrat', sans-serif;
    margin: 0;
    padding: 0;
}
.attacher{
    padding: 14px;
    font-size: 15px;
    border: 1px solid #ccc;
    border-radius: 12px;
    outline: none;
}
.auth {
    position: relative;
    min-height: 100vh;
    background: linear-gradient(to bottom, #00aaff 0%, #f8f9fa 60%);
    display: flex;
    justify-content: center;
    flex-direction: column;
    align-items: center;
    overflow: hidden;
}
.auth__bg {
    position: absolute;
    top: 180px; /* регулируй чтобы опустить фон ниже */
    left: 0;
    width: 100%;
    height: 200px;
    background: url('@/assets/city.png') no-repeat center top;
    background-size: cover;
    z-index: 0;
    pointer-events: none;
}
.auth__top,
.auth__card {
    position: relative;
    z-index: 1;
}

.auth__top {
    padding-top: 0px;
    margin-bottom: 50px;
}

.auth__logo {
    width: 96px;
    height: 96px;
    border-radius: 24px;
    box-shadow: 0 4px 20px rgba(0, 0, 0, 0.2);
}

.auth__card {
    width: 100%;
    max-width: 360px;
    background: white;
    border-radius: 24px;
    padding: 24px;
    box-shadow: 0 8px 24px rgba(0, 0, 0, 0.15);
    display: flex;
    flex-direction: column;
    gap: 16px;
}

.auth__tabs {

    display: flex;
    justify-content: space-around;
    margin-bottom: 48px;
}

.auth__tabs span {
    font-size: 18px;
    font-weight: 600;
    color: #ccc;
    cursor: pointer;
    padding-bottom: 4px;
    transition: all 0.2s ease;
}

.auth__tabs .active {
    font-size: 24px;
    color: #3498db;
    border-bottom: 2px solid #3498db;
}

.auth__form {
    display: flex;
    flex-direction: column;
    gap: 16px;
    transition: all 0.2s ease;
}

.auth__form input {
    padding: 14px;
    font-size: 15px;
    border: 1px solid #ccc;
    border-radius: 12px;
    outline: none;
}

.auth__checkbox {
    display: flex;
    align-items: flex-start;
    font-size: 13px;
    color: #7f8c8d;
    gap: 8px;
}

.auth__checkbox input {
    width: 16px;
    height: 16px;
    margin-top: 2px;
}

.auth__checkbox a {
    color: #3498db;
    text-decoration: underline;
}

.auth__submit {
    padding: 14px;
    font-size: 15px;
    font-weight: 600;
    background: #3498db;
    color: white;
    border: none;
    border-radius: 12px;
    cursor: pointer;
}

/* Анимация */
.fade-enter-active, .fade-leave-active {
    transition: opacity 0.25s ease;
}
.fade-enter-from, .fade-leave-to {
    opacity: 0;
}
</style>
