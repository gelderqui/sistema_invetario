<template>
    <div class="card border-0 shadow-lg">
        <div class="card-body p-5 text-center">
            <span class="badge text-bg-warning mb-3">Recurso no disponible</span>
            <h1 class="h3 mb-3">Ruta no encontrada o sin permisos</h1>
            <p class="text-body-secondary mb-4">
                Tu sesión está activa, pero la ruta no existe o tu rol no tiene acceso al recurso solicitado.
            </p>
            <router-link v-if="defaultPath" :to="defaultPath" class="btn btn-dark">
                Ir a mi módulo disponible
            </router-link>
            <p v-else class="mb-0 text-body-secondary">No tienes módulos asignados. Contacta a un administrador.</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue';

import { useAuthStore } from '@/stores/auth';
import { getDefaultAuthorizedPath } from '@/utils/authorization';

const authStore = useAuthStore();
const defaultPath = computed(() => getDefaultAuthorizedPath(authStore.user));
</script>
