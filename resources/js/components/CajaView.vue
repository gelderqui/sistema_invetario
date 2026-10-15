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
            <div class="col-6 col-lg"><div class="metric"><small>Apertura</small><strong>Q {{ q(resumen.total_apertura) }}</strong></div></div>
            <div class="col-6 col-lg"><div class="metric"><small>Ventas</small><strong>Q {{ q(resumen.total_ventas) }}</strong></div></div>
            <div class="col-6 col-lg"><div class="metric"><small>Ingresos</small><strong>Q {{ q(resumen.total_ingresos) }}</strong></div></div>
            <div class="col-6 col-lg"><div class="metric"><small>Gastos</small><strong>Q {{ q(resumen.total_gastos) }}</strong></div></div>
            <div class="col-6 col-lg"><div class="metric"><small>Compras</small><strong>Q {{ q(resumen.total_compras) }}</strong></div></div>
            <div class="col-6 col-lg"><div class="metric"><small>Egresos</small><strong>Q {{ q(resumen.total_egresos) }}</strong></div></div>
            <div class="col-6 col-lg"><div class="metric"><small>Sistema</small><strong>Q {{ q(resumen.monto_sistema) }}</strong></div></div>
        </div>

        <div class="alert alert-warning" v-if="!cajaActiva">No hay caja abierta para este usuario.</div>
        <div class="alert alert-success" v-if="mensajeExitoCaja">{{ mensajeExitoCaja }}</div>
        <div class="alert alert-danger" v-if="alertaCaja">{{ alertaCaja.mensaje }}</div>

        <div class="card border-0 shadow-sm mb-3" v-if="section === 'apertura'">
            <div class="card-header bg-transparent"><strong>Apertura de caja</strong></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Fondo inicial</label>
                        <input v-model.number="formApertura.monto_apertura" type="number" step="0.01" min="0" class="form-control" :disabled="loading || !!cajaActiva" />
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
                            <option value="ingreso">Ingreso</option>
                            <option value="egreso">Egreso</option>
                            <option value="gasto">Gasto desde caja</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3">
                        <label class="form-label">Monto</label>
                        <input v-model.number="formAjuste.monto" type="number" min="0.01" step="0.01" class="form-control" />
                    </div>
                    <div class="col-12 col-md-3" v-if="formAjuste.tipo === 'egreso'">
                        <label class="form-label">Destino</label>
                        <select v-model="formAjuste.destino" class="form-select">
                            <option value="banco">Banco</option>
                            <option value="caja_general">Caja general</option>
                            <option value="otro">Otro</option>
                        </select>
                    </div>
                    <div class="col-12 col-md-3" v-if="formAjuste.tipo === 'gasto'">
                        <label class="form-label">Tipo gasto</label>
                        <select v-model="formAjuste.tipo_gasto_id" class="form-select">
                            <option :value="null">Seleccione</option>
                            <option v-for="t in catalogs.tipos_gasto" :key="t.id" :value="t.id">{{ t.nombre }}</option>
                        </select>
                    </div>
                    <div class="col-12" :class="formAjuste.tipo === 'ingreso' ? 'col-md-4' : 'col-md-3'">
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
                            <td>{{ movementLabel(row.tipo) }}</td>
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
                        <input v-model.number="formArqueo.monto_contado" type="number" min="0" step="0.01" class="form-control" readonly disabled />
                    </div>
                    <div class="col-12 col-md-4 d-grid align-items-end">
                        <button class="btn btn-brand mt-4" :disabled="loading || !cajaActiva" @click="registrarArqueo">Registrar arqueo</button>
                    </div>
                </div>

                <div class="row g-2">
                    <div class="col-6 col-md-3" v-for="b in denominaciones" :key="`${b.tipo}-${b.denominacion}`">
                        <label class="form-label">{{ b.tipo === 'billete' ? 'Billete' : 'Moneda' }} Q{{ formatDenominacion(b.denominacion) }}</label>
                        <input v-model.number="b.cantidad" type="number" min="0" step="1" class="form-control" />
                    </div>
                </div>
                <small class="text-muted d-block mt-2">Total contado: Q {{ q(totalDenominaciones) }}</small>
            </div>
        </div>

        <div class="card border-0 shadow-sm" v-if="section === 'cierre'">
            <div class="card-header bg-transparent"><strong>Cierre de caja</strong></div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-4">
                        <label class="form-label">Monto contado final</label>
                        <input v-model.number="formCierre.monto_contado" type="number" min="0" step="0.01" class="form-control" :disabled="loading || !cajaActiva || !!ultimoArqueoRegistrado" />
                    </div>
                    <div class="col-12 col-md-4">
                        <label class="form-label">Diferencia estimada</label>
                        <input :value="q((Number(formCierre.monto_contado || 0) - Number(resumen?.monto_sistema || 0)))" class="form-control" disabled />
                    </div>
                    <div class="col-12 col-md-4 d-grid">
                        <button class="btn btn-danger" :disabled="loading || !cajaActiva" @click="cerrarCaja">Cerrar caja</button>
                    </div>
                    <div class="col-12 col-md-4">
                        <small class="text-muted">{{ ultimoArqueoRegistrado ? 'Este valor se toma del ultimo arqueo registrado.' : 'Si no hay arqueo, este valor puede ingresarse manualmente.' }}</small>
                    </div>
                    <div class="col-12 col-md-4">
                        <small class="text-muted">Comparacion entre monto contado final y monto del sistema.</small>
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
import { formatMoney } from '@/utils/formatters';

