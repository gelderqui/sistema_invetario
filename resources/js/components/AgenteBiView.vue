<template>
    <div class="container-fluid py-3 agente-bi-view">
        <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-3">
            <div>
                <h3 class="mb-1">Agente BI</h3>
                <small class="text-muted">Cuadre de capital, fondos físicos y deudas de BI.</small>
            </div>
            <button class="btn btn-outline-brand btn-sm" :disabled="loading" @click="loadData">Recargar</button>
        </div>

        <FormErrors :errors="errors" />

        <div class="d-flex flex-wrap align-items-center gap-2 mb-2 small text-muted">
            <span>Capital + BI debe = Banco + Caja + Caja chica + Deuda a BI</span>
            <span class="fw-semibold">· Diferencia = ubicado − financiado</span>
        </div>
        <div v-for="(row, rowIndex) in summaryCardRows" :key="rowIndex" class="row g-3" :class="rowIndex === summaryCardRows.length - 1 ? 'mb-4' : 'mb-3'">
            <div v-for="card in row" :key="card.key" :class="card.columnClass">
                <button type="button" class="bi-card bi-card-clickable bi-summary-card" :class="summaryCardClass(card)" @click="openSummaryCard(card)">
                    <small>{{ card.label }}</small>
                    <strong>Q {{ q(resumen[card.valueKey]) }}</strong>
                </button>
            </div>
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-header bg-transparent d-flex flex-wrap justify-content-between align-items-center gap-1">
                <strong>Guardar cuadre</strong>
                <small class="text-muted">Los totales de deuda se calculan automáticamente.</small>
            </div>
            <form class="card-body row g-3 align-items-end" @submit.prevent="openConfirmCuadre">
                <div class="col-12 col-md-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label mb-1">Capital</label>
                        <button type="button" class="btn btn-link btn-sm p-0" @click="capitalEditable = !capitalEditable">{{ capitalEditable ? 'Bloquear' : 'Modificar' }}</button>
                    </div>
                    <input v-model.number="cuadreForm.capital" class="form-control" :class="{ 'bg-light': !capitalEditable }" type="number" min="0" step="0.01" required :readonly="!capitalEditable" @blur="formatCuadreField('capital')">
                </div>
                <div class="col-12 col-md-3">
                    <div class="d-flex justify-content-between align-items-center">
                        <label class="form-label mb-1">Caja</label>
                        <button type="button" class="btn btn-link btn-sm p-0" @click="cajaEditable = !cajaEditable">{{ cajaEditable ? 'Bloquear' : 'Modificar' }}</button>
                    </div>
                    <input v-model.number="cuadreForm.caja" class="form-control" :class="{ 'bg-light': !cajaEditable }" type="number" min="0" step="0.01" required :readonly="!cajaEditable" @blur="formatCuadreField('caja')">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Banco</label>
                    <input v-model.number="cuadreForm.banco" class="form-control" type="number" min="0" step="0.01" required @blur="formatCuadreField('banco')">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label">Último arqueo de caja chica</label>
                    <div class="form-control bg-light">Q {{ q(resumen.ultimo_arqueo_monto) }}</div>
                </div>
                <div class="col-12 col-md-3 ms-md-auto d-grid">
                    <button class="btn btn-brand" :disabled="savingCuadre">{{ savingCuadre ? 'Guardando...' : 'Guardar cuadre' }}</button>
                </div>
            </form>
        </div>

        <div class="bi-action-buttons d-flex flex-wrap justify-content-end gap-2 mb-4">
            <button class="btn btn-outline-brand" @click="openDeudaModal">Gestionar deuda</button>
            <button class="btn btn-outline-brand" @click="openArqueoModal">Nuevo arqueo</button>
            <button class="btn btn-outline-brand" @click="openCuadresModal">Ver cuadres</button>
        </div>

        <div ref="historyModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">{{ historyTitle }}</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">
                        <div v-if="historyMode === 'deudas' && !selectedDebtHistory" class="d-flex justify-content-end mb-3">
                            <select v-model="deudaHistoryFilter" class="form-select form-select-sm" style="max-width: 180px;">
                                <option value="activa">Activas</option>
                                <option value="cerradas">Canceladas</option>
                                <option value="todas">Todas</option>
                            </select>
                        </div>
                        <div v-if="historyLoading" class="text-center text-muted py-4">Cargando historial...</div>
                        <div v-else class="table-responsive">
                            <table v-if="historyMode === 'campo'" class="table table-sm align-middle mb-0">
                                <thead><tr><th>Fecha</th><th>Usuario</th><th class="text-end">Valor</th></tr></thead>
                                <tbody>
                                    <tr v-if="!fieldHistory.length"><td colspan="3" class="text-center text-muted py-3">Sin cambios registrados.</td></tr>
                                    <tr v-for="item in fieldHistory" :key="item.id"><td>{{ dateTime(item.fecha) }}</td><td>{{ item.usuario || '—' }}</td><td class="text-end">Q {{ q(item.valor) }}</td></tr>
                                </tbody>
                            </table>
                            <table v-else-if="selectedDebtHistory" class="table table-sm align-middle mb-0">
                                <thead><tr><th>Fecha</th><th>Movimiento</th><th>Descripción</th><th>Usuario</th><th class="text-end">Monto</th><th class="text-end">Saldo anterior</th><th class="text-end">Saldo posterior</th></tr></thead>
                                <tbody>
                                    <tr v-if="!debtMovements.length"><td colspan="7" class="text-center text-muted py-3">Sin movimientos registrados.</td></tr>
                                    <tr v-for="item in debtMovements" :key="item.id"><td>{{ dateTime(item.fecha) }}</td><td>{{ debtMovementLabel(item.tipo) }}</td><td>{{ item.descripcion || '—' }}</td><td>{{ item.usuario?.name || '—' }}</td><td class="text-end">Q {{ q(item.monto) }}</td><td class="text-end">Q {{ q(item.saldo_anterior) }}</td><td class="text-end">Q {{ q(item.saldo_posterior) }}</td></tr>
                                </tbody>
                            </table>
                            <table v-else class="table table-sm align-middle mb-0">
                                <thead><tr><th>Referencia</th><th>Fecha de creación</th><th>Última actualización</th><th class="text-end">Saldo</th><th class="text-end">Estado</th><th></th></tr></thead>
                                <tbody>
                                    <tr v-if="!filteredDebtHistory.length"><td colspan="6" class="text-center text-muted py-3">Sin registros para este filtro.</td></tr>
                                    <tr v-for="item in filteredDebtHistory" :key="item.id"><td><div>{{ item.nombre_referencia }}</div><small class="text-muted">{{ item.descripcion || '—' }}</small></td><td>{{ dateTime(item.fecha_origen) }}</td><td>{{ debtLastUpdate(item) }}</td><td class="text-end">Q {{ q(item.saldo_pendiente) }}</td><td class="text-end"><span class="badge" :class="item.estado === 'activa' ? 'text-bg-success' : 'text-bg-secondary'">{{ debtStateLabel(item.estado) }}</span></td><td class="text-end"><button class="btn btn-sm btn-outline-brand" @click="openDebtMovements(item)">Ver movimientos</button></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer"><button v-if="selectedDebtHistory" type="button" class="btn btn-outline-brand" @click="backToDebtList">← Volver a deudas</button><button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cerrar</button></div>
                </div>
            </div>
        </div>

        <div ref="cuadresModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand"><h5 class="modal-title">{{ selectedCuadre ? 'Detalle de cuadre' : 'Historial de cuadres' }}</h5><button type="button" class="btn-close" data-bs-dismiss="modal" /></div>
                    <div class="modal-body">
                        <div v-if="!selectedCuadre" class="row g-2 align-items-end mb-3">
                            <div class="col-12 col-sm-5"><label class="form-label mb-1">Fecha inicial</label><input v-model="cuadresFilters.fechaInicio" class="form-control" type="date" required></div>
                            <div class="col-12 col-sm-5"><label class="form-label mb-1">Fecha final</label><input v-model="cuadresFilters.fechaFin" class="form-control" type="date" required></div>
                            <div class="col-12 col-sm-2 d-grid"><button type="button" class="btn btn-outline-brand" :disabled="cuadresLoading" @click="loadCuadres(1)">Filtrar</button></div>
                        </div>
                        <div v-if="cuadresLoading" class="text-center text-muted py-4">Cargando cuadres...</div>
                        <template v-else-if="selectedCuadre">
                            <div class="row g-2 mb-4">
                                <div v-for="item in detalleCuadreValores" :key="item.label" class="col-6 col-md-4 col-lg-3"><div class="border rounded p-2 h-100"><small class="d-block text-muted">{{ item.label }}</small><strong>Q {{ q(item.valor) }}</strong></div></div>
                            </div>
                            <div class="d-flex flex-wrap justify-content-between align-items-center gap-2 mb-2"><h6 class="mb-0">Arqueo de caja chica utilizado</h6><small v-if="selectedCuadre.arqueo_caja_chica" class="text-muted">{{ dateTime(selectedCuadre.arqueo_caja_chica.fecha) }} · Q {{ q(selectedCuadre.arqueo_caja_chica.monto_contado) }}</small></div>
                            <div v-if="selectedCuadre.arqueo_caja_chica" class="table-responsive border rounded">
                                <table class="table table-sm align-middle mb-0"><thead><tr><th class="ps-3">Tipo</th><th>Denominación</th><th>Unidades por paquete</th><th>Cantidad</th><th class="text-end pe-3">Subtotal</th></tr></thead><tbody><tr v-for="detalle in selectedCuadre.arqueo_caja_chica.detalles" :key="detalle.id"><td class="ps-3">{{ detalle.tipo }}</td><td>Q {{ q(detalle.denominacion) }}</td><td>{{ detalle.unidades_por_paquete }}</td><td>{{ detalle.cantidad }}</td><td class="text-end pe-3">Q {{ q(detalle.subtotal) }}</td></tr></tbody></table>
                            </div>
                            <div v-else class="border rounded p-3 text-muted">Este cuadre no tiene un arqueo de caja chica asociado.</div>
                        </template>
                        <div v-else class="table-responsive">
                            <table class="table table-sm align-middle mb-0 bi-cuadres-table">
                                <thead><tr><th>Fecha</th><th class="text-end">Total financiado</th><th class="text-end">Total ubicado</th><th class="text-end">Diferencia</th><th>Usuario</th><th></th></tr></thead>
                                <tbody>
                                    <tr v-if="!cuadresHistory.length"><td colspan="6" class="text-center text-muted py-3">No hay cuadres registrados.</td></tr>
                                    <tr v-for="cuadre in cuadresHistory" :key="cuadre.id"><td>{{ dateTime(cuadre.fecha) }}</td><td class="text-end">Q {{ q(cuadre.total_financiado) }}</td><td class="text-end">Q {{ q(cuadre.total_ubicado) }}</td><td class="text-end" :class="Number(cuadre.diferencia) >= 0 ? 'text-success' : 'text-danger'">Q {{ q(cuadre.diferencia) }}</td><td>{{ cuadre.usuario?.name || '—' }}</td><td><button type="button" class="btn btn-sm btn-outline-brand text-nowrap" @click="openCuadreDetail(cuadre)">Ver detalle</button></td></tr>
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="modal-footer d-flex justify-content-between">
                        <template v-if="selectedCuadre"><button type="button" class="btn btn-outline-brand" @click="backToCuadres">← Volver a cuadres</button><small class="text-muted">Guardado por {{ selectedCuadre.usuario?.name || '—' }}</small></template>
                        <template v-else><small class="text-muted">{{ cuadresMeta.total }} cuadre{{ cuadresMeta.total === 1 ? '' : 's' }}</small><div class="d-flex align-items-center gap-2"><button type="button" class="btn btn-sm btn-outline-brand" :disabled="cuadresLoading || cuadresMeta.currentPage <= 1" @click="loadCuadres(cuadresMeta.currentPage - 1)">Anterior</button><span class="small">Página {{ cuadresMeta.currentPage }} de {{ cuadresMeta.lastPage }}</span><button type="button" class="btn btn-sm btn-outline-brand" :disabled="cuadresLoading || cuadresMeta.currentPage >= cuadresMeta.lastPage" @click="loadCuadres(cuadresMeta.currentPage + 1)">Siguiente</button></div></template>
                    </div>
                </div>
            </div>
        </div>

        <div ref="confirmCuadreModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand"><h5 class="modal-title">Confirmar cuadre</h5><button type="button" class="btn-close" data-bs-dismiss="modal" /></div>
                    <div class="modal-body">
                        <p class="mb-3">¿Seguro que deseas guardar este cuadre? Este es el cálculo que verá la tarjeta de diferencia:</p>
                        <div class="border rounded p-3 bg-light">
                            <div class="d-flex justify-content-between gap-3 mb-2"><span>Total financiado</span><strong>Q {{ q(previewCuadre.totalFinanciado) }}</strong></div>
                            <div class="d-flex justify-content-between gap-3 mb-2"><span>Total ubicado</span><strong>Q {{ q(previewCuadre.totalUbicado) }}</strong></div>
                            <hr class="my-2">
                            <div class="d-flex justify-content-between gap-3"><strong>Diferencia actual</strong><strong :class="previewCuadre.diferencia >= 0 ? 'text-success' : 'text-danger'">Q {{ q(previewCuadre.diferencia) }}</strong></div>
                        </div>
                    </div>
                    <div class="modal-footer"><button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button><button type="button" class="btn btn-brand" :disabled="savingCuadre" @click="saveCuadre">{{ savingCuadre ? 'Guardando...' : 'Sí, guardar cuadre' }}</button></div>
                </div>
            </div>
        </div>

        <div ref="deudaModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand"><h5 class="modal-title">Gestionar deuda</h5><button type="button" class="btn-close" data-bs-dismiss="modal" /></div>
                    <form @submit.prevent="saveDeudaAction">
                        <div class="modal-body row g-3">
                            <div class="col-12"><label class="form-label">Acción</label><select v-model="deudaAction" class="form-select" @change="resetDebtActionForm"><option value="nuevo">Nuevo registro</option><option value="movimiento">Abono o ajuste</option></select></div>
                            <template v-if="deudaAction === 'nuevo'">
                                <div class="col-12 col-md-5"><label class="form-label">Tipo</label><select v-model="deudaForm.tipo" class="form-select"><option value="bi_debe">BI debe</option><option value="deuda_a_bi">Deuda a BI</option></select></div>
                                <div class="col-12 col-md-7"><label class="form-label">Persona o referencia</label><input v-model="deudaForm.nombre_referencia" class="form-control" maxlength="150" required placeholder="Ej. Rodrigo"></div>
                                <div class="col-12"><label class="form-label">Monto inicial</label><input v-model.number="deudaForm.monto" class="form-control" type="number" min="0.01" step="0.01" required></div>
                                <div class="col-12"><label class="form-label">Descripción</label><input v-model="deudaForm.descripcion" class="form-control" maxlength="255" placeholder="Motivo o detalle"></div>
                            </template>
                            <template v-else>
                                <div class="col-12 col-md-5"><label class="form-label">Tipo</label><select v-model="movimientoTipo" class="form-select" @change="movimientoForm.deuda_id = null"><option value="bi_debe">BI debe</option><option value="deuda_a_bi">Deuda a BI</option></select></div>
                                <div class="col-12 col-md-7"><label class="form-label">Deuda activa</label><select v-model="movimientoForm.deuda_id" class="form-select" required><option :value="null">Seleccione</option><option v-for="deuda in deudasActivasPorTipo" :key="deuda.id" :value="deuda.id">{{ deuda.nombre_referencia }} · Q {{ q(deuda.saldo_pendiente) }}</option></select></div>
                                <div class="col-12 col-md-5"><label class="form-label">Movimiento</label><select v-model="movimientoForm.tipo" class="form-select"><option value="abono">Abono</option><option value="ajuste_aumenta">Ajuste aumenta</option></select></div>
                                <div class="col-12 col-md-3"><label class="form-label">Monto</label><input v-model.number="movimientoForm.monto" class="form-control" type="number" min="0.01" step="0.01" required></div>
                                <div class="col-12 col-md-4"><label class="form-label">Descripción</label><input v-model="movimientoForm.descripcion" class="form-control" maxlength="255" required></div>
                            </template>
                        </div>
                        <div class="modal-footer"><button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button><button class="btn btn-brand" :disabled="savingDeuda || savingMovimiento">{{ savingDeuda || savingMovimiento ? 'Guardando...' : deudaAction === 'nuevo' ? 'Registrar deuda' : 'Guardar movimiento' }}</button></div>
                    </form>
                </div>
            </div>
        </div>

        <div ref="arqueoModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Arqueo de caja chica</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <form @submit.prevent="saveArqueo">
                        <div class="modal-body">
                            <div class="form-check mb-3">
                                <input id="cargar-ultimo-arqueo" v-model="cargarUltimoArqueo" class="form-check-input" type="checkbox" :disabled="!arqueos.length" @change="cargarDatosUltimoArqueo">
                                <label class="form-check-label" for="cargar-ultimo-arqueo">Cargar datos del último arqueo</label>
                                <small class="d-block text-muted">Úsalo para corregir solo las denominaciones que necesites.</small>
                            </div>
                            <div class="table-responsive border rounded">
                                <table class="table table-sm align-middle mb-0">
                                    <thead><tr><th class="ps-3">Tipo</th><th>Denominación</th><th>Unidades por paquete</th><th>Cantidad</th><th class="text-end pe-3">Subtotal</th></tr></thead>
                                    <tbody>
                                        <tr v-for="(detalle, index) in arqueoForm.detalles" :key="index">
                                            <td class="ps-3">{{ detalle.tipo }}</td>
                                            <td>Q {{ q(detalle.denominacion) }}</td>
                                            <td>{{ detalle.unidades_por_paquete }}</td>
                                            <td><input v-model.number="detalle.cantidad" class="form-control form-control-sm" type="number" min="0" step="0.01"></td>
                                            <td class="text-end pe-3">Q {{ q(subtotalDetalle(detalle)) }}</td>
                                        </tr>
                                    </tbody>
                                    <tfoot><tr><th colspan="4" class="text-end">Total contado</th><th class="text-end pe-3">Q {{ q(totalArqueo) }}</th></tr></tfoot>
                                </table>
                            </div>
                        </div>
                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                            <button class="btn btn-brand" :disabled="savingArqueo">{{ savingArqueo ? 'Guardando...' : 'Guardar arqueo' }}</button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import axios from '@/bootstrap';
