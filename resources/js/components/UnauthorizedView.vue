<template>
    <div class="card border-0 shadow-lg">
        <div class="card-body p-5 text-center">
            <span class="badge text-bg-warning mb-3">Recurso no disponible</span>
            <h1 class="h3 mb-3">Ruta no encontrada o sin permisos</h1>
            <p class="text-body-secondary mb-4">
                Tu sesión está activa, pero la ruta no existe o tu rol no tiene acceso al recurso solicitado.
            </p>
            <router-link :to="{ name: 'dashboard' }" class="btn btn-dark">
                Volver al dashboard
            </router-link>
        </div>
    </div>
</template>