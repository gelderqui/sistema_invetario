<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <div>
                <h2 class="h4 mb-0">Reportes</h2>
                <p class="text-body-secondary mb-0 small">Utilidad e inventario valorizado.</p>
            </div>
            <button class="btn btn-outline-brand" :disabled="loading" @click="loadReport">Actualizar</button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Generando reporte...</p>
        </div>

        <div v-else class="d-grid gap-4">
            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-1">Estado general del negocio</h6>
                    <small class="text-body-secondary">Foto actual: efectivo disponible + inventario valorizado.</small>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-3" v-for="item in estadoGeneralCards" :key="item.label">
                            <div class="report-stat h-100">
                                <div class="report-stat-label">{{ item.label }}</div>
                                <div class="report-stat-value" :class="item.className">Q {{ formatMoney(item.value) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Concepto</th>
                                    <th class="text-end">Monto</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Caja POS abierta</td>
                                    <td class="text-end">Q {{ formatMoney(report.estado_general.dinero_disponible.caja_pos) }}</td>
                                </tr>
                                <tr>
                                    <td>Caja general</td>
                                    <td class="text-end">Q {{ formatMoney(report.estado_general.dinero_disponible.caja_general) }}</td>
                                </tr>
                                <tr>
                                    <td>Banco</td>
                                    <td class="text-end">Q {{ formatMoney(report.estado_general.dinero_disponible.banco) }}</td>
                                </tr>
                                <tr>
                                    <td>Total efectivo</td>
                                    <td class="text-end fw-semibold">Q {{ formatMoney(report.estado_general.dinero_disponible.total_efectivo) }}</td>
                                </tr>
                                <tr>
                                    <td>Inventario valorizado</td>
                                    <td class="text-end">Q {{ formatMoney(report.estado_general.inventario) }}</td>
                                </tr>
                                <tr>
                                    <td>Total negocio</td>
                                    <td class="text-end fw-semibold">Q {{ formatMoney(report.estado_general.total_negocio) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-1">Utilidad</h6>
                    <small class="text-body-secondary">Periodo: {{ rangeLabel }}</small>
                </div>
                <div class="card-body">
                    <div class="row g-3 align-items-end mb-4">
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Desde</label>
                            <input v-model="filters.desde" type="date" class="form-control">
                        </div>
                        <div class="col-12 col-md-3">
                            <label class="form-label fw-semibold">Hasta</label>
                            <input v-model="filters.hasta" type="date" class="form-control">
                        </div>
                        <div class="col-12 col-md-3 d-grid">
                            <button class="btn btn-brand" :disabled="loading" @click="loadReport">Filtrar utilidad</button>
                        </div>
                        <div class="col-12 col-md-3 d-grid">
                            <button class="btn btn-outline-brand" :disabled="loading" @click="resetFilters">Mes actual</button>
                        </div>
                    </div>

                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-4 col-xl-3" v-for="item in utilidadCards" :key="item.label">
                            <div class="report-stat h-100">
                                <div class="report-stat-label">{{ item.label }}</div>
                                <div class="report-stat-value" :class="item.className">Q {{ formatMoney(item.value) }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table align-middle mb-0">
                            <thead>
                                <tr>
                                    <th>Concepto</th>
                                    <th class="text-end">Total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Ventas brutas</td>
                                    <td class="text-end">Q {{ formatMoney(report.utilidad.ventas_brutas) }}</td>
                                </tr>
                                <tr>
                                    <td>Devoluciones</td>
                                    <td class="text-end text-danger">Q {{ formatMoney(report.utilidad.devoluciones) }}</td>
                                </tr>
                                <tr>
                                    <td>Ventas netas</td>
                                    <td class="text-end">Q {{ formatMoney(report.utilidad.ventas_netas) }}</td>
                                </tr>
                                <tr>
                                    <td>Costo de ventas</td>
                                    <td class="text-end text-danger">Q {{ formatMoney(report.utilidad.costo_ventas) }}</td>
                                </tr>
                                <tr>
                                    <td>Reversion costo por devoluciones</td>
                                    <td class="text-end text-success">Q {{ formatMoney(report.utilidad.costo_devoluciones) }}</td>
                                </tr>
                                <tr>
                                    <td>Costo de ventas neto</td>
                                    <td class="text-end">Q {{ formatMoney(report.utilidad.costo_ventas_neto) }}</td>
                                </tr>
                                <tr>
                                    <td>Ganancia bruta</td>
                                    <td class="text-end fw-semibold" :class="numberTone(report.utilidad.ganancia_bruta)">Q {{ formatMoney(report.utilidad.ganancia_bruta) }}</td>
                                </tr>
                                <tr>
                                    <td>Gastos</td>
                                    <td class="text-end text-danger">Q {{ formatMoney(report.utilidad.gastos) }}</td>
                                </tr>
                                <tr>
                                    <td>Perdidas de inventario</td>
                                    <td class="text-end text-danger">Q {{ formatMoney(report.utilidad.perdidas_inventario) }}</td>
                                </tr>
                                <tr>
                                    <td>Ganancia neta</td>
                                    <td class="text-end fw-bold" :class="numberTone(report.utilidad.ganancia_neta)">Q {{ formatMoney(report.utilidad.ganancia_neta) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="card border-0 shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-1">Inventario valorizado</h6>
                    <small class="text-body-secondary">Valorizado por lotes disponibles (cantidad remanente por costo unitario).</small>
                </div>
                <div class="card-body">
                    <div class="row g-3 mb-4">
                        <div class="col-12 col-md-6">
                            <div class="report-stat h-100">
                                <div class="report-stat-label">Inventario total</div>
                                <div class="report-stat-value">Q {{ formatMoney(report.inventario_valorizado.total) }}</div>
                            </div>
                        </div>
                        <div class="col-12 col-md-6">
                            <div class="report-stat h-100">
                                <div class="report-stat-label">Productos con stock</div>
                                <div class="report-stat-value">{{ report.inventario_valorizado.productos }}</div>
                            </div>
                        </div>
                    </div>

                    <div class="row g-4">
                        <div class="col-12 col-xl-5">
                            <h6 class="small text-uppercase text-body-secondary mb-3">Valor por categoria</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Categoria</th>
                                            <th class="text-end">Productos</th>
                                            <th class="text-end">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!inventarioCategoriasOrdenadas.length">
                                            <td colspan="3" class="text-center text-body-secondary py-3">Sin categorias valorizadas.</td>
                                        </tr>
                                        <tr v-for="item in inventarioCategoriasOrdenadas" :key="item.categoria">
                                            <td>{{ item.categoria }}</td>
                                            <td class="text-end">{{ item.productos }}</td>
                                            <td class="text-end">Q {{ formatMoney(item.valor_total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <div class="col-12 col-xl-7">
                            <h6 class="small text-uppercase text-body-secondary mb-3">Top productos valorizados</h6>
                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Categoria</th>
                                            <th class="text-end">Stock</th>
                                            <th class="text-end">Costo unit.</th>
                                            <th class="text-end">Valor</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!inventarioProductosOrdenados.length">
                                            <td colspan="5" class="text-center text-body-secondary py-3">Sin productos con stock.</td>
                                        </tr>
                                        <tr v-for="item in inventarioProductosOrdenados" :key="item.id">
                                            <td>{{ item.nombre }}</td>
                                            <td>{{ item.categoria }}</td>
                                            <td class="text-end">{{ Number(item.stock_actual || 0).toFixed(0) }}</td>
                                            <td class="text-end">Q {{ formatMoney(item.costo_unitario_valorizacion ?? item.costo_promedio) }}</td>
                                            <td class="text-end fw-semibold">Q {{ formatMoney(item.valor_total) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';

import axios from '@/bootstrap';
import { formatMoney } from '@/utils/formatters';

const loading = ref(false);
const filters = ref(defaultFilters());
const report = ref(emptyReport());

const rangeLabel = computed(() => `${formatDate(filters.value.desde)} a ${formatDate(filters.value.hasta)}`);
const estadoGeneralCards = computed(() => [
    {
        label: 'Total efectivo',
        value: report.value.estado_general.dinero_disponible.total_efectivo,
        className: '',
    },
    {
        label: 'Inventario valorizado',
        value: report.value.estado_general.inventario,
        className: '',
    },
    {
        label: 'Total negocio',
        value: report.value.estado_general.total_negocio,
        className: '',
    },
]);
const utilidadCards = computed(() => [
    { label: 'Ventas netas', value: report.value.utilidad.ventas_netas, className: '' },
    { label: 'Ganancia bruta', value: report.value.utilidad.ganancia_bruta, className: numberTone(report.value.utilidad.ganancia_bruta) },
    { label: 'Gastos', value: report.value.utilidad.gastos, className: 'text-danger' },
    { label: 'Perdidas inventario', value: report.value.utilidad.perdidas_inventario, className: 'text-danger' },
    { label: 'Ganancia neta', value: report.value.utilidad.ganancia_neta, className: numberTone(report.value.utilidad.ganancia_neta) },
]);
const inventarioCategoriasOrdenadas = computed(() => {
    return [...(report.value.inventario_valorizado.categorias ?? [])]
        .sort((a, b) => Number(b?.valor_total || 0) - Number(a?.valor_total || 0));
});
const inventarioProductosOrdenados = computed(() => {
    return [...(report.value.inventario_valorizado.top_productos ?? [])]
        .sort((a, b) => Number(b?.valor_total || 0) - Number(a?.valor_total || 0));
});

onMounted(loadReport);

async function loadReport() {
    loading.value = true;
    try {
        const { data } = await axios.get('/reportes/get', {
            params: {
                desde: filters.value.desde,
                hasta: filters.value.hasta,
            },
        });

        report.value = data.data ?? emptyReport();
        filters.value.desde = report.value.meta.desde;
        filters.value.hasta = report.value.meta.hasta;
    } finally {
        loading.value = false;
    }
}

function resetFilters() {
    filters.value = defaultFilters();
    loadReport();
}

function defaultFilters() {
    const now = new Date();
    return {
        desde: toIsoDate(new Date(now.getFullYear(), now.getMonth(), 1)),
        hasta: toIsoDate(now),
    };
}

function emptyReport() {
    return {
        meta: {
            desde: defaultFilters().desde,
            hasta: defaultFilters().hasta,
        },
        estado_general: {
            dinero_disponible: {
                caja_pos: 0,
                caja_general: 0,
                banco: 0,
                total_efectivo: 0,
            },
            inventario: 0,
            total_negocio: 0,
            inversion_inicial: 0,
            resultado: 0,
        },
        utilidad: {
            ventas_brutas: 0,
            devoluciones: 0,
            ventas_netas: 0,
            costo_ventas: 0,
            costo_devoluciones: 0,
            costo_ventas_neto: 0,
            ganancia_bruta: 0,
            gastos: 0,
            perdidas_inventario: 0,
            ganancia_neta: 0,
        },
        flujo_caja: {
            ingresos: 0,
            egresos: 0,
            neto: 0,
            detalle: [],
        },
        inventario_valorizado: {
            total: 0,
            productos: 0,
            categorias: [],
            top_productos: [],
        },
    };
}

function toIsoDate(date) {
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(`${value}T00:00:00`).toLocaleDateString('es-GT');
}

function numberTone(value) {
    if (Number(value) > 0) return 'text-success';
    if (Number(value) < 0) return 'text-danger';
    return 'text-body-emphasis';
}
</script>

<style scoped>
.report-stat {
    border: 1px solid #e9ecef;
    border-radius: 0.75rem;
    background: #f8f9fa;
    padding: 1rem;
}

.report-stat-label {
    color: #6c757d;
    font-size: 0.85rem;
    margin-bottom: 0.35rem;
}

.report-stat-value {
    color: #212529;
    font-size: 1.35rem;
    font-weight: 700;
    line-height: 1.2;
}
</style>