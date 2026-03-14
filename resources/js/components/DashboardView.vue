<template>
    <section class="d-grid gap-4">
        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <p class="text-uppercase small text-body-secondary mb-2">Fase 1 completada</p>
                <h2 class="h3 mb-3">Base del sistema lista para crecer</h2>
                <p class="text-body-secondary mb-0">
                    Ya existe autenticación SPA con Sanctum, carga del usuario autenticado, shell Vue y control base de permisos.
                </p>
            </div>
        </div>

        <div class="row g-4">
            <div v-for="module in modules" :key="module.title" class="col-12 col-md-6 col-xl-3">
                <div class="card h-100 border-0 shadow-sm">
                    <div class="card-body">
                        <div class="d-flex justify-content-between align-items-start mb-3">
                            <h3 class="h5 mb-0">{{ module.title }}</h3>
                            <span class="badge" :class="module.enabled ? 'text-bg-success' : 'text-bg-secondary'">
                                {{ module.enabled ? 'Listo' : 'Sin permiso' }}
                            </span>
                        </div>
                        <p class="text-body-secondary mb-0">{{ module.description }}</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4">
                <h3 class="h5 mb-3">Contexto de sesión</h3>
                <div class="row g-4">
                    <div class="col-12 col-lg-6">
                        <h4 class="h6 text-uppercase text-body-secondary">Roles</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span v-for="role in roleItems" :key="role.code" class="badge text-bg-dark">
                                {{ role.name }}
                            </span>
                        </div>
                    </div>
                    <div class="col-12 col-lg-6">
                        <h4 class="h6 text-uppercase text-body-secondary">Permisos efectivos</h4>
                        <div class="d-flex flex-wrap gap-2">
                            <span
                                v-for="permission in authStore.user?.permissions ?? []"
                                :key="permission.code"
                                class="badge rounded-pill text-bg-light border"
                            >
                                {{ permission.code }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>
<script setup>
import { computed } from 'vue';

import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();

const permissionCodes = computed(() => new Set((authStore.user?.permissions ?? []).map((permission) => permission.code)));
const roleItems = computed(() => (authStore.user?.role ? [authStore.user.role] : []));

const modules = computed(() => [
    {
        title: 'Inventario',
        description: 'Productos, existencias, ajustes y kardex.',
        enabled: permissionCodes.value.has('inventario'),
    },
    {
        title: 'Compras',
        description: 'Proveedores, órdenes e ingresos de stock.',
        enabled: permissionCodes.value.has('compras'),
    },
    {
        title: 'POS',
        description: 'Caja, ventas rápidas y recibos.',
        enabled: permissionCodes.value.has('pos.access'),
    },
    {
        title: 'Reportes',
        description: 'Exportación a Excel y salidas PDF.',
        enabled: permissionCodes.value.has('reports.view'),
    },
]);
</script>