<template>
    <div class="container-fluid py-3 caja-view">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Caja</h3>
                <small class="text-muted">Apertura, movimientos, arqueo y cierre.</small>
            </div>
            <div class="d-flex flex-wrap gap-2">
                <button class="btn btn-outline-brand btn-sm" @click="goSection('apertura')">Apertura</button>
                <button class="btn btn-outline-brand btn-sm" @click="goSection('movimientos')">Movimientos</button>
                <button class="btn btn-outline-brand btn-sm" @click="goSection('arqueo')">Arqueo</button>
                <button class="btn btn-outline-brand btn-sm" @click="goSection('cierre')">Cierre</button>
            </div>
        </div>

        <div class="row g-3 mb-3" v-if="resumen">
            <div class="col-6 col-lg-2"><div class="metric"><small>Apertura</small><strong>Q {{ q(resumen.total_apertura) }}</strong></div></div>
            <div class="col-6 col-lg-2"><div class="metric"><small>Ventas</small><strong>Q {{ q(resumen.total_ventas) }}</strong></div></div>
            <div class="col-6 col-lg-2"><div class="metric"><small>Gastos</small><strong>Q {{ q(resumen.total_gastos) }}</strong></div></div>
            <div class="col-6 col-lg-2"><div class="metric"><small>Compras</small><strong>Q {{ q(resumen.total_compras) }}</strong></div></div>
            <div class="col-6 col-lg-2"><div class="metric"><small>Ajustes</small><strong>Q {{ q(resumen.total_ajustes) }}</strong></div></div>
            <div class="col-6 col-lg-2"><div class="metric"><small>Sistema</small><strong>Q {{ q(resumen.monto_sistema) }}</strong></div></div>
        </div>

        <div class="alert alert-warning" v-if="!cajaActiva">No hay caja abierta para este usuario.</div>
        <div class="alert alert-danger" v-if="alertaCaja">{{ alertaCaja.mensaje }}</div>

        <div class="card border-0 shadow-sm mb-3" v-if="section === 'apertura'">
            <div class="card-header bg-transparent"><strong>Apertura de caja</strong></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Fondo inicial</label>
                        <input v-model.number="formApertura.monto_apertura" type="number" step="0.01" min="0" class="form-control" />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Fecha/hora apertura</label>
                        <input v-model="formApertura.fecha_apertura" type="datetime-local" class="form-control" />
                    </div>
                    <div class="col-12 col-md-4 d-flex gap-2">
                        <button class="btn btn-brand" :disabled="loading || !!cajaActiva" @click="abrirCaja">Abrir caja</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3" v-if="section === 'movimientos'">
            <div class="card-header bg-transparent d-flex justify-content-between align-items-center">
                <strong>Movimientos de caja</strong>
                <button class="btn btn-outline-brand btn-sm" :disabled="loading" @click="loadMovimientos">Recargar</button>
            </div>
            <div class="card-body border-bottom">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Tipo</label>
                        <select v-model="formAjuste.tipo" class="form-select">
                            <option value="ingreso_manual">Ingreso manual</option>
                            <option value="ajuste">Ajuste egreso</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Monto</label>
                        <input v-model.number="formAjuste.monto" type="number" min="0.01" step="0.01" class="form-control" />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Descripcion</label>
                        <input v-model="formAjuste.descripcion" type="text" class="form-control" />
                    </div>
                    <div class="col-12 col-md-2 d-grid">
                        <button class="btn btn-brand" :disabled="loading || !cajaActiva" @click="registrarAjuste">Registrar</button>
                    </div>
                </div>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="px-3">Fecha</th>
                            <th>Tipo</th>
                            <th>Descripcion</th>
                            <th class="text-end pe-3">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!movimientos.length">
                            <td colspan="4" class="text-center text-muted py-3">Sin movimientos.</td>
                        </tr>
                        <tr v-for="row in movimientos" :key="row.id">
                            <td class="px-3">{{ dateTime(row.fecha) }}</td>
                            <td>{{ row.tipo }}</td>
                            <td>{{ row.descripcion || '-' }}</td>
                            <td class="text-end pe-3" :class="Number(row.monto) < 0 ? 'text-danger' : 'text-success'">Q {{ q(row.monto) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3" v-if="section === 'arqueo'">
            <div class="card-header bg-transparent"><strong>Arqueo de caja</strong></div>
            <div class="card-body">
                <div class="row g-3 mb-3">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Total sistema</label>
                        <input :value="q(resumen?.monto_sistema || 0)" class="form-control" disabled />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Dinero contado</label>
                        <input v-model.number="formArqueo.monto_contado" type="number" min="0" step="0.01" class="form-control" />
                    </div>
                    <div class="col-12 col-md-4 d-grid align-items-end">
                        <button class="btn btn-brand mt-4" :disabled="loading || !cajaActiva" @click="registrarArqueo">Registrar arqueo</button>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6 col-md-3" v-for="b in billetes" :key="b.denominacion">
                        <label class="form-label">Q{{ b.denominacion }}</label>
                        <input v-model.number="b.cantidad" type="number" min="0" step="1" class="form-control" />
                    </div>
                </div>
                <small class="text-muted d-block mt-2">Total por billetes: Q {{ q(totalBilletes) }}</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm" v-if="section === 'cierre'">
            <div class="card-header bg-transparent"><strong>Cierre de caja</strong></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Monto contado final</label>
                        <input v-model.number="formCierre.monto_contado" type="number" min="0" step="0.01" class="form-control" />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Diferencia estimada</label>
                        <input :value="q((Number(formCierre.monto_contado || 0) - Number(resumen?.monto_sistema || 0)))" class="form-control" disabled />
                    </div>
                    <div class="col-12 col-md-4 d-grid">
                        <button class="btn btn-danger" :disabled="loading || !cajaActiva" @click="cerrarCaja">Cerrar caja</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import { useRoute, useRouter } from 'vue-router';
import axios from '@/bootstrap';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const cajaActiva = ref(null);
const resumen = ref(null);
const movimientos = ref([]);
const alertaCaja = ref(null);

const formApertura = ref({
    monto_apertura: 0,
    fecha_apertura: '',
});

const formAjuste = ref({
    tipo: 'ingreso_manual',
    monto: null,
    descripcion: '',
});

const formArqueo = ref({
    monto_contado: null,
});

const formCierre = ref({
    monto_contado: null,
});

const billetes = ref([
    { denominacion: 200, cantidad: 0 },
    { denominacion: 100, cantidad: 0 },
    { denominacion: 50, cantidad: 0 },
    { denominacion: 20, cantidad: 0 },
    { denominacion: 10, cantidad: 0 },
    { denominacion: 5, cantidad: 0 },
    { denominacion: 1, cantidad: 0 },
]);

const section = computed(() => {
    const path = route.path || '/caja/apertura';
    if (path.includes('/movimientos')) return 'movimientos';
    if (path.includes('/arqueo')) return 'arqueo';
    if (path.includes('/cierre')) return 'cierre';
    return 'apertura';
});

const totalBilletes = computed(() => billetes.value.reduce((acc, row) => acc + Number(row.denominacion) * Number(row.cantidad || 0), 0));

watch(totalBilletes, (v) => {
    if (section.value === 'arqueo' && v > 0) {
        formArqueo.value.monto_contado = Number(v.toFixed(2));
    }
});

onMounted(async () => {
    await loadEstado();
    await loadMovimientos();
});

function q(value) {
    return Number(value || 0).toFixed(2);
}

function dateTime(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString();
}

function goSection(name) {
    router.push(`/caja/${name}`);
}

async function loadEstado() {
    loading.value = true;
    try {
        const { data } = await axios.get('/caja/get/estado');
        cajaActiva.value = data?.data?.caja_activa ?? null;
        resumen.value = data?.data?.resumen ?? null;
        if (resumen.value?.monto_sistema != null) {
            formCierre.value.monto_contado = Number(resumen.value.monto_sistema);
        }
    } finally {
        loading.value = false;
    }
}

async function loadMovimientos() {
    try {
        const { data } = await axios.get('/caja/get/movimientos');
        movimientos.value = data?.data?.movimientos ?? [];
        if (data?.data?.resumen) {
            resumen.value = data.data.resumen;
        }
    } catch {
        movimientos.value = [];
    }
}

async function abrirCaja() {
    loading.value = true;
    try {
        const { data } = await axios.post('/caja/apertura', {
            ...formApertura.value,
            monto_apertura: Number(formApertura.value.monto_apertura || 0),
            fecha_apertura: formApertura.value.fecha_apertura || undefined,
        });
        alertaCaja.value = data?.data?.alerta ?? null;
        await loadEstado();
        await loadMovimientos();
    } finally {
        loading.value = false;
    }
}

async function registrarAjuste() {
    loading.value = true;
    try {
        await axios.post('/caja/movimientos/ajuste', {
            ...formAjuste.value,
            monto: Number(formAjuste.value.monto || 0),
        });
        formAjuste.value.monto = null;
        formAjuste.value.descripcion = '';
        await loadEstado();
        await loadMovimientos();
    } finally {
        loading.value = false;
    }
}

async function registrarArqueo() {
    loading.value = true;
    try {
        const { data } = await axios.post('/caja/arqueo', {
            monto_contado: Number(formArqueo.value.monto_contado || 0),
            billetes: billetes.value,
        });
        alertaCaja.value = data?.data?.alerta ?? null;
        await loadEstado();
    } finally {
        loading.value = false;
    }
}

async function cerrarCaja() {
    loading.value = true;
    try {
        const { data } = await axios.post('/caja/cierre', {
            monto_contado: Number(formCierre.value.monto_contado || 0),
        });
        alertaCaja.value = data?.data?.alerta ?? null;
        await loadEstado();
        await loadMovimientos();
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.metric {
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 0.75rem;
    padding: 0.75rem;
}

.metric small {
    display: block;
    color: #5f6b7a;
}

.metric strong {
    font-size: 1.05rem;
}
</style>