const route = useRoute();
const router = useRouter();

const loading = ref(false);
const cajaActiva = ref(null);
const resumen = ref(null);
const movimientos = ref([]);
const alertaCaja = ref(null);
const mensajeExitoCaja = ref('');
const catalogs = ref({ tipos_gasto: [] });

const formApertura = ref({
    monto_apertura: 0,
});

const formAjuste = ref({
    tipo: 'ingreso',
    monto: null,
    descripcion: '',
    destino: 'banco',
    tipo_gasto_id: null,
});

const formArqueo = ref({
    monto_contado: null,
});

const formCierre = ref({
    monto_contado: null,
});

const denominaciones = ref([
    { tipo: 'billete', denominacion: 200, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 100, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 50, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 20, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 10, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 5, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'moneda', denominacion: 1, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'moneda', denominacion: 0.50, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'moneda', denominacion: 0.25, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'moneda', denominacion: 0.10, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'moneda', denominacion: 0.05, unidades_por_paquete: 1, cantidad: 0 },
]);

const section = computed(() => {
    const path = route.path || '/caja';
    if (path.includes('/movimientos')) return 'movimientos';
    if (path.includes('/arqueo')) return 'arqueo';
    if (path.includes('/cierre')) return 'cierre';
    return 'apertura';
});

const ultimoArqueoRegistrado = computed(() => cajaActiva.value?.arqueos?.[0] ?? null);

const totalDenominaciones = computed(() => denominaciones.value.reduce((acc, row) => acc + Number(row.denominacion) * Number(row.unidades_por_paquete || 1) * Number(row.cantidad || 0), 0));

watch(totalDenominaciones, (v) => {
    if (section.value === 'arqueo') {
        formArqueo.value.monto_contado = Number(v.toFixed(2));
    }
});

onMounted(async () => {
    await loadCatalogs();
    await loadEstado();
    await loadMovimientos();
});

function q(value) {
    return formatMoney(value);
}

function movementLabel(type) {
    return ({
        compra: 'Compra',
        anulacion_compra: 'Anulación de compra',
    })[type] ?? type;
}

function formatDenominacion(value) {
    return Number(value || 0).toFixed(2);
}

function dateTime(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString();
}

function goSection(name) {
    router.push(name === 'apertura' ? '/caja' : `/caja/${name}`);
}

function resolveApiErrorMessage(error, fallback) {
    const backendErrors = error?.response?.data?.errors;
    if (backendErrors && typeof backendErrors === 'object') {
        const first = Object.values(backendErrors).flat()[0];
        if (first) return String(first);
    }

    return error?.response?.data?.message || error?.message || fallback;
}

