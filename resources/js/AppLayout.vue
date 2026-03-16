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

            <nav class="nav nav-pills flex-column gap-1">
                <template v-for="item in menuItems" :key="item.name">
                    <!-- Grupo con sub-items -->
                    <template v-if="item.children">
                        <button
                            type="button"
                            class="nav-group-btn nav-link d-flex align-items-center gap-2 text-white w-100"
                            @click="toggleGroup(item.name)"
                        >
                            <FontAwesomeIcon :icon="item.icon" fixed-width />
                            <span class="flex-grow-1">{{ item.label }}</span>
                            <FontAwesomeIcon
                                :icon="isGroupOpen(item.name) ? 'fa-solid fa-chevron-down' : 'fa-solid fa-chevron-right'"
                                class="small opacity-75"
                            />
                        </button>
                        <template v-if="isGroupOpen(item.name)">
                            <router-link
                                v-for="child in item.children"
                                :key="child.code"
                                :to="child.ruta"
                                class="nav-link d-flex align-items-center gap-2 ps-4"
                                :class="route.path === child.ruta ? 'active' : 'text-white-50'"
                            >
                                <FontAwesomeIcon :icon="child.icono" fixed-width />
                                <span>{{ child.name }}</span>
                            </router-link>
                        </template>
                    </template>

                    <!-- Item regular -->
                    <router-link
                        v-else
                        :to="item.ruta"
                        class="nav-link d-flex align-items-center gap-2 text-white"
                        :class="route.path === item.ruta ? 'active' : ''"
                    >
                        <FontAwesomeIcon :icon="item.icono" fixed-width />
                        <span>{{ item.name }}</span>
                    </router-link>
                </template>
            </nav>
        </aside>

        <div class="flex-grow-1 d-flex flex-column" :class="sidebarVisible ? '' : 'w-100'">
            <header class="bg-white border-bottom px-3 px-md-4 py-3 d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <button type="button" class="btn btn-outline-brand" @click="toggleSidebar">
                        <FontAwesomeIcon :icon="sidebarVisible ? 'fa-solid fa-bars-staggered' : 'fa-solid fa-bars'" />
                    </button>
                </div>

                <div class="dropdown">
                    <button
                        class="btn btn-outline-brand dropdown-toggle"
                        type="button"
                        data-bs-toggle="dropdown"
                        aria-expanded="false"
                    >
                        <FontAwesomeIcon icon="fa-solid fa-user" class="me-2" />
                        {{ authStore.user?.username || authStore.user?.name || 'usuario' }}
                    </button>
                    <ul class="dropdown-menu dropdown-menu-end">
                        <li>
                            <button type="button" class="dropdown-item" @click="openChangePasswordModal">
                                <FontAwesomeIcon icon="fa-solid fa-key" class="me-2" />
                                Cambiar contrasena
                            </button>
                        </li>
                        <li><hr class="dropdown-divider"></li>
                        <li>
                            <button type="button" class="dropdown-item text-danger" @click="logout">
                                <FontAwesomeIcon icon="fa-solid fa-right-from-bracket" class="me-2" />
                                Cerrar sesion
                            </button>
                        </li>
                    </ul>
                </div>
            </header>

            <main class="flex-grow-1 p-3 p-md-4">
                <router-view />
            </main>
        </div>

        <div ref="passwordModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Cambiar contrasena</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>

                    <form novalidate @submit.prevent="submitChangePassword">
                        <div class="modal-body d-grid gap-3">
                            <div class="alert alert-warning py-2 mb-0">
                                Al confirmar este cambio, el sistema cerrara sesion en todos los navegadores.
                                Debera iniciar sesion nuevamente.
                            </div>

                            <div v-if="passwordErrors.length" class="alert alert-danger py-2 mb-0">
                                <ul class="mb-0 ps-3">
                                    <li v-for="(e, i) in passwordErrors" :key="i">{{ e }}</li>
                                </ul>
                            </div>

                            <div>
                                <label class="form-label fw-semibold" for="current-password">Contrasena actual *</label>
                                <input
                                    id="current-password"
                                    v-model="passwordForm.current_password"
                                    type="password"
                                    class="form-control"
                                    autocomplete="current-password"
                                    required
                                >
                            </div>

                            <div>
                                <label class="form-label fw-semibold" for="new-password">Nueva contrasena *</label>
                                <input
                                    id="new-password"
                                    v-model="passwordForm.password"
                                    type="password"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                >
                                <div class="form-text">
                                    Minimo 8 caracteres, al menos una mayuscula y un numero. Recomendado incluir simbolo.
                                </div>
                            </div>

                            <div>
                                <label class="form-label fw-semibold" for="new-password-confirm">Confirmar nueva contrasena *</label>
                                <input
                                    id="new-password-confirm"
                                    v-model="passwordForm.password_confirmation"
                                    type="password"
                                    class="form-control"
                                    autocomplete="new-password"
                                    required
                                >
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="passwordSaving">
                                {{ passwordSaving ? 'Guardando...' : 'Actualizar contrasena' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div class="toast-container position-fixed top-0 end-0 p-3">
            <div
                ref="successToastRef"
                class="toast align-items-center text-bg-success border-0"
                role="status"
                aria-live="polite"
                aria-atomic="true"
            >
                <div class="d-flex">
                    <div class="toast-body">
                        {{ successToastMessage }}
                    </div>
                    <button type="button" class="btn-close btn-close-white me-2 m-auto" data-bs-dismiss="toast" aria-label="Close"></button>
                </div>
            </div>
        </div>
    </div>

    <main v-else class="min-vh-100 d-flex align-items-center justify-content-center p-4">
        <div class="w-100" style="max-width: 480px;">
            <router-view />
        </div>
    </main>

    <LoadingOverlay :show="isAppLoading" />
</template>

<script setup>
import { Modal, Toast } from 'bootstrap';
import { computed, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import axios from '@/bootstrap';
import LoadingOverlay from '@/components/components_ui/LoadingOverlay.vue';
import { isAppLoading } from '@/components/components_ui/loadingState';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();
const nombreSistema = ref('Sistema POS e Inventario');
const configuracionCargada = ref(false);
const sidebarVisible = ref(true);
const passwordSaving = ref(false);
const passwordErrors = ref([]);
const passwordModalRef = ref(null);
const successToastRef = ref(null);
const successToastMessage = ref('');
let passwordModal = null;
let successToast = null;

const emptyPasswordForm = () => ({
    current_password: '',
    password: '',
    password_confirmation: '',
});

const passwordForm = ref(emptyPasswordForm());

const showShell = computed(() => authStore.isAuthenticated && route.name !== 'login');

const openGroups = ref([]);

// Construye el menú dinámicamente desde los permisos del usuario ordenados por `orden`.
const menuItems = computed(() => {
    const perms = [...(authStore.user?.permissions ?? [])]
        .filter((p) => Boolean(p.ruta))
        .sort((a, b) => (a.orden ?? 999) - (b.orden ?? 999));

    const groupedModules = [
        { module: 'operaciones', name: 'operaciones', label: 'Operaciones', icon: 'fa-solid fa-briefcase' },
        { module: 'inventario', name: 'inventario', label: 'Inventario', icon: 'fa-solid fa-warehouse' },
        { module: 'caja', name: 'caja', label: 'Caja', icon: 'fa-solid fa-cash-register' },
        { module: 'ventas', name: 'ventas', label: 'Ventas', icon: 'fa-solid fa-cart-shopping' },
        { module: 'catalogos', name: 'catalogos', label: 'Catalogo', icon: 'fa-solid fa-boxes-stacked' },
        { module: 'configuracion', name: 'configuracion', label: 'Configuracion', icon: 'fa-solid fa-gears' },
    ];

    const groupedSet = new Set(groupedModules.map((g) => g.module));
    const topLevel = perms.filter((p) => !groupedSet.has(p.module));
    const result = [...topLevel];

    for (const groupDef of groupedModules) {
        const children = perms.filter((p) => p.module === groupDef.module);
        if (!children.length) continue;

        const minOrden = Math.min(...children.map((p) => p.orden ?? 999));

        const group = {
            name: groupDef.name,
            label: groupDef.label,
            icon: groupDef.icon,
            orden: minOrden,
            children,
        };
        const insertAt = result.findIndex((p) => (p.orden ?? 999) > minOrden);

        if (insertAt === -1) {
            result.push(group);
        } else {
            result.splice(insertAt, 0, group);
        }
    }

    return result.sort((a, b) => (a.orden ?? 999) - (b.orden ?? 999));
});

function toggleGroup(name) {
    const idx = openGroups.value.indexOf(name);
    if (idx >= 0) {
        openGroups.value.splice(idx, 1);
    } else {
        openGroups.value.push(name);
    }
}

function isGroupOpen(name) {
    return openGroups.value.includes(name);
}

// Auto-expandir grupo cuando la ruta activa es un hijo
watch(
    () => route.path,
    (currentPath) => {
        for (const item of menuItems.value) {
            if (item.children?.some((child) => child.ruta === currentPath)) {
                if (!openGroups.value.includes(item.name)) {
                    openGroups.value.push(item.name);
                }
            }
        }
    },
    { immediate: true }
);

watch(
    showShell,
    async (isVisible) => {
        if (!isVisible || configuracionCargada.value) {
            return;
        }

        try {
            const { data } = await axios.get('/configuraciones/get/publicas');
            nombreSistema.value = data?.nombre_empresa ?? nombreSistema.value;
            configuracionCargada.value = true;
            if (!passwordModal && passwordModalRef.value) {
                passwordModal = new Modal(passwordModalRef.value);
            }
            if (!successToast && successToastRef.value) {
                successToast = new Toast(successToastRef.value, { delay: 1400 });
            }
        } catch {
            // Usa valor por defecto si falla la carga de configuraciones.
        }
    }
);

function toggleSidebar() {
    sidebarVisible.value = !sidebarVisible.value;
}

function openChangePasswordModal() {
    passwordErrors.value = [];
    passwordForm.value = emptyPasswordForm();

    if (!passwordModal && passwordModalRef.value) {
        passwordModal = new Modal(passwordModalRef.value);
    }

    passwordModal?.show();
}

function showSuccessToast(message) {
    successToastMessage.value = message;

    if (!successToast && successToastRef.value) {
        successToast = new Toast(successToastRef.value, { delay: 1400 });
    }

    successToast?.show();
}

async function submitChangePassword() {
    passwordErrors.value = [];

    const { current_password, password, password_confirmation } = passwordForm.value;
    const localErrors = [];

    if (!current_password) localErrors.push('La contrasena actual es obligatoria.');
    if (!password) localErrors.push('La nueva contrasena es obligatoria.');
    if (password.length > 0 && password.length < 8) localErrors.push('La nueva contrasena debe tener al menos 8 caracteres.');
    if (password && !/[A-Z]/.test(password)) localErrors.push('La nueva contrasena debe contener al menos una mayuscula.');
    if (password && !/\d/.test(password)) localErrors.push('La nueva contrasena debe contener al menos un numero.');
    if (password !== password_confirmation) localErrors.push('La confirmacion de la contrasena no coincide.');

    if (localErrors.length) {
        passwordErrors.value = localErrors;
        return;
    }

    passwordSaving.value = true;
    try {
        const { data: response } = await axios.put('/auth/password', passwordForm.value);
        passwordModal?.hide();

        showSuccessToast(response?.message ?? 'Contrasena actualizada correctamente.');

        if (response?.force_logout) {
            setTimeout(async () => {
                try {
                    await axios.post('/auth/logout');
                } catch {
                    // La sesion puede ya estar invalidada en servidor.
                } finally {
                    authStore.clearUser();
                    await router.push({ name: 'login' });
                }
            }, 1100);
        }
    } catch (error) {
        const serverErrors = error.response?.data?.errors;
        passwordErrors.value = serverErrors
            ? Object.values(serverErrors).flat()
            : [error.response?.data?.message ?? 'No se pudo actualizar la contrasena.'];
    } finally {
        passwordSaving.value = false;
    }
}

async function logout() {
    await axios.post('/auth/logout');
    authStore.clearUser();
    await router.push({ name: 'login' });
}
</script>
