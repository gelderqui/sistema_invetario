<template>
    <div class="container-fluid py-3 reportes-view">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-3">
            <div>
                <h3 class="mb-1">Reportes</h3>
                <small class="text-muted">Consolidado de gastos y caja.</small>
            </div>
            <span class="badge text-bg-light border">
                Alcance: {{ reportes.scope === 'global' ? 'Global' : 'Personal' }}
            </span>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Desde</label>
                        <input v-model="filtros.desde" type="date" class="form-control" />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Hasta</label>
                        <input v-model="filtros.hasta" type="date" class="form-control" />
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button class="btn btn-brand" :disabled="loading" @click="loadAll">Actualizar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mb-3">
            <div class="col-12 col-lg-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Total del periodo (Gastos)</small>
                        <h4 class="mb-0">Q {{ Number(reportes.total_periodo || 0).toFixed(2) }}</h4>
                    </div>
                </div>
            </div>
            <div class="col-12 col-lg-8">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-body">
                        <small class="text-muted d-block mb-1">Reportes implementados</small>
                        <span class="badge rounded-pill text-bg-primary-subtle border border-primary-subtle text-primary-emphasis me-2">Gastos por dia</span>
                        <span class="badge rounded-pill text-bg-primary-subtle border border-primary-subtle text-primary-emphasis me-2">Gastos por tipo</span>
                        <span class="badge rounded-pill text-bg-primary-subtle border border-primary-subtle text-primary-emphasis">Caja del dia</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent">
                        <strong>Gastos por dia</strong>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="px-3">Fecha</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end pe-3">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!reportes.por_dia.length">
                                        <td colspan="3" class="text-center text-muted py-3">Sin datos para el rango seleccionado.</td>
                                    </tr>
                                    <tr v-for="row in reportes.por_dia" :key="row.fecha">
                                        <td class="px-3">{{ row.fecha }}</td>
                                        <td class="text-end">{{ row.cantidad }}</td>
                                        <td class="text-end pe-3">Q {{ Number(row.total || 0).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-6">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-transparent">
                        <strong>Gastos por tipo</strong>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="px-3">Tipo</th>
                                        <th class="text-end">Cantidad</th>
                                        <th class="text-end pe-3">Total</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!reportes.por_tipo.length">
                                        <td colspan="3" class="text-center text-muted py-3">Sin datos para el rango seleccionado.</td>
                                    </tr>
                                    <tr v-for="row in reportes.por_tipo" :key="row.tipo_gasto_id">
                                        <td class="px-3">{{ row.tipo_nombre }}</td>
                                        <td class="text-end">{{ row.cantidad }}</td>
                                        <td class="text-end pe-3">Q {{ Number(row.total || 0).toFixed(2) }}</td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-3 mt-1">
            <div class="col-12">
                <div class="card border-0 shadow-sm">
                    <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                        <strong>Reporte de caja del dia</strong>
                        <small class="text-muted">Fecha: {{ reporteCaja.fecha || filtros.hasta }}</small>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                            <table class="table table-sm align-middle mb-0">
                                <thead>
                                    <tr>
                                        <th class="px-3">Cajero</th>
                                        <th>Estado</th>
                                        <th class="text-end">Apertura</th>
                                        <th class="text-end">Ventas</th>
                                        <th class="text-end">Gastos</th>
                                        <th class="text-end">Sistema</th>
                                        <th class="text-end pe-3">Diferencia</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr v-if="!reporteCaja.items.length">
                                        <td colspan="7" class="text-center text-muted py-3">Sin cajas para la fecha seleccionada.</td>
                                    </tr>
                                    <tr v-for="row in reporteCaja.items" :key="row.id">
                                        <td class="px-3">{{ row.usuario || '-' }}</td>
                                        <td>{{ row.estado }}</td>
                                        <td class="text-end">Q {{ Number(row.total_apertura || 0).toFixed(2) }}</td>
                                        <td class="text-end">Q {{ Number(row.total_ventas || 0).toFixed(2) }}</td>
                                        <td class="text-end">Q {{ Number(row.total_gastos || 0).toFixed(2) }}</td>
                                        <td class="text-end">Q {{ Number(row.monto_sistema || 0).toFixed(2) }}</td>
                                        <td class="text-end pe-3" :class="Number(row.diferencia_final || 0) < 0 ? 'text-danger' : 'text-success'">
                                            Q {{ Number(row.diferencia_final || 0).toFixed(2) }}
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from '@/bootstrap';

const loading = ref(false);
const filtros = ref({
    desde: new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().slice(0, 10),
    hasta: new Date().toISOString().slice(0, 10),
});

const reportes = ref({
    scope: 'usuario',
    total_periodo: 0,
    por_dia: [],
    por_tipo: [],
});

const reporteCaja = ref({
    fecha: '',
    items: [],
});

onMounted(async () => {
    await loadAll();
});

async function loadAll() {
    await Promise.all([loadReportes(), loadReporteCaja()]);
}

async function loadReportes() {
    loading.value = true;
    try {
        const { data } = await axios.get('/reportes/gastos', { params: filtros.value });
        reportes.value = data?.data ?? reportes.value;
    } finally {
        loading.value = false;
    }
}

async function loadReporteCaja() {
    const { data } = await axios.get('/caja/get/reporte-dia', {
        params: { fecha: filtros.value.hasta },
    });
    reporteCaja.value = data?.data ?? reporteCaja.value;
}
</script>

<style scoped>
.reportes-view .table > :not(caption) > * > * {
    padding-top: 0.55rem;
    padding-bottom: 0.55rem;
}
</style>
