<template>
    <div class="container-fluid py-3">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h2 class="h4 mb-1">Cuentas por cobrar</h2>
                <p class="text-body-secondary mb-0">Ventas a crédito pendientes de cobro.</p>
            </div>
            <button class="btn btn-outline-brand btn-sm" :disabled="loading" @click="loadData()">Recargar</button>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-2">
                <strong>Clientes deudores</strong>
                <select v-model="estado" class="form-select form-select-sm" style="width: 180px;" @change="loadData(1)">
                    <option value="activa">Activas</option>
                    <option value="pagada">Pagadas</option>
                    <option value="vencida">Vencidas</option>
                    <option value="anulada">Anuladas</option>
                    <option value="todas">Todas</option>
                </select>
            </div>
            <div v-if="loading" class="text-center text-body-secondary py-5">Cargando cuentas...</div>
            <div v-else class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand"><tr><th>Cliente</th><th>Venta</th><th>Vencimiento</th><th>Estado</th><th class="text-end">Total</th><th class="text-end">Saldo</th><th></th></tr></thead>
                    <tbody>
                        <tr v-if="!creditos.length"><td colspan="7" class="text-center text-body-secondary py-4">No hay cuentas para este filtro.</td></tr>
                        <tr v-for="credito in creditos" :key="credito.id">
                            <td><div class="fw-semibold">{{ credito.cliente?.nombre }}</div><small class="text-body-secondary">{{ credito.cliente?.telefono || 'Sin teléfono' }}</small></td>
                            <td><code>{{ credito.venta?.numero }}</code><small class="d-block text-body-secondary">{{ formatDate(credito.venta?.fecha_venta) }}</small></td>
                            <td :class="isOverdue(credito) ? 'text-danger fw-semibold' : ''">{{ credito.fecha_vencimiento ? formatDate(credito.fecha_vencimiento) : 'Sin fecha' }}</td>
                            <td><span class="badge" :class="statusClass(credito)">{{ statusLabel(credito) }}</span></td>
                            <td class="text-end">Q {{ formatMoney(credito.monto_original) }}</td>
                            <td class="text-end fw-semibold">Q {{ formatMoney(credito.saldo_pendiente) }}</td>
                            <td class="text-end"><div class="btn-group btn-group-sm"><button class="btn btn-outline-brand" @click="openDetail(credito)">Detalle</button><button v-if="credito.estado === 'activa'" class="btn btn-brand" @click="openPayment(credito)">Abonar</button></div></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <TablePagination :page="meta.currentPage" :per-page="perPage" :total-items="meta.total" :page-size-options="[5, 10, 25, 50]" @update:page="loadData" @update:per-page="changePerPage" />
        </div>

        <div ref="detailModalRef" class="modal fade" tabindex="-1" aria-hidden="true"><div class="modal-dialog modal-lg modal-dialog-scrollable"><div class="modal-content"><div class="modal-header modal-header-brand"><h5 class="modal-title">Movimientos de la cuenta</h5><button type="button" class="btn-close" data-bs-dismiss="modal" /></div><div class="modal-body"><div v-if="detailLoading" class="text-center text-body-secondary py-4">Cargando movimientos...</div><template v-else><div class="row g-2 mb-3"><div class="col-12 col-sm-6"><strong>{{ selected?.cliente?.nombre }}</strong><small class="d-block text-body-secondary">Venta {{ selected?.venta?.numero }}</small></div><div class="col-12 col-sm-6 text-sm-end"><span class="text-body-secondary">Saldo actual</span><strong class="d-block">Q {{ formatMoney(selected?.saldo_pendiente) }}</strong></div></div><div class="table-responsive"><table class="table table-sm align-middle mb-0"><thead><tr><th>Fecha</th><th>Movimiento</th><th>Descripción</th><th>Usuario</th><th class="text-end">Monto</th><th class="text-end">Saldo</th></tr></thead><tbody><tr v-if="!movimientos.length"><td colspan="6" class="text-center text-body-secondary py-3">Sin movimientos.</td></tr><tr v-for="movimiento in movimientos" :key="movimiento.id"><td>{{ formatDateTime(movimiento.fecha) }}</td><td>{{ movementLabel(movimiento.tipo) }}</td><td>{{ movimiento.descripcion || '—' }}</td><td>{{ movimiento.usuario?.name || '—' }}</td><td class="text-end">Q {{ formatMoney(movimiento.monto) }}</td><td class="text-end">Q {{ formatMoney(movimiento.saldo_posterior) }}</td></tr></tbody></table></div></template></div><div class="modal-footer"><button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cerrar</button></div></div></div></div>

        <div ref="paymentModalRef" class="modal fade" tabindex="-1" aria-hidden="true"><div class="modal-dialog"><div class="modal-content"><div class="modal-header modal-header-brand"><h5 class="modal-title">Registrar abono</h5><button type="button" class="btn-close" data-bs-dismiss="modal" /></div><form @submit.prevent="savePayment"><div class="modal-body d-grid gap-3"><FormErrors :errors="formErrors" /><div class="alert alert-light border mb-0"><div>{{ selected?.cliente?.nombre }}</div><strong>Saldo pendiente: Q {{ formatMoney(selected?.saldo_pendiente) }}</strong></div><div><label class="form-label fw-semibold">Monto recibido en efectivo *</label><input v-model.number="payment.monto" type="number" class="form-control" min="0.01" step="0.01" required></div><div><label class="form-label fw-semibold">Nota</label><input v-model="payment.descripcion" class="form-control" maxlength="255" placeholder="Ej. Abono de cliente"></div><small class="text-body-secondary">El abono se registrará como ingreso en la caja abierta del usuario.</small></div><div class="modal-footer"><button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" :disabled="saving">{{ saving ? 'Guardando...' : 'Registrar abono' }}</button></div></form></div></div></div>
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { onMounted, ref } from 'vue';

