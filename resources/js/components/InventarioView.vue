<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">{{ pageTitle }}</h2>
            <div class="inventario-toolbar d-flex gap-2 align-items-center flex-wrap justify-content-end">
                <input
                    v-model="search"
                    type="search"
                    class="form-control inventario-search"
                    placeholder="Buscar por barra o nombre"
                    @keyup.enter="loadExistencias"
                >
                <div class="form-check form-switch mb-0">
                    <input id="only-low" v-model="soloBajoStock" class="form-check-input" type="checkbox" @change="loadExistencias">
                    <label class="form-check-label" for="only-low">Solo bajo stock</label>
                </div>
                <button class="btn btn-brand" :disabled="loading" @click="loadExistencias">Actualizar</button>
            </div>
        </div>

        <div v-if="loading" class="text-center py-5"><p class="text-body-secondary mb-0">Cargando información...</p></div>

        <div v-else-if="showStock" class="card border-0 shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Producto</th>
                            <th>Categoria</th>
                            <th>Stock</th>
                            <th>Stock Min.</th>
                            <th>Costo Prom.</th>
                            <th>Precio Venta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!existencias.length">
                            <td colspan="7" class="text-center text-body-secondary py-4">Sin datos de inventario.</td>
                        </tr>
                        <tr v-for="item in existencias" :key="item.id">
                            <td>
                                <div class="fw-semibold">{{ item.nombre }}</div>
                                <div class="small text-body-secondary">{{ item.codigo_barra || 'Sin cod. barra' }}</div>
                            </td>
                            <td>{{ item.categoria?.nombre ?? '-' }}</td>
                            <td :class="Number(item.stock_actual) <= Number(item.stock_minimo) ? 'stock-low' : 'stock-ok'">
                                {{ Number(item.stock_actual || 0).toFixed(2) }} {{ item.unidad_medida || '' }}
                            </td>
                            <td>{{ Number(item.stock_minimo || 0).toFixed(2) }}</td>
                            <td>Q {{ Number(item.costo_promedio || 0).toFixed(2) }}</td>
                            <td>Q {{ Number(item.precio_venta || 0).toFixed(2) }}</td>
                            <td>
                                <span class="badge" :class="item.activo ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ item.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div v-if="showMovimientos" class="card border-0 shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0">Movimientos recientes</h6>
                <button class="btn btn-outline-brand btn-sm" :disabled="loading" @click="loadMovimientos">Recargar</button>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Tipo</th>
                            <th>Cantidad</th>
                            <th>Stock anterior</th>
                            <th>Stock nuevo</th>
                            <th>Costo</th>
                            <th>Referencia</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!movimientos.length">
                            <td colspan="8" class="text-center text-body-secondary py-3">Sin movimientos.</td>
                        </tr>
                        <tr v-for="mov in movimientos" :key="mov.id">
                            <td>{{ formatDateTime(mov.created_at) }}</td>
                            <td>{{ mov.producto?.nombre ?? '-' }}</td>
                            <td><span class="badge text-bg-light border text-uppercase">{{ mov.tipo }}</span></td>
                            <td>{{ Number(mov.cantidad || 0).toFixed(2) }}</td>
                            <td>{{ Number(mov.stock_anterior || 0).toFixed(2) }}</td>
                            <td>{{ Number(mov.stock_nuevo || 0).toFixed(2) }}</td>
                            <td>Q {{ Number(mov.costo_unitario || 0).toFixed(2) }}</td>
                            <td>{{ mov.referencia || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import axios from '@/bootstrap';

const loading = ref(true);
const search = ref('');
const soloBajoStock = ref(false);
const existencias = ref([]);
const movimientos = ref([]);
const route = useRoute();

const showStock = computed(() => route.path !== '/inventario/movimientos');
const showMovimientos = computed(() => route.path !== '/inventario/stock');
const pageTitle = computed(() => (route.path === '/inventario/movimientos' ? 'Inventario - Movimientos' : 'Inventario - Stock'));

onMounted(async () => {
    await Promise.all([loadExistencias(), loadMovimientos()]);
});

async function loadExistencias() {
    loading.value = true;
    try {
        const { data } = await axios.get('/inventario/existencias/get', {
            params: {
                search: search.value || null,
                solo_bajo_stock: soloBajoStock.value ? 1 : 0,
            },
        });
        existencias.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadMovimientos() {
    const { data } = await axios.get('/inventario/movimientos/get');
    movimientos.value = data.data;
}

function formatDateTime(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('es-GT');
}
</script>