import FormErrors from '@/components/FormErrors.vue';
import { formatMoney } from '@/utils/formatters';

const loading = ref(false);
const savingCuadre = ref(false);
const savingDeuda = ref(false);
const savingMovimiento = ref(false);
const savingArqueo = ref(false);
const errors = ref([]);
const deudas = ref([]);
const arqueos = ref([]);
const cuadres = ref([]);
const resumen = ref({ capital: 0, banco: 0, caja: 0, bi_debe_total: 0, deuda_a_bi_total: 0, caja_chica_total: 0, total_financiado: 0, total_ubicado: 0, diferencia: 0, ultimo_arqueo_id: null, ultimo_arqueo_monto: 0 });
const cuadreFormInicializado = ref(false);
const capitalEditable = ref(false);
const cajaEditable = ref(false);
const historyModalRef = ref(null);
const cuadresModalRef = ref(null);
const confirmCuadreModalRef = ref(null);
const deudaModalRef = ref(null);
const arqueoModalRef = ref(null);
const historyTitle = ref('Historial');
const historyMode = ref('campo');
const historyLoading = ref(false);
const fieldHistory = ref([]);
const debtHistoryType = ref(null);
const deudaHistoryFilter = ref('todas');
const selectedDebtHistory = ref(null);
const debtMovements = ref([]);
const debtHistoryBaseTitle = ref('');
const cargarUltimoArqueo = ref(false);
const deudaAction = ref('nuevo');
const movimientoTipo = ref('bi_debe');
const cuadresHistory = ref([]);
const cuadresLoading = ref(false);
const cuadresMeta = ref({ currentPage: 1, lastPage: 1, perPage: 15, total: 0 });
const selectedCuadre = ref(null);
const cuadresFilters = ref({ fechaInicio: localDateToday(), fechaFin: localDateToday() });
let historyModal = null;
let cuadresModal = null;
let confirmCuadreModal = null;
let deudaModal = null;
let arqueoModal = null;

