<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Alertas y vencimientos</h2>
            <button class="btn btn-outline-brand" :disabled="loading" @click="load">Actualizar</button>
        </div>

        <div class="row g-3">
            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white"><strong>Bajo stock</strong></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Producto</th><th class="text-end">Stock</th></tr></thead>
                            <tbody>
                                <tr v-if="!data.bajo_stock.length"><td colspan="2" class="text-center text-body-secondary py-3">Sin alertas</td></tr>
                                <tr v-for="i in data.bajo_stock" :key="i.id">
                                    <td>{{ i.nombre }}</td>
                                    <td class="text-end">{{ Number(i.stock_actual || 0).toFixed(0) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white"><strong>Productos por vencer</strong></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Producto</th><th>Vence</th><th class="text-end">Cant.</th></tr></thead>
                            <tbody>
                                <tr v-if="!data.por_vencer.length"><td colspan="3" class="text-center text-body-secondary py-3">Sin registros</td></tr>
                                <tr v-for="i in data.por_vencer" :key="`pv-${i.lote_id}`">
                                    <td>{{ i.producto_nombre }}</td>
                                    <td>{{ fmtDate(i.fecha_vencimiento) }}</td>
                                    <td class="text-end">{{ Number(i.cantidad_disponible || 0).toFixed(0) }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-12 col-xl-4">
                <div class="card border-0 shadow-sm h-100">
                    <div class="card-header bg-white"><strong>Productos vencidos</strong></div>
                    <div class="table-responsive">
                        <table class="table table-sm mb-0">
                            <thead><tr><th>Producto</th><th>Vencio</th><th class="text-end">Cant.</th></tr></thead>
                            <tbody>
                                <tr v-if="!data.vencidos.length"><td colspan="3" class="text-center text-body-secondary py-3">Sin vencidos</td></tr>
                                <tr v-for="i in data.vencidos" :key="`v-${i.lote_id}`">
                                    <td>{{ i.producto_nombre }}</td>
                                    <td>{{ fmtDate(i.fecha_vencimiento) }}</td>
                                    <td class="text-end">{{ Number(i.cantidad_disponible || 0).toFixed(0) }}</td>
                                </tr>
                            </tbody>
                        </table>
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
const data = ref({ bajo_stock: [], por_vencer: [], vencidos: [] });

onMounted(load);

async function load() {
    loading.value = true;
    try {
        const { data: res } = await axios.get('/inventario/alertas/get');
        data.value = res?.data ?? data.value;
    } finally {
        loading.value = false;
    }
}

function fmtDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('es-GT');
}
</script>
