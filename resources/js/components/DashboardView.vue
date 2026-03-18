<template>
    <section class="d-grid gap-4">
        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Cargando dashboard...</p>
        </div>

        <template v-else>
            <div class="card border-0 shadow-sm">
                <div class="card-body p-4">
                    <p class="text-uppercase small text-body-secondary mb-2">Hoy</p>
                    <h2 class="h3 mb-1">Resumen rápido del negocio</h2>
                    <p class="text-body-secondary mb-0 small">
                        {{ stats.scope === 'global' ? 'Vista global (admin)' : 'Vista personal (solo tus registros)' }}
                    </p>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="small text-uppercase text-body-secondary mb-1">Ventas hoy</div>
                            <div class="h4 mb-0">Q {{ money(stats.hoy.ventas) }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="small text-uppercase text-body-secondary mb-1">Compras hoy</div>
                            <div class="h4 mb-0">Q {{ money(stats.hoy.compras) }}</div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-md-6 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="small text-uppercase text-body-secondary mb-1">Gastos hoy</div>
                            <div class="h4 mb-0">Q {{ money(stats.hoy.gastos) }}</div>
                        </div>
                    </div>
                </div>

                <div v-if="stats.scope !== 'global'" class="col-12 col-md-6 col-xl-3">
                    <div class="card h-100 border-0 shadow-sm">
                        <div class="card-body">
                            <div class="small text-uppercase text-body-secondary mb-1">Ganancia estimada</div>
                            <div class="h4 mb-0" :class="Number(stats.hoy.ganancia_estimada) < 0 ? 'text-danger' : 'text-success'">
                                Q {{ money(stats.hoy.ganancia_estimada) }}
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="row g-4">
                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h3 class="h5 mb-3">
                                <RouterLink class="text-decoration-none" to="/inventario/alertas">Productos</RouterLink>
                            </h3>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span>Productos con stock bajo</span>
                                <strong>{{ stats.productos.bajo_stock }}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2">
                                <span>Productos por vencer</span>
                                <strong>{{ stats.productos.por_vencer }}</strong>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="col-12 col-lg-6">
                    <div class="card border-0 shadow-sm h-100">
                        <div class="card-body">
                            <h3 class="h5 mb-3">
                                <RouterLink class="text-decoration-none" to="/ventas/historial">Ventas</RouterLink>
                            </h3>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span>Ventas del día</span>
                                <strong>{{ stats.ventas.del_dia }}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center border-bottom py-2">
                                <span>Ventas del mes</span>
                                <strong>Q {{ money(stats.ventas.del_mes_total) }}</strong>
                            </div>
                            <div class="d-flex justify-content-between align-items-center py-2">
                                <span>Ticket promedio</span>
                                <strong>Q {{ money(stats.ventas.ticket_promedio) }}</strong>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { RouterLink } from 'vue-router';

import axios from '@/bootstrap';
import { formatMoney } from '@/utils/formatters';

const loading = ref(true);
const stats = ref({
    scope: 'user',
    hoy: {
        ventas: 0,
        compras: 0,
        gastos: 0,
        ganancia_estimada: 0,
    },
    productos: {
        bajo_stock: 0,
        por_vencer: 0,
    },
    ventas: {
        del_dia: 0,
        del_mes_total: 0,
        ticket_promedio: 0,
    },
});

onMounted(async () => {
    try {
        const { data } = await axios.get('/dashboard/get');
        stats.value = data.data ?? stats.value;
    } finally {
        loading.value = false;
    }
});

function money(value) {
    return formatMoney(value);
}
</script>