async function loadEstado() {
    loading.value = true;
    try {
        const { data } = await axios.get('/caja/get');
        cajaActiva.value = data?.data?.caja_activa ?? null;
        resumen.value = data?.data?.resumen ?? null;
        const ultimoArqueo = ultimoArqueoRegistrado.value;
        if (ultimoArqueo?.monto_contado != null) {
            formCierre.value.monto_contado = Number(ultimoArqueo.monto_contado);
        } else if (resumen.value?.monto_sistema != null) {
            formCierre.value.monto_contado = Number(resumen.value.monto_sistema);
        }
    } catch (error) {
        alertaCaja.value = { mensaje: resolveApiErrorMessage(error, 'No se pudo cargar el estado de caja.') };
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
    } catch (error) {
        movimientos.value = [];
        alertaCaja.value = { mensaje: resolveApiErrorMessage(error, 'No se pudieron cargar los movimientos de caja.') };
    }
}

async function loadCatalogs() {
    try {
        const { data } = await axios.get('/caja/get/catalogs');
        catalogs.value = data?.data ?? catalogs.value;
    } catch (error) {
        catalogs.value = { tipos_gasto: [] };
        alertaCaja.value = { mensaje: resolveApiErrorMessage(error, 'No se pudieron cargar catalogos de caja.') };
    }
}

async function abrirCaja() {
    loading.value = true;
    alertaCaja.value = null;
    mensajeExitoCaja.value = '';
    try {
        const { data } = await axios.post('/caja/apertura', {
            monto_apertura: Number(formApertura.value.monto_apertura || 0),
        });
        alertaCaja.value = data?.data?.alerta ?? null;
        await loadEstado();
        await loadMovimientos();
    } catch (error) {
        alertaCaja.value = { mensaje: resolveApiErrorMessage(error, 'No se pudo abrir la caja.') };
    } finally {
        loading.value = false;
    }
}

async function registrarAjuste() {
    loading.value = true;
    alertaCaja.value = null;
    mensajeExitoCaja.value = '';
    try {
        await axios.post('/caja/movimientos/ajuste', {
            ...formAjuste.value,
            monto: Number(formAjuste.value.monto || 0),
        });
        formAjuste.value.monto = null;
        formAjuste.value.descripcion = '';
        formAjuste.value.tipo_gasto_id = null;
        await loadEstado();
        await loadMovimientos();
    } catch (error) {
        alertaCaja.value = { mensaje: resolveApiErrorMessage(error, 'No se pudo registrar el movimiento.') };
    } finally {
        loading.value = false;
    }
}

async function registrarArqueo() {
    loading.value = true;
    alertaCaja.value = null;
    mensajeExitoCaja.value = '';
    try {
        const { data } = await axios.post('/caja/arqueo', {
            monto_contado: Number(formArqueo.value.monto_contado || 0),
            billetes: denominaciones.value,
        });
        mensajeExitoCaja.value = data?.message || 'Arqueo registrado correctamente.';
        if (data?.data?.arqueo?.monto_contado != null) {
            formCierre.value.monto_contado = Number(data.data.arqueo.monto_contado);
        }
        await loadEstado();
    } catch (error) {
        alertaCaja.value = { mensaje: resolveApiErrorMessage(error, 'No se pudo registrar el arqueo.') };
    } finally {
        loading.value = false;
    }
}

function resetFormsAfterCierre() {
    formApertura.value.monto_apertura = 0;

    formAjuste.value.tipo = 'ingreso';
    formAjuste.value.monto = null;
    formAjuste.value.descripcion = '';
    formAjuste.value.destino = 'banco';
    formAjuste.value.tipo_gasto_id = null;

    formArqueo.value.monto_contado = null;
    formCierre.value.monto_contado = null;

    billetes.value = billetes.value.map((row) => ({
        ...row,
        cantidad: 0,
    }));

    movimientos.value = [];
}

async function cerrarCaja() {
    loading.value = true;
    alertaCaja.value = null;
    mensajeExitoCaja.value = '';
    try {
        const { data } = await axios.post('/caja/cierre', {
            monto_contado: Number(formCierre.value.monto_contado || 0),
        });
        alertaCaja.value = null;
        mensajeExitoCaja.value = data?.message || 'Caja cerrada correctamente.';
        resetFormsAfterCierre();
        await loadEstado();
        await loadMovimientos();
        goSection('apertura');
    } catch (error) {
        alertaCaja.value = { mensaje: resolveApiErrorMessage(error, 'No se pudo cerrar la caja.') };
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
