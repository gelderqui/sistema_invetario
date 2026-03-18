<template>
    <div v-if="totalItems > 0" class="d-flex flex-wrap justify-content-between align-items-center gap-2 p-3 border-top bg-white">
        <div class="small text-body-secondary">
            Mostrando {{ startItem }}-{{ endItem }} de {{ totalItems }}
        </div>

        <div class="d-flex align-items-center gap-2">
            <label class="small text-body-secondary mb-0">Por pagina</label>
            <select class="form-select form-select-sm" style="width: 84px;" :value="normalizedPerPage" @change="onPerPageChange">
                <option v-for="size in pageSizeOptions" :key="size" :value="size">{{ size }}</option>
            </select>
        </div>

        <nav aria-label="Paginacion">
            <ul class="pagination pagination-sm mb-0">
                <li class="page-item" :class="{ disabled: normalizedPage <= 1 }">
                    <button type="button" class="page-link" @click="changePage(normalizedPage - 1)">Anterior</button>
                </li>

                <li
                    v-for="num in visiblePages"
                    :key="num"
                    class="page-item"
                    :class="{ active: num === normalizedPage }"
                >
                    <button type="button" class="page-link" @click="changePage(num)">{{ num }}</button>
                </li>

                <li class="page-item" :class="{ disabled: normalizedPage >= totalPages }">
                    <button type="button" class="page-link" @click="changePage(normalizedPage + 1)">Siguiente</button>
                </li>
            </ul>
        </nav>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    page: {
        type: Number,
        default: 1,
    },
    perPage: {
        type: Number,
        default: 10,
    },
    totalItems: {
        type: Number,
        default: 0,
    },
    pageSizeOptions: {
        type: Array,
        default: () => [10, 25, 50, 100],
    },
    maxVisiblePages: {
        type: Number,
        default: 5,
    },
});

const emit = defineEmits(['update:page', 'update:perPage']);

const normalizedPerPage = computed(() => {
    const value = Number(props.perPage || 10);
    return value > 0 ? Math.trunc(value) : 10;
});

const totalPages = computed(() => Math.max(1, Math.ceil(Number(props.totalItems || 0) / normalizedPerPage.value)));

const normalizedPage = computed(() => {
    const value = Number(props.page || 1);
    const page = Number.isFinite(value) ? Math.trunc(value) : 1;
    return Math.min(Math.max(page, 1), totalPages.value);
});

const startItem = computed(() => {
    if (props.totalItems <= 0) return 0;
    return ((normalizedPage.value - 1) * normalizedPerPage.value) + 1;
});

const endItem = computed(() => Math.min(props.totalItems, normalizedPage.value * normalizedPerPage.value));

const visiblePages = computed(() => {
    const total = totalPages.value;
    const max = Math.max(1, Number(props.maxVisiblePages || 5));

    if (total <= max) {
        return Array.from({ length: total }, (_, i) => i + 1);
    }

    const half = Math.floor(max / 2);
    let start = Math.max(1, normalizedPage.value - half);
    let end = start + max - 1;

    if (end > total) {
        end = total;
        start = end - max + 1;
    }

    return Array.from({ length: end - start + 1 }, (_, i) => start + i);
});

function changePage(page) {
    const target = Math.min(Math.max(Number(page || 1), 1), totalPages.value);
    emit('update:page', target);
}

function onPerPageChange(event) {
    const nextPerPage = Math.max(1, Math.trunc(Number(event.target?.value || 10)));
    emit('update:perPage', nextPerPage);
    emit('update:page', 1);
}
</script>