const emptyDeuda = () => ({ tipo: 'bi_debe', nombre_referencia: '', descripcion: '', monto: null });
const emptyMovimiento = () => ({ deuda_id: null, tipo: 'abono', monto: null, descripcion: '' });
const defaultDetalles = () => [
    { tipo: 'billete', denominacion: 100, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 50, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 20, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 10, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'billete', denominacion: 5, unidades_por_paquete: 1, cantidad: 0 },
    { tipo: 'paquete', denominacion: 1, unidades_por_paquete: 100, cantidad: 0 },
    { tipo: 'moneda', denominacion: 1, unidades_por_paquete: 1, cantidad: 0 },
];
const deudaForm = ref(emptyDeuda());
const movimientoForm = ref(emptyMovimiento());
const arqueoForm = ref({ monto_sistema: null, detalles: defaultDetalles() });
const cuadreForm = ref({ capital: 0, banco: 0, caja: 0 });

const deudasActivas = computed(() => deudas.value.filter((item) => item.estado === 'activa'));
const deudasActivasPorTipo = computed(() => deudasActivas.value.filter((item) => item.tipo === movimientoTipo.value));
const filteredDebtHistory = computed(() => deudas.value.filter((item) => {
    if (item.tipo !== debtHistoryType.value) return false;
    if (deudaHistoryFilter.value === 'activa') return item.estado === 'activa';
    if (deudaHistoryFilter.value === 'cerradas') return item.estado !== 'activa';
    return true;
}));
const totalArqueo = computed(() => arqueoForm.value.detalles.reduce((total, detalle) => total + subtotalDetalle(detalle), 0));
const previewCuadre = computed(() => {
    const capital = Number(cuadreForm.value.capital || 0);
    const banco = Number(cuadreForm.value.banco || 0);
    const caja = Number(cuadreForm.value.caja || 0);
    const totalFinanciado = capital + Number(resumen.value.bi_debe_total || 0);
    const totalUbicado = banco + caja + Number(resumen.value.ultimo_arqueo_monto || 0) + Number(resumen.value.deuda_a_bi_total || 0);

    return {
        totalFinanciado,
        totalUbicado,
        diferencia: totalUbicado - totalFinanciado,
    };
});
const detalleCuadreValores = computed(() => {
    if (!selectedCuadre.value) return [];

    return [
        { label: 'Capital', valor: selectedCuadre.value.capital },
        { label: 'BI debe', valor: selectedCuadre.value.bi_debe_total },
        { label: 'Total financiado', valor: selectedCuadre.value.total_financiado },
        { label: 'Banco', valor: selectedCuadre.value.banco },
        { label: 'Caja', valor: selectedCuadre.value.caja },
        { label: 'Caja chica', valor: selectedCuadre.value.caja_chica },
        { label: 'Deuda a BI', valor: selectedCuadre.value.deuda_a_bi_total },
        { label: 'Total ubicado', valor: selectedCuadre.value.total_ubicado },
        { label: 'Diferencia', valor: selectedCuadre.value.diferencia },
    ];
});
const summaryCardRows = computed(() => [
    [
        { key: 'capital', label: 'Capital', valueKey: 'capital', tone: 'financed', columnClass: 'col-12 col-md-6 col-xl-3', historyField: 'capital', historyTitle: 'Historial de capital' },
        { key: 'bi_debe', label: 'BI debe', valueKey: 'bi_debe_total', tone: 'financed', columnClass: 'col-12 col-md-6 col-xl-3', debtType: 'bi_debe', historyTitle: 'BI debe' },
        { key: 'total_financiado', label: 'Total financiado', valueKey: 'total_financiado', tone: 'financed', columnClass: 'col-12 col-md-6 col-xl-3', historyField: 'total_financiado', historyTitle: 'Historial de total financiado' },
        { key: 'diferencia', label: 'Diferencia', valueKey: 'diferencia', tone: 'difference', columnClass: 'col-12 col-md-6 col-xl-3', historyField: 'diferencia', historyTitle: 'Historial de diferencia' },
    ],
    [
        { key: 'caja', label: 'Caja', valueKey: 'caja', tone: 'located', columnClass: 'col-6 col-md-4 col-xl-2', historyField: 'caja', historyTitle: 'Historial de caja' },
        { key: 'banco', label: 'Banco', valueKey: 'banco', tone: 'located', columnClass: 'col-6 col-md-4 col-xl-2', historyField: 'banco', historyTitle: 'Historial de banco' },
        { key: 'caja_chica', label: 'Caja chica', valueKey: 'caja_chica_total', tone: 'located', columnClass: 'col-6 col-md-4 col-xl-2', historyField: 'caja_chica', historyTitle: 'Arqueos de caja chica incluidos en cuadres' },
        { key: 'deuda_a_bi', label: 'Deuda a BI', valueKey: 'deuda_a_bi_total', tone: 'located', columnClass: 'col-6 col-md-4 col-xl-2', debtType: 'deuda_a_bi', historyTitle: 'Deuda a BI' },
        { key: 'total_ubicado', label: 'Total ubicado', valueKey: 'total_ubicado', tone: 'located', columnClass: 'col-6 col-md-4 col-xl-2', historyField: 'total_ubicado', historyTitle: 'Historial de total ubicado' },
    ],
]);

