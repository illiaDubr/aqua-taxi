<template>
    <div class="wl">
        <!-- Аква-герой -->
        <header class="wl__hero">
            <div class="wl__blob wl__blob--1"></div>
            <div class="wl__blob wl__blob--2"></div>

            <div class="wl__brand">
                <img :src="logo" alt="Aqua Taxi" class="wl__logo" />
                <span class="wl__brandname">Aqua&nbsp;Taxi</span>
            </div>

            <h1 class="wl__title">Вода — за пару&nbsp;тапів</h1>
            <p class="wl__subtitle">Оберіть, ким ви хочете бути в Aqua&nbsp;Taxi</p>
        </header>

        <!-- Ролі -->
        <main class="wl__roles">
            <button
                v-for="role in roles"
                :key="role.key"
                class="wl__role"
                @click="role.action"
            >
                <span class="wl__role-ic" :style="{ background: role.tint }" v-html="role.icon"></span>
                <span class="wl__role-txt">
                    <span class="wl__role-title">{{ role.title }}</span>
                    <span class="wl__role-desc">{{ role.desc }}</span>
                </span>
                <svg class="wl__chev" width="20" height="20" viewBox="0 0 24 24" fill="none">
                    <path d="M9 6l6 6-6 6" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round"/>
                </svg>
            </button>
        </main>

        <!-- Довіра -->
        <footer class="wl__trust">
            <span class="wl__trust-label">Безпечна оплата</span>
            <div class="wl__logos">
                <img :src="mastercardLogo" alt="MasterCard SecureCode" />
                <img :src="visaLogo" alt="Visa Secure" />
            </div>
        </footer>
    </div>
</template>

<script setup>
import { useRouter } from 'vue-router';
import logo from '@/assets/logo2.png';
import mastercardLogo from '@/assets/maste1.png';
import visaLogo from '@/assets/visa1.png';

const router = useRouter();

const roles = [
    {
        key: 'user',
        title: 'Замовити воду',
        desc: 'Доставка чистої води додому',
        tint: 'linear-gradient(135deg,#22d3ee,#0ea5e9)',
        icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M12 2.5c3.6 4.2 6.5 7.9 6.5 11.4A6.5 6.5 0 1 1 5.5 13.9C5.5 10.4 8.4 6.7 12 2.5Z" fill="#fff"/><path d="M9.2 14.2a2.8 2.8 0 0 0 2.8 2.8" stroke="#0ea5e9" stroke-width="1.6" stroke-linecap="round"/></svg>',
        action: () => router.push({ name: 'authUser' }),
    },
    {
        key: 'driver',
        title: 'Стати водієм',
        desc: 'Приймайте замовлення та заробляйте',
        tint: 'linear-gradient(135deg,#34d399,#059669)',
        icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="8.5" fill="#fff"/><circle cx="12" cy="12" r="2.4" fill="#059669"/><path d="M12 3.5v3M12 17.5v3M3.5 12h3M17.5 12h3" stroke="#059669" stroke-width="1.6" stroke-linecap="round"/></svg>',
        action: () => router.push({ name: 'authDriver' }),
    },
    {
        key: 'factory',
        title: 'Стати виробником',
        desc: 'Продавайте воду через платформу',
        tint: 'linear-gradient(135deg,#60a5fa,#2563eb)',
        icon: '<svg width="24" height="24" viewBox="0 0 24 24" fill="none"><path d="M4 20V10l5 3V10l5 3V6l6 3.5V20H4Z" fill="#fff"/><path d="M7 16h1.5M11 16h1.5M15 16h1.5" stroke="#2563eb" stroke-width="1.4" stroke-linecap="round"/></svg>',
        action: () => router.push({ name: 'authFactory' }),
    },
];
</script>

<style scoped>
.wl {
    max-width: var(--maxw);
    margin: 0 auto;
    min-height: 100vh;
    display: flex;
    flex-direction: column;
    background: var(--bg);
}

/* ---- Hero ---- */
.wl__hero {
    position: relative;
    overflow: hidden;
    padding: calc(52px + env(safe-area-inset-top, 0px)) 26px 60px;
    background: var(--grad-hero);
    color: #fff;
    border-radius: 0 0 34px 34px;
    box-shadow: var(--sh-md);
}
.wl__blob {
    position: absolute;
    border-radius: 50%;
    filter: blur(2px);
    opacity: .35;
    background: #fff;
    pointer-events: none;
}
.wl__blob--1 { width: 190px; height: 190px; top: -70px; right: -50px; opacity: .18; }
.wl__blob--2 { width: 120px; height: 120px; bottom: -40px; left: -30px; opacity: .12; }

.wl__brand { display: flex; align-items: center; gap: 12px; margin-bottom: 34px; position: relative; }
.wl__logo {
    width: 46px; height: 46px; border-radius: 14px; object-fit: cover;
    box-shadow: 0 6px 18px rgba(0,0,0,.22); background: #fff;
}
.wl__brandname { font-size: 20px; font-weight: 800; letter-spacing: -.02em; }

.wl__title {
    position: relative;
    color: #fff;
    font-size: 30px;
    line-height: 1.12;
    font-weight: 800;
    margin-bottom: 10px;
}
.wl__subtitle { position: relative; margin: 0; font-size: 15.5px; color: rgba(255,255,255,.9); }

/* ---- Roles ---- */
.wl__roles {
    flex: 1;
    display: flex;
    flex-direction: column;
    gap: 14px;
    padding: 22px 18px;
    margin-top: -26px;
}
.wl__role {
    display: flex;
    align-items: center;
    gap: 15px;
    width: 100%;
    padding: 16px;
    background: var(--surface);
    border: none;
    border-radius: var(--r-lg);
    box-shadow: var(--sh-sm);
    cursor: pointer;
    text-align: left;
    transition: transform .16s var(--ease), box-shadow .2s var(--ease);
}
.wl__role:active { transform: scale(.98); box-shadow: var(--sh-xs); }

.wl__role-ic {
    flex: none;
    width: 52px; height: 52px;
    display: inline-flex; align-items: center; justify-content: center;
    border-radius: 16px;
    box-shadow: var(--sh-xs);
}
.wl__role-txt { display: flex; flex-direction: column; gap: 3px; flex: 1; min-width: 0; }
.wl__role-title { font-size: 16.5px; font-weight: 700; color: var(--ink); }
.wl__role-desc { font-size: 13px; color: var(--muted); }
.wl__chev { flex: none; color: var(--faint); }

/* ---- Trust ---- */
.wl__trust {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 10px;
    padding: 8px 0 calc(26px + env(safe-area-inset-bottom, 0px));
}
.wl__trust-label { font-size: 12px; letter-spacing: .04em; text-transform: uppercase; color: var(--faint); font-weight: 600; }
.wl__logos { display: flex; align-items: center; gap: 18px; }
.wl__logos img { height: 30px; object-fit: contain; opacity: .8; }
</style>