import axios from '@/bootstrap';
import FormErrors from '@/components/FormErrors.vue';
import TablePagination from '@/components/components_ui/TablePagination.vue';
import { formatMoney } from '@/utils/formatters';

const creditos = ref([]);
const estado = ref('activa');
const perPage = ref(10);
const meta = ref({ currentPage: 1, total: 0 });
const loading = ref(false);
const saving = ref(false);
const selected = ref(null);
const movimientos = ref([]);
const detailLoading = ref(false);
const formErrors = ref([]);
const payment = ref({ monto: null, descripcion: '' });
const detailModalRef = ref(null);
const paymentModalRef = ref(null);
let detailModal;
let paymentModal;

onMounted(() => { detailModal = new Modal(detailModalRef.value); paymentModal = new Modal(paymentModalRef.value); loadData(); });

async function loadData(page = meta.value.currentPage) {
    loading.value = true;
    try {
        const { data } = await axios.get('/ventas/creditos/get', { params: { estado: estado.value, per_page: perPage.value, page } });
        creditos.value = data.data ?? [];
        meta.value = { currentPage: data.meta?.current_page ?? 1, total: data.meta?.total ?? 0 };
    } finally { loading.value = false; }
}
function changePerPage(value) { perPage.value = Number(value); loadData(1); }
async function openDetail(credito) { selected.value = credito; movimientos.value = []; detailLoading.value = true; detailModal.show(); try { const { data } = await axios.get(`/ventas/creditos/${credito.id}/movimientos`); movimientos.value = data.data ?? []; } finally { detailLoading.value = false; } }
function openPayment(credito) { selected.value = credito; payment.value = { monto: null, descripcion: '' }; formErrors.value = []; paymentModal.show(); }
async function savePayment() { saving.value = true; formErrors.value = []; try { await axios.post(`/ventas/creditos/${selected.value.id}/abonos`, payment.value); paymentModal.hide(); await loadData(); } catch (error) { const errors = error.response?.data?.errors; formErrors.value = errors ? Object.values(errors).flat() : [error.response?.data?.message ?? 'No se pudo registrar el abono.']; } finally { saving.value = false; } }
function isOverdue(credito) { return credito.estado === 'activa' && credito.fecha_vencimiento && credito.fecha_vencimiento < new Date().toISOString().slice(0, 10); }
function statusLabel(credito) { return isOverdue(credito) ? 'Vencida' : ({ activa: 'Activa', pagada: 'Pagada', anulada: 'Anulada', incobrable: 'Incobrable' }[credito.estado] ?? credito.estado); }
function statusClass(credito) { return isOverdue(credito) ? 'text-bg-danger' : ({ activa: 'text-bg-warning', pagada: 'text-bg-success', anulada: 'text-bg-secondary', incobrable: 'text-bg-dark' }[credito.estado] ?? 'text-bg-secondary'); }
function movementLabel(tipo) { return ({ cargo_inicial: 'Venta a crédito', abono: 'Abono', anulacion: 'Anulación' }[tipo] ?? tipo); }
function formatDate(value) { return value ? new Date(`${value}T00:00:00`).toLocaleDateString('es-GT') : '—'; }
function formatDateTime(value) { return value ? new Date(value).toLocaleString('es-GT') : '—'; }
</script>