onMounted(() => {
    historyModal = new Modal(historyModalRef.value);
    cuadresModal = new Modal(cuadresModalRef.value);
    confirmCuadreModal = new Modal(confirmCuadreModalRef.value);
    deudaModal = new Modal(deudaModalRef.value);
    arqueoModal = new Modal(arqueoModalRef.value);
    loadData();
});

onBeforeUnmount(() => {
    historyModal?.dispose();
    cuadresModal?.dispose();
    confirmCuadreModal?.dispose();
    deudaModal?.dispose();
    arqueoModal?.dispose();
});

function q(value) { return formatMoney(value); }
function localDateToday() {
    const today = new Date();
    const month = String(today.getMonth() + 1).padStart(2, '0');
    const day = String(today.getDate()).padStart(2, '0');

    return `${today.getFullYear()}-${month}-${day}`;
}
function summaryCardClass(card) {
    if (card.tone === 'difference') return Number(resumen.value.diferencia) >= 0 ? 'bi-card-balanced' : 'bi-card-unbalanced';
    return card.tone === 'financed' ? 'bi-card-financed' : 'bi-card-located';
}
function openSummaryCard(card) {
    if (card.debtType) {
        openDebtHistory(card.debtType, card.historyTitle);
        return;
    }

    openFieldHistory(card.historyField, card.historyTitle);
}
function decimalInput(value) { return Number(value || 0).toFixed(2); }
function formatCuadreField(campo) { cuadreForm.value[campo] = decimalInput(cuadreForm.value[campo]); }
function dateTime(value) { return value ? new Date(value).toLocaleString('es-GT') : '—'; }
function dateOnly(value) {
    if (!value) return '—';
    const isoDate = String(value).slice(0, 10);
    return new Date(`${isoDate}T00:00:00`).toLocaleDateString('es-GT');
}
function debtLastUpdate(deuda) { return deuda.ultima_actualizacion ? dateTime(deuda.ultima_actualizacion) : dateTime(deuda.fecha_origen); }
function subtotalDetalle(detalle) { return Number(detalle.denominacion || 0) * Number(detalle.unidades_por_paquete || 1) * Number(detalle.cantidad || 0); }
function setErrors(error, fallback) { errors.value = error.response?.data?.errors ? Object.values(error.response.data.errors).flat() : [error.response?.data?.message ?? fallback]; }
function debtStateLabel(estado) { return ({ activa: 'Activa', pagada: 'Pagada', anulada: 'Anulada' })[estado] || estado; }
function debtMovementLabel(tipo) { return ({ cargo: 'Registro inicial', abono: 'Abono', ajuste_aumenta: 'Ajuste aumenta', ajuste_disminuye: 'Ajuste disminuye', anulacion: 'Anulación' })[tipo] || tipo; }
function openDebtHistory(tipo, title) {
    historyTitle.value = title;
    debtHistoryBaseTitle.value = title;
    historyMode.value = 'deudas';
    debtHistoryType.value = tipo;
    deudaHistoryFilter.value = 'todas';
    selectedDebtHistory.value = null;
    debtMovements.value = [];
    historyLoading.value = false;
    historyModal?.show();
}
async function openDebtMovements(deuda) {
    historyLoading.value = true;
    selectedDebtHistory.value = deuda;
    debtMovements.value = [];
    historyTitle.value = `Movimientos: ${deuda.nombre_referencia}`;
    try {
        const { data } = await axios.get(`/agente-bi/deudas/${deuda.id}/movimientos`);
        const payload = data?.data ?? {};
        selectedDebtHistory.value = payload.deuda ?? deuda;
        debtMovements.value = payload.movimientos ?? [];
    } catch (error) {
        selectedDebtHistory.value = null;
        historyTitle.value = debtHistoryBaseTitle.value;
        setErrors(error, 'No se pudieron cargar los movimientos de la deuda.');
    } finally {
        historyLoading.value = false;
    }
}
function backToDebtList() {
    selectedDebtHistory.value = null;
    debtMovements.value = [];
    historyTitle.value = debtHistoryBaseTitle.value;
}
function openCuadresModal() {
    selectedCuadre.value = null;
    cuadresFilters.value = { fechaInicio: localDateToday(), fechaFin: localDateToday() };
    cuadresModal?.show();
    loadCuadres(1);
}
async function loadCuadres(page = 1) {
    cuadresLoading.value = true;
    try {
        const { data } = await axios.get('/agente-bi/cuadres', { params: { page, per_page: 15, fecha_inicio: cuadresFilters.value.fechaInicio, fecha_fin: cuadresFilters.value.fechaFin } });
        cuadresHistory.value = data?.data ?? [];
        const meta = data?.meta ?? {};
        cuadresMeta.value = {
            currentPage: Number(meta.current_page ?? 1),
            lastPage: Number(meta.last_page ?? 1),
            perPage: Number(meta.per_page ?? 15),
            total: Number(meta.total ?? 0),
        };
    } catch (error) {
        setErrors(error, 'No se pudieron cargar los cuadres.');
    } finally {
        cuadresLoading.value = false;
    }
}
async function openCuadreDetail(cuadre) {
    cuadresLoading.value = true;
    try {
        const { data } = await axios.get(`/agente-bi/cuadres/${cuadre.id}`);
        selectedCuadre.value = data?.data ?? null;
    } catch (error) {
        setErrors(error, 'No se pudo cargar el detalle del cuadre.');
    } finally {
        cuadresLoading.value = false;
    }
}
function backToCuadres() {
    selectedCuadre.value = null;
}
async function openFieldHistory(campo, title) {
    historyTitle.value = title;
    historyMode.value = 'campo';
    fieldHistory.value = [];
    historyLoading.value = true;
    historyModal?.show();
    try {
        const { data } = await axios.get(`/agente-bi/historial/campo/${campo}`);
        fieldHistory.value = data?.data ?? [];
    } catch (error) {
        setErrors(error, 'No se pudo cargar el historial.');
    } finally {
        historyLoading.value = false;
    }
}
function openDeudaModal() {
    deudaAction.value = 'nuevo';
    movimientoTipo.value = 'bi_debe';
    deudaForm.value = emptyDeuda();
    movimientoForm.value = emptyMovimiento();
    deudaModal?.show();
}
function resetDebtActionForm() {
    if (deudaAction.value === 'nuevo') deudaForm.value = emptyDeuda();
    else {
        movimientoTipo.value = 'bi_debe';
        movimientoForm.value = emptyMovimiento();
    }
}
async function saveDeudaAction() {
    if (deudaAction.value === 'nuevo') await saveDeuda();
    else await saveMovimientoDeuda();
}
function openArqueoModal() {
    cargarUltimoArqueo.value = false;
    arqueoForm.value = { monto_sistema: null, detalles: defaultDetalles() };
    arqueoModal?.show();
}
function cargarDatosUltimoArqueo() {
    if (!cargarUltimoArqueo.value) {
        arqueoForm.value.detalles = defaultDetalles();
        return;
    }

    const ultimoArqueo = arqueos.value[0];
    if (!ultimoArqueo) return;

    arqueoForm.value.detalles = defaultDetalles().map((detalle) => {
        const detalleAnterior = (ultimoArqueo.detalles ?? []).find((anterior) => (
            anterior.tipo === detalle.tipo
            && Number(anterior.denominacion) === Number(detalle.denominacion)
            && Number(anterior.unidades_por_paquete) === Number(detalle.unidades_por_paquete)
        ));

        return { ...detalle, cantidad: Number(detalleAnterior?.cantidad ?? 0) };
    });
}

