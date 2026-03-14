<template>
    <div v-if="errors.length" class="alert alert-danger py-2 mb-0">
        <ul class="mb-0 ps-3">
            <li v-for="(error, index) in errors" :key="index">{{ error }}</li>
        </ul>
    </div>
</template>

<script setup>
defineProps({
    errors: {
        type: Array,
        default: () => [],
    },
});
</script>
