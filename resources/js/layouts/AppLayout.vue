<template>
    <div v-if="showShell" class="d-flex min-vh-100 bg-light">
        <aside
            v-if="sidebarVisible"
            class="bg-dark text-white p-3"
            style="width: 280px;"
        >
            <div class="mb-4">
                <h1 class="h5 mb-0">{{ nombreSistema }}</h1>
            </div>

            <nav class="nav nav-pills flex-column gap-2">
                <router-link
                    v-for="item in visibleNavigationItems"
                    :key="item.name"
                    :to="item.disabled ? '#' : { name: item.name }"
                    class="nav-link d-flex align-items-center gap-2"
                    :class="route.name === item.name ? 'active' : 'text-white'"
                    :aria-disabled="item.disabled"
                    @click.prevent="item.disabled && null"
                >
                    <FontAwesomeIcon :icon="item.icon" fixed-width />
                    <span>{{ item.label }}</span>
                    <span v-if="item.disabled" class="badge text-bg-secondary ms-auto">Pronto</span>
                </router-link>
            </nav>
        </aside>

        <div class="flex-grow-1 d-flex flex-column" :class="sidebarVisible ? '' : 'w-100'">
            <header class="bg-white border-bottom px-3 px-md-4 py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-outline-secondary" @click="toggleSidebar">
                        <FontAwesomeIcon :icon="sidebarVisible ? 'fa-solid fa-bars-staggered' : 'fa-solid fa-bars'" />
                    </button>

                    <div>
                        <p class="small text-body-secondary mb-1">Sesion activa</p>
                        <strong>{{ authStore.user?.name }}</strong>
                    </div>
                </div>

                <button type="button" class="btn btn-outline-danger" @click="logout">
                    <FontAwesomeIcon icon="fa-solid fa-right-from-bracket" class="me-2" />
                    Cerrar sesion
                </button>
            </header>

            <main class="flex-grow-1 p-3 p-md-4">
                <router-view />
            </main>
        </div>
    </div>

    <main v-else class="min-vh-100 d-flex align-items-center justify-content-center p-4">
        <div class="w-100" style="max-width: 480px;">
            <router-view />
        </div>
    </main>
</template>

<script setup>
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import axios from '@/bootstrap';
import { useAuthStore } from '@/stores/auth';
import { navigationItems } from '@/utils/navigation';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const nombreSistema = ref('Sistema POS e Inventario');
const configuracionCargada = ref(false);
const sidebarVisible = ref(true);

const showShell = computed(() => authStore.isAuthenticated && route.name !== 'login');

const visibleNavigationItems = computed(() => navigationItems.filter((item) => authStore.hasAnyPermission(item.permissions)));

watch(
    showShell,
    async (isVisible) => {
        if (!isVisible || configuracionCargada.value) {
            return;
        }

        try {
            const { data } = await axios.get('/configuraciones/publicas');
            nombreSistema.value = data?.nombre_empresa ?? nombreSistema.value;
            configuracionCargada.value = true;
        } catch {
            // Usa valor por defecto si falla la carga de configuraciones.
        }
    }
);

function toggleSidebar() {
    sidebarVisible.value = !sidebarVisible.value;
}

async function logout() {
    await authStore.logout();
    await router.push({ name: 'login' });
}
</script>