async function loadData() {
    loading.value = true;
    try {
        const { data } = await axios.get('/agente-bi/get');
        const payload = data?.data ?? {};
        deudas.value = payload.deudas ?? [];
        arqueos.value = payload.arqueos ?? [];
        cuadres.value = payload.cuadres ?? [];
        resumen.value = { ...resumen.value, ...(payload.resumen ?? {}) };
        if (!cuadreFormInicializado.value) {
            cuadreForm.value.capital = decimalInput(resumen.value.capital);
            cuadreForm.value.banco = decimalInput(resumen.value.banco);
            cuadreForm.value.caja = decimalInput(resumen.value.caja);
            cuadreFormInicializado.value = true;
        }
    } catch (error) { setErrors(error, 'No se pudo cargar Agente BI.'); } finally { loading.value = false; }
}

function openConfirmCuadre() {
    confirmCuadreModal?.show();
}
async function saveCuadre() {
    errors.value = []; savingCuadre.value = true;
    try {
        await axios.post('/agente-bi/cuadres', { ...cuadreForm.value, capital: Number(cuadreForm.value.capital || 0), banco: Number(cuadreForm.value.banco || 0), caja: Number(cuadreForm.value.caja || 0) });
        formatCuadreField('capital');
        formatCuadreField('banco');
        formatCuadreField('caja');
        capitalEditable.value = false;
        cajaEditable.value = false;
        confirmCuadreModal?.hide();
        await loadData();
    } catch (error) { setErrors(error, 'No se pudo guardar el cuadre.'); } finally { savingCuadre.value = false; }
}

