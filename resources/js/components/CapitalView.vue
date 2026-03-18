<template>
    <div class="container-fluid py-3 capital-view">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Capital</h3>
                <small class="text-muted">Caja general, banco y transferencias entre fondos.</small>
            </div>
            <button class="btn btn-outline-brand btn-sm" :disabled="loading" @click="loadData">Recargar</button>
        </div>

        <div class="row g-3 mb-3">
            <div v-for="cuenta in cuentas" :key="cuenta.id" class="col-12 col-md-6 col-xl-4">
                <div class="capital-card h-100">
                    <small>{{ cuenta.nombre }}</small>
                    <strong>Q {{ q(cuenta.saldo_actual) }}</strong>
                    <div class="text-muted small">{{ cuenta.descripcion || cuenta.tipo }}</div>
                </div>
            </div>
            <div class="col-12 col-md-6 col-xl-4">
                <div class="capital-card h-100 capital-card-total">
                    <small>Total capital</small>
                    <strong>Q {{ q(resumen.saldo_total) }}</strong>
                    <div class="text-muted small">Suma de saldos disponibles en fondos centrales.</div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-header bg-transparent">
                <strong>Registrar movimiento</strong>
            </div>
            <div class="card-body">
                <div class="row g-3 align-items-end">
                    <div class="col-12 col-md-3">
                        <label class="form-label">Tipo</label>
                        <select v-model="form.tipo" class="form-select">
                            <option v-for="tipo in catalogs.tipos_movimiento" :key="tipo.value" :value="tipo.value">{{ tipo.label }}</option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3" v-if="form.tipo !== 'ingreso'">
                        <label class="form-label">Cuenta origen</label>
                        <select v-model="form.cuenta_origen_id" class="form-select">
                            <option :value="null">Seleccione</option>
                            <option v-for="cuenta in originAccounts" :key="`origen-${cuenta.id}`" :value="cuenta.id">
                                {{ cuenta.nombre }} | Q {{ q(cuenta.saldo_actual) }}
                            </option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3" v-if="form.tipo !== 'retiro'">
                        <label class="form-label">Cuenta destino</label>
                        <select v-model="form.cuenta_destino_id" class="form-select">
                            <option :value="null">Seleccione</option>
                            <option v-for="cuenta in destinationAccounts" :key="`destino-${cuenta.id}`" :value="cuenta.id">
                                {{ cuenta.nombre }} | Q {{ q(cuenta.saldo_actual) }}
                            </option>
                        </select>
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Monto</label>
                        <input v-model.number="form.monto" type="number" min="0.01" step="0.01" class="form-control" />
                    </div>

                    <div class="col-12 col-md-3">
                        <label class="form-label">Fecha actual</label>
                        <div class="form-control bg-light-subtle">{{ currentDateLabel }}</div>
                    </div>

                    <div class="col-12 col-md-6">
                        <label class="form-label">Descripcion</label>
                        <input v-model="form.descripcion" type="text" class="form-control" maxlength="255" />
                    </div>

                    <div class="col-12 col-md-3 d-grid">
                        <button class="btn btn-brand" :disabled="loading" @click="registrarMovimiento">Guardar</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent">
                <strong>Movimientos recientes</strong>
            </div>
            <div class="table-responsive">
                <table class="table table-sm align-middle mb-0">
                    <thead>
                        <tr>
                            <th class="px-3">Fecha</th>
                            <th>Tipo</th>
                            <th>Origen</th>
                            <th>Destino</th>
                            <th>Descripcion</th>
                            <th>Usuario</th>
                            <th class="text-end pe-3">Monto</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!totalMovimientosCapital">
                            <td colspan="7" class="text-center text-muted py-3">Sin movimientos registrados.</td>
                        </tr>
                        <tr v-for="item in paginatedMovimientosCapital" :key="item.id">
                            <td class="px-3">{{ dateTime(item.fecha) }}</td>
                            <td>{{ labelTipo(item.tipo) }}</td>
                            <td>{{ item.cuenta_origen?.nombre || '-' }}</td>
                            <td>{{ item.cuenta_destino?.nombre || '-' }}</td>
                            <td>{{ item.descripcion || '-' }}</td>
                            <td>{{ item.usuario?.name || '-' }}</td>
                            <td class="text-end pe-3">Q {{ q(item.monto) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination
                v-model:page="pageCapital"
                v-model:perPage="perPageCapital"
                :total-items="totalMovimientosCapital"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue';
import axios from '@/bootstrap';
import TablePagination from '@/components/components_ui/TablePagination.vue';
import { formatMoney } from '@/utils/formatters';

const loading = ref(false);
const cuentas = ref([]);
const movimientos = ref([]);
const resumen = ref({ saldo_total: 0 });
const catalogs = ref({ cuentas: [], tipos_movimiento: [] });
const pageCapital = ref(1);
const perPageCapital = ref(10);

const emptyForm = () => ({
    tipo: 'transferencia',
    cuenta_origen_id: null,
    cuenta_destino_id: null,
    monto: null,
    descripcion: '',
});

const form = ref(emptyForm());

const currentDateLabel = computed(() => new Date().toLocaleString('es-GT'));

const originAccounts = computed(() => {
    const selectedDestino = form.value.cuenta_destino_id;

    return catalogs.value.cuentas.filter((cuenta) => cuenta.id !== selectedDestino);
});

const destinationAccounts = computed(() => {
    const selectedOrigen = form.value.cuenta_origen_id;

    return catalogs.value.cuentas.filter((cuenta) => cuenta.id !== selectedOrigen);
});
const totalMovimientosCapital = computed(() => movimientos.value.length);
const totalPagesCapital = computed(() => Math.max(1, Math.ceil(totalMovimientosCapital.value / perPageCapital.value)));
const safePageCapital = computed(() => Math.min(Math.max(pageCapital.value, 1), totalPagesCapital.value));
const paginatedMovimientosCapital = computed(() => {
    const start = (safePageCapital.value - 1) * perPageCapital.value;
    return movimientos.value.slice(start, start + perPageCapital.value);
});

watch(() => form.value.cuenta_origen_id, (newValue) => {
    if (newValue && newValue === form.value.cuenta_destino_id) {
        form.value.cuenta_destino_id = null;
    }
});

watch(() => form.value.cuenta_destino_id, (newValue) => {
    if (newValue && newValue === form.value.cuenta_origen_id) {
        form.value.cuenta_origen_id = null;
    }
});

onMounted(async () => {
    await Promise.all([loadCatalogs(), loadData()]);
});

function q(value) {
    return formatMoney(value);
}

function dateTime(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString();
}

function labelTipo(value) {
    const labels = {
        ingreso_capital: 'Ingreso de capital',
        retiro_capital: 'Retiro de capital',
        transferencia_capital: 'Transferencia',
        apertura_caja: 'Apertura de caja',
        cierre_caja: 'Cierre de caja',
        gasto: 'Gasto',
        egreso_caja: 'Egreso desde caja POS',
    };

    return labels[value] || value;
}

async function loadCatalogs() {
    const { data } = await axios.get('/capital/get/catalogs');
    catalogs.value = data?.data ?? catalogs.value;
}

async function loadData() {
    loading.value = true;
    try {
        const { data } = await axios.get('/capital/get');
        cuentas.value = data?.data?.cuentas ?? [];
        movimientos.value = data?.data?.movimientos ?? [];
        resumen.value = data?.data?.resumen ?? resumen.value;
    } finally {
        loading.value = false;
    }
}

async function registrarMovimiento() {
    loading.value = true;
    try {
        await axios.post('/capital/store', {
            ...form.value,
            monto: Number(form.value.monto || 0),
        });
        form.value = emptyForm();
        await Promise.all([loadCatalogs(), loadData()]);
    } finally {
        loading.value = false;
    }
}
</script>

<style scoped>
.capital-card {
    background: #fff;
    border: 1px solid #e8edf3;
    border-radius: 0.85rem;
    padding: 1rem;
}

.capital-card small {
    display: block;
    color: #5f6b7a;
    margin-bottom: 0.4rem;
}

.capital-card strong {
    display: block;
    font-size: 1.4rem;
    margin-bottom: 0.2rem;
}

.capital-card-total {
    background: linear-gradient(135deg, #f8fbff 0%, #eef7f1 100%);
}
</style>