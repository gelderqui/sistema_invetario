<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">{{ pageTitle }}</h2>
            <button class="btn btn-outline-brand" :disabled="loading" @click="loadExistencias">Actualizar</button>
        </div>

        <div v-if="showStock" class="card border-0 shadow-sm mb-3">
            <div class="card-body row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Categoria</label>
                    <select v-model="categoriaId" class="form-select" @change="loadExistencias">
                        <option :value="null">Todas</option>
                        <option v-for="categoria in categorias" :key="categoria.id" :value="categoria.id">
                            {{ categoria.nombre }}
                        </option>
                    </select>
                </div>
                <div class="col-12 col-md-5">
                    <label class="form-label fw-semibold">Nombre o codigo de barra</label>
                    <input
                        v-model="search"
                        type="search"
                        class="form-control"
                        placeholder="Buscar por barra o nombre"
                        @keyup.enter="loadExistencias"
                    >
                </div>
                <div class="col-12 col-md-2">
                    <div class="form-check form-switch pt-4">
                        <input id="only-low" v-model="soloBajoStock" class="form-check-input" type="checkbox" @change="loadExistencias">
                        <label class="form-check-label" for="only-low">Solo bajo stock</label>
                    </div>
                </div>
                <div class="col-12 col-md-2 d-grid">
                    <button class="btn btn-brand" :disabled="loading" @click="loadExistencias">Filtrar</button>
                </div>
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
                            <th>Venta Prom.</th>
                            <th>Costo Ult.</th>
                            <th>Precio Venta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!existencias.length">
                            <td colspan="9" class="text-center text-body-secondary py-4">Sin datos de inventario.</td>
                        </tr>
                        <tr v-for="item in existencias" :key="item.id">
                            <td>
                                <div class="fw-semibold">{{ item.nombre }}</div>
                                <div class="small text-body-secondary">{{ item.codigo_barra || 'Sin cod. barra' }}</div>
                            </td>
                            <td>{{ item.categoria?.nombre ?? '-' }}</td>
                            <td :class="Number(item.stock_actual) <= Number(item.stock_minimo) ? 'stock-low' : 'stock-ok'">
                                {{ Number(item.stock_actual || 0).toFixed(0) }} {{ item.unidad_medida_abreviatura || '' }}
                            </td>
                            <td>{{ Number(item.stock_minimo || 0).toFixed(0) }}</td>
                            <td>Q {{ Number(item.costo_promedio || 0).toFixed(2) }}</td>
                            <td>Q {{ Number(item.precio_venta_promedio || 0).toFixed(2) }}</td>
                            <td>Q {{ Number(item.costo_ultimo || 0).toFixed(2) }}</td>
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
                <h6 class="mb-0">{{ movimientosTitle }}</h6>
                <button class="btn btn-outline-brand btn-sm" :disabled="loading" @click="loadMovimientos">Recargar</button>
            </div>
            <div class="card-body row g-3 align-items-end border-top">
                <div class="col-12 col-md-2">
                    <label class="form-label fw-semibold">Fecha desde</label>
                    <input v-model="movFechaDesde" type="date" class="form-control" />
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label fw-semibold">Fecha hasta</label>
                    <input v-model="movFechaHasta" type="date" class="form-control" />
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Categoria</label>
                    <select v-model="movCategoriaId" class="form-select" @change="onMovCategoriaChange">
                        <option :value="null">Todas</option>
                        <option v-for="categoria in movCategorias" :key="categoria.id" :value="categoria.id">
                            {{ categoria.nombre }}
                        </option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Producto</label>
                    <select v-model="movProductoId" class="form-select">
                        <option :value="null">Todos</option>
                        <option v-for="producto in movProductos" :key="producto.id" :value="producto.id">
                            {{ producto.nombre }}
                        </option>
                    </select>
                </div>
                <div class="col-12 col-md-2 d-grid gap-2">
                    <button class="btn btn-brand" :disabled="loading" @click="loadMovimientos">Filtrar</button>
                    <button class="btn btn-outline-brand" :disabled="loading" @click="resetMovimientosFiltros">Limpiar</button>
                </div>
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
                        <tr v-if="!totalMovimientosInventario">
                            <td colspan="8" class="text-center text-body-secondary py-3">Sin movimientos.</td>
                        </tr>
                        <tr v-for="mov in paginatedMovimientos" :key="mov.id">
                            <td>{{ formatDateTime(mov.created_at) }}</td>
                            <td>{{ mov.producto?.nombre ?? '-' }}</td>
                            <td><span class="badge text-bg-light border text-uppercase">{{ mov.tipo }}</span></td>
                            <td>{{ Number(mov.cantidad || 0).toFixed(0) }}</td>
                            <td>{{ Number(mov.stock_anterior || 0).toFixed(0) }}</td>
                            <td>{{ Number(mov.stock_nuevo || 0).toFixed(0) }}</td>
                            <td>Q {{ Number(mov.costo_unitario || 0).toFixed(2) }}</td>
                            <td>{{ mov.referencia || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination
                v-model:page="pageMovimientos"
                v-model:perPage="perPageMovimientos"
                :total-items="totalMovimientosInventario"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import { useRoute } from 'vue-router';

import axios from '@/bootstrap';
import TablePagination from '@/components/components_ui/TablePagination.vue';
import { formatMoney } from '@/utils/formatters';

const loading = ref(true);
const search = ref('');
const categoriaId = ref(null);
const soloBajoStock = ref(false);
const existencias = ref([]);
const categorias = ref([]);
const movimientos = ref([]);
const movCategorias = ref([]);
const movProductos = ref([]);
const movCategoriaId = ref(null);
const movProductoId = ref(null);
const movFechaDesde = ref(defaultMovFechaDesde());
const movFechaHasta = ref(defaultMovFechaHasta());
const pageMovimientos = ref(1);
const perPageMovimientos = ref(10);
const route = useRoute();

const showStock = computed(() => route.path !== '/inventario/movimientos');
const showMovimientos = computed(() => route.path !== '/inventario/stock');
const pageTitle = computed(() => (route.path === '/inventario/movimientos' ? 'Inventario - Movimientos' : 'Inventario - Stock'));
const movimientosTitle = computed(() => {
    const desde = movFechaDesde.value ? formatDate(movFechaDesde.value) : 'inicio';
    const hasta = movFechaHasta.value ? formatDate(movFechaHasta.value) : 'hoy';
    return `Movimientos (${desde} a ${hasta})`;
});
const totalMovimientosInventario = computed(() => movimientos.value.length);
const totalPagesMovimientos = computed(() => Math.max(1, Math.ceil(totalMovimientosInventario.value / perPageMovimientos.value)));
const safePageMovimientos = computed(() => Math.min(Math.max(pageMovimientos.value, 1), totalPagesMovimientos.value));
const paginatedMovimientos = computed(() => {
    const start = (safePageMovimientos.value - 1) * perPageMovimientos.value;
    return movimientos.value.slice(start, start + perPageMovimientos.value);
});

onMounted(async () => {
    await Promise.all([loadExistencias(), loadMovimientos()]);
});

async function loadExistencias() {
    loading.value = true;
    try {
        const { data } = await axios.get('/inventario/existencias/get', {
            params: {
                search: search.value || null,
                categoria_id: categoriaId.value || null,
                solo_bajo_stock: soloBajoStock.value ? 1 : 0,
            },
        });
        existencias.value = data.data;
        categorias.value = data.categorias ?? [];
    } finally {
        loading.value = false;
    }
}

async function loadMovimientos() {
    const { data } = await axios.get('/inventario/movimientos/get', {
        params: {
            fecha_desde: movFechaDesde.value || null,
            fecha_hasta: movFechaHasta.value || null,
            categoria_id: movCategoriaId.value || null,
            producto_id: movProductoId.value || null,
        },
    });
    movimientos.value = data.data;
    movCategorias.value = data.categorias ?? [];
    movProductos.value = data.productos ?? [];
}

function resetMovimientosFiltros() {
    movCategoriaId.value = null;
    movProductoId.value = null;
    movFechaDesde.value = defaultMovFechaDesde();
    movFechaHasta.value = defaultMovFechaHasta();
    loadMovimientos();
}

function onMovCategoriaChange() {
    movProductoId.value = null;
    loadMovimientos();
}

function defaultMovFechaHasta() {
    return toISODate(new Date());
}

function defaultMovFechaDesde() {
    const fecha = new Date();
    fecha.setDate(fecha.getDate() - 30);
    return toISODate(fecha);
}

function toISODate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(`${value}T00:00:00`).toLocaleDateString('es-GT');
}

function formatDateTime(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('es-GT');
}
</script>