async function saveDeuda() {
    errors.value = []; savingDeuda.value = true;
    try {
        await axios.post('/agente-bi/deudas', { ...deudaForm.value, monto: Number(deudaForm.value.monto || 0) });
        deudaModal?.hide();
        deudaForm.value = emptyDeuda();
        await loadData();
    } catch (error) { setErrors(error, 'No se pudo guardar la deuda.'); } finally { savingDeuda.value = false; }
}

async function saveMovimientoDeuda() {
    errors.value = []; savingMovimiento.value = true;
    try {
        await axios.post(`/agente-bi/deudas/${movimientoForm.value.deuda_id}/movimientos`, { ...movimientoForm.value, monto: movimientoForm.value.tipo === 'anulacion' ? null : Number(movimientoForm.value.monto || 0) });
        deudaModal?.hide();
        movimientoForm.value = emptyMovimiento();
        await loadData();
    } catch (error) { setErrors(error, 'No se pudo guardar el movimiento.'); } finally { savingMovimiento.value = false; }
}

async function saveArqueo() {
    errors.value = []; savingArqueo.value = true;
    try {
        await axios.post('/agente-bi/arqueos', { monto_sistema: arqueoForm.value.monto_sistema === null || arqueoForm.value.monto_sistema === '' ? null : Number(arqueoForm.value.monto_sistema), detalles: arqueoForm.value.detalles.map((detalle) => ({ ...detalle, cantidad: Number(detalle.cantidad || 0) })) });
        arqueoForm.value = { monto_sistema: null, detalles: defaultDetalles() };
        arqueoModal?.hide();
        await loadData();
    } catch (error) { setErrors(error, 'No se pudo guardar el arqueo.'); } finally { savingArqueo.value = false; }
}
</script>

