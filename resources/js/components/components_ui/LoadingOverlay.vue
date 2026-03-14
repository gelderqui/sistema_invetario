<template>
    <div v-if="show" class="loading-overlay" role="status" aria-live="polite" aria-label="Cargando contenido">
        <div class="loading-content">
            <div class="spinner-group" aria-hidden="true">
                <div class="spinner spinner-small"></div>
                <div class="spinner spinner-large"></div>
                <div class="spinner spinner-small"></div>
            </div>
            <p class="loading-text">Cargando...</p>
        </div>
    </div>
</template>

<script setup>
defineProps({
    show: {
        type: Boolean,
        default: false,
    },
});
</script>
