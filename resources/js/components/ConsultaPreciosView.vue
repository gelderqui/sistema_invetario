<template>
    <div class="consulta-precios-view">
        <div class="mb-4">
            <h2 class="h4 mb-1">Consulta de precios</h2>
            <p class="text-body-secondary mb-0">Escribe al menos 3 caracteres o escanea un producto. La búsqueda se realiza automáticamente.</p>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body">
                <form class="row g-2 align-items-end" @submit.prevent="buscarProductos">
                    <div class="col-12 col-md">
                        <label class="form-label fw-semibold" for="consulta-precios-buscar">Producto o código de barra</label>
                        <input
                            id="consulta-precios-buscar"
                            ref="inputBuscarRef"
                            v-model.trim="buscar"
                            type="search"
                            class="form-control form-control-lg"
                            placeholder="Escriba nombre, código o palabras clave"
                            autocomplete="off"
                        >
                    </div>
                    <div class="col-12 col-md-auto d-grid">
                        <button type="submit" class="btn btn-brand btn-lg" :disabled="loading || !buscar">
                            <FontAwesomeIcon icon="fa-solid fa-magnifying-glass" class="me-2" />
                            Buscar
                        </button>
                    </div>
                </form>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5 text-body-secondary">
            Buscando productos...
        </div>

        <div v-else-if="searched && !productos.length" class="card border-0 shadow-sm">
            <div class="card-body text-center text-body-secondary py-5">
                No se encontraron productos activos para esta búsqueda.
            </div>
        </div>

        <div v-else-if="productos.length" class="row g-3">
            <div v-for="producto in productos" :key="producto.id" class="col-12 col-md-6 col-xl-4">
                <article class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <h3 class="h5 mb-2">{{ producto.nombre }}</h3>
                        <div v-if="producto.codigo_barra" class="small text-body-secondary mb-3">
                            Código: {{ producto.codigo_barra }}
                        </div>
                        <div v-else class="small text-body-secondary mb-3">Sin código de barra</div>

                        <div class="text-body-secondary small">Precio de venta</div>
                        <div class="h3 text-brand mb-3">Q {{ formatMoney(producto.precio_venta) }}</div>

                        <div class="d-flex justify-content-between gap-3 small border-top pt-3">
                            <span>Unidad: <strong>{{ producto.unidad_medida?.abreviatura || producto.unidad_medida?.nombre || '—' }}</strong></span>
                            <span>Existencia: <strong>{{ Number(producto.stock_actual || 0).toFixed(0) }}</strong></span>
                        </div>
                    </div>
                </article>
            </div>
        </div>

        <div v-else class="text-center text-body-secondary py-5">
            Escribe o escanea un código para consultar el precio.
        </div>
    </div>
</template>

<script setup>
import { nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';

import axios from '@/bootstrap';
import { formatMoney } from '@/utils/formatters';

const buscar = ref('');
const productos = ref([]);
const loading = ref(false);
const searched = ref(false);
const inputBuscarRef = ref(null);
let searchTimer = null;
let searchController = null;

onMounted(async () => {
    await nextTick();
    inputBuscarRef.value?.focus();
});

watch(buscar, (value) => {
    clearTimeout(searchTimer);

    if (value.length < 3) {
        searchController?.abort();
        productos.value = [];
        searched.value = false;
        loading.value = false;
        return;
    }

    searchTimer = setTimeout(buscarProductos, 300);
});

onBeforeUnmount(() => {
    clearTimeout(searchTimer);
    searchController?.abort();
});

async function buscarProductos() {
    const termino = buscar.value.trim();
    if (!termino) return;

    clearTimeout(searchTimer);
    searchController?.abort();

    const controller = new AbortController();
    searchController = controller;
    loading.value = true;
    searched.value = true;

    try {
        const { data } = await axios.get('/consulta-precios/get', {
            params: { buscar: termino },
            signal: controller.signal,
        });
        if (searchController === controller) {
            productos.value = data?.data ?? [];
        }
    } catch (error) {
        if (error?.code === 'ERR_CANCELED') return;

        productos.value = [];
    } finally {
        if (searchController === controller) {
            loading.value = false;
        }
    }
}
</script>