<style scoped>
.bi-card { background: #fff; border: 1px solid #e8edf3; border-radius: .85rem; padding: 1rem; height: 100%; }
.bi-card small { display: block; color: #5f6b7a; margin-bottom: .35rem; }
.bi-card strong { font-size: 1.35rem; }
.bi-card-financed { background: #edf8fc; border-color: #c5e6ef; }
.bi-card-located { background: #fff8ed; border-color: #f2ddba; }
.bi-card-balanced { background: #f0f8f2; border-color: #c8e3cf; }
.bi-card-unbalanced { background: #fff2f2; border-color: #f0c5c5; }
.bi-card-clickable { cursor: pointer; transition: transform .15s ease, box-shadow .15s ease; }
.bi-card-clickable:hover { box-shadow: 0 .35rem .8rem rgba(35, 55, 80, .12); transform: translateY(-1px); }
.bi-summary-card { display: block; width: 100%; color: inherit; text-align: left; }
.bi-summary-card:focus-visible { outline: 3px solid rgba(13, 110, 253, .35); outline-offset: 2px; }
.bi-cuadres-table { min-width: 720px; }

@media (max-width: 575.98px) {
    .agente-bi-view { padding-right: .75rem !important; padding-left: .75rem !important; }
    .bi-card { padding: .8rem; }
    .bi-card strong { font-size: 1.12rem; }
    .bi-action-buttons > .btn { width: 100%; }
    .agente-bi-view :deep(.modal-dialog) { margin: .5rem; }
    .agente-bi-view :deep(.table-responsive > .table) { min-width: 620px; }
}
</style>
