<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Compras</h2>
            <button class="btn btn-brand" :disabled="actionLocked" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-cart-plus" class="me-2" />
                Nueva compra
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Cargando información...</p>
        </div>

        <div v-else class="card border-0 shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Numero</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th>Productos</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!compras.length">
                            <td colspan="8" class="text-center text-body-secondary py-4">Sin compras registradas</td>
                        </tr>
                        <tr v-for="compra in compras" :key="compra.id">
                            <td><code>{{ compra.numero }}</code></td>
                            <td>{{ compra.proveedor?.nombre ?? '-' }}</td>
                            <td>{{ formatDate(compra.fecha_compra) }}</td>
                            <td>{{ compra.detalles_count ?? 0 }}</td>
                            <td class="fw-semibold">Q {{ Number(compra.total ?? 0).toFixed(2) }}</td>
                            <td>
                                <span :class="['badge text-uppercase', compra.estado === 'anulada' ? 'text-bg-danger' : 'text-bg-success']">
                                    {{ compra.estado }}
                                </span>
                            </td>
                            <td class="text-body-secondary small">{{ formatDate(compra.created_at) }}</td>
                            <td class="text-end">
                                <div class="d-inline-flex gap-2">
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-secondary"
                                        :disabled="saving"
                                        @click="openDetalleCompra(compra)"
                                    >
                                        Ver detalle
                                    </button>
                                    <button
                                        type="button"
                                        class="btn btn-sm btn-outline-danger"
                                        :disabled="saving || compra.estado !== 'activo'"
                                        @click="openAnularCompra(compra)"
                                    >
                                        Anular
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div ref="detailModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl modal-dialog-scrollable">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Detalle de compra</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">
                        <div v-if="detalleLoading" class="text-center py-4 text-body-secondary">Cargando detalle...</div>

                        <div v-else-if="detalleError" class="alert alert-danger mb-0">{{ detalleError }}</div>

                        <div v-else-if="detalleCompra">
                            <div class="row g-3 mb-3">
                                <div class="col-12 col-md-3">
                                    <div class="small text-body-secondary">Numero</div>
                                    <div class="fw-semibold">{{ detalleCompra.numero }}</div>
                                </div>
                                <div class="col-12 col-md-3">
                                    <div class="small text-body-secondary">Proveedor</div>
                                    <div class="fw-semibold">{{ detalleCompra.proveedor?.nombre ?? '-' }}</div>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="small text-body-secondary">Fecha</div>
                                    <div class="fw-semibold">{{ formatDate(detalleCompra.fecha_compra) }}</div>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="small text-body-secondary">Estado</div>
                                    <span :class="['badge text-uppercase', detalleCompra.estado === 'anulada' ? 'text-bg-danger' : 'text-bg-success']">
                                        {{ detalleCompra.estado }}
                                    </span>
                                </div>
                                <div class="col-12 col-md-2">
                                    <div class="small text-body-secondary">Total</div>
                                    <div class="fw-semibold">Q {{ Number(detalleCompra.total ?? 0).toFixed(2) }}</div>
                                </div>
                                <div class="col-12 col-md-3">
                                    <div class="small text-body-secondary">Tipo documento</div>
                                    <div class="fw-semibold">{{ documentTypeLabel(detalleCompra.tipo_documento) }}</div>
                                </div>
                                <div class="col-12 col-md-3">
                                    <div class="small text-body-secondary">Numero documento</div>
                                    <div class="fw-semibold">{{ detalleCompra.numero_documento || '-' }}</div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Medida</th>
                                            <th>Costo unitario</th>
                                            <th>Precio sugerido</th>
                                            <th>Precio aplicado</th>
                                            <th>Caducidad</th>
                                            <th>Subtotal</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!(detalleCompra.detalles ?? []).length">
                                            <td colspan="8" class="text-center text-body-secondary py-3">Sin productos en esta compra.</td>
                                        </tr>
                                        <tr v-for="detalle in (detalleCompra.detalles ?? [])" :key="detalle.id">
                                            <td>{{ detalle.producto?.nombre ?? '-' }}</td>
                                            <td>{{ Number(detalle.cantidad ?? 0).toFixed(2) }}</td>
                                            <td>{{ detalle.unidad_medida || '-' }}</td>
                                            <td>Q {{ Number(detalle.costo_unitario ?? 0).toFixed(2) }}</td>
                                            <td>Q {{ Number(detalle.precio_venta_sugerido ?? 0).toFixed(2) }}</td>
                                            <td>Q {{ Number(detalle.precio_venta_aplicado ?? 0).toFixed(2) }}</td>
                                            <td>{{ formatDate(detalle.fecha_caducidad) }}</td>
                                            <td class="fw-semibold">Q {{ Number(detalle.subtotal ?? 0).toFixed(2) }}</td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cerrar</button>
                    </div>
                </div>
            </div>
        </div>

        <div ref="formModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Nueva compra</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>

                    <form novalidate @submit.prevent="save">
                        <div class="modal-body d-grid gap-3">
                            <FormErrors :errors="formErrors" />

                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold">Fecha compra *</label>
                                    <input v-model="form.fecha_compra" type="date" class="form-control" required>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-semibold">Proveedor *</label>
                                    <Multiselect
                                        v-model="form.proveedor"
                                        :options="catalogs.proveedores"
                                        label="nombre"
                                        track-by="id"
                                        placeholder="Buscar proveedor..."
                                        :searchable="true"
                                        :allow-empty="false"
                                        :close-on-select="true"
                                        :show-labels="false"
                                        select-label="Seleccionar"
                                        selected-label="Seleccionado"
                                        deselect-label="Quitar"
                                    />
                                </div>
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold">Tipo documento</label>
                                    <select v-model="form.tipo_documento" class="form-select">
                                        <option value="sin_documento">Sin documento</option>
                                        <option value="recibo">Recibo</option>
                                        <option value="factura">Factura</option>
                                    </select>
                                </div>
                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-semibold">Numero documento</label>
                                    <input
                                        v-model.trim="form.numero_documento"
                                        type="text"
                                        class="form-control"
                                        placeholder="Numero de recibo o factura"
                                    >
                                </div>
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Detalle de productos</h6>
                                <button type="button" class="btn btn-outline-brand btn-sm" :disabled="saving" @click="addItem">Agregar item</button>
                            </div>

                            <div class="table-responsive compra-items-wrap">
                                <table class="table table-sm compra-items-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 220px;">Producto</th>
                                            <th style="min-width: 110px;">Cantidad</th>
                                            <th style="min-width: 140px;">Medida</th>
                                            <th style="min-width: 140px;">Costo unitario</th>
                                            <th style="min-width: 140px;">Precio venta</th>
                                            <th style="min-width: 140px;">Caducidad</th>
                                            <th style="min-width: 130px;">Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!form.items.length">
                                            <td colspan="8" class="text-center text-body-secondary py-3">Agrega al menos un producto.</td>
                                        </tr>
                                        <tr v-for="(item, idx) in form.items" :key="idx">
                                            <td>
                                                <Multiselect
                                                    v-model="item.producto"
                                                    :options="productOptionsForItem(item)"
                                                    :custom-label="productOptionLabel"
                                                    track-by="id"
                                                    placeholder="Buscar por nombre, codigo o palabras clave"
                                                    :searchable="true"
                                                    :allow-empty="false"
                                                    :close-on-select="true"
                                                    :show-labels="false"
                                                    :internal-search="false"
                                                    select-label="Seleccionar"
                                                    selected-label="Seleccionado"
                                                    deselect-label="Quitar"
                                                    @search-change="onProductSearchChange(item, $event)"
                                                    @select="onProductSelect(item, $event)"
                                                    @remove="onProductClear(item)"
                                                    @clear="onProductClear(item)"
                                                />
                                            </td>
                                            <td>
                                                <input
                                                    v-model.number="item.cantidad"
                                                    type="number"
                                                    step="1"
                                                    min="1"
                                                    class="form-control form-control-sm"
                                                    @input="normalizeQuantityInput(item)"
                                                    @blur="normalizeQuantityInput(item)"
                                                >
                                            </td>
                                            <td>
                                                <span class="form-control-plaintext form-control-sm py-1 d-block">
                                                    {{ item.unidad_medida || '—' }}
                                                </span>
                                            </td>
                                            <td>
                                                <input
                                                    v-model.number="item.costo_unitario"
                                                    type="number"
                                                    step="0.0001"
                                                    min="0.0001"
                                                    class="form-control form-control-sm"
                                                    @input="handleCostInput(item)"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    v-model.number="item.precio_venta"
                                                    type="number"
                                                    step="0.0001"
                                                    min="0"
                                                    class="form-control form-control-sm"
                                                    @input="handleSalePriceInput(item, $event)"
                                                >
                                            </td>
                                            <td>
                                                <input
                                                    v-if="itemRequiresCaducidad(item)"
                                                    v-model="item.fecha_caducidad"
                                                    type="date"
                                                    class="form-control form-control-sm"
                                                    required
                                                >
                                                <span v-else class="form-control-plaintext form-control-sm py-1 d-block text-body-secondary">No aplica</span>
                                            </td>
                                            <td class="fw-semibold">Q {{ itemSubtotal(item).toFixed(2) }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-action-brand" :disabled="saving" @click="removeItem(idx)">
                                                    <FontAwesomeIcon icon="fa-solid fa-trash" class="icon-action-delete" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="compra-total-box d-flex justify-content-between align-items-center">
                                <div>
                                    <div class="small text-body-secondary">Total estimado</div>
                                    <div class="h5 mb-0">Q {{ totalCompra.toFixed(2) }}</div>
                                </div>
                                <div class="text-end small text-body-secondary">Precio de venta sugerido: costo + {{ porcentajeUtilidadCompra }}%. Puede modificarse por item.</div>
                            </div>

                            <div v-if="alerts.length" class="alert alert-warning mb-0">
                                <strong>Alertas de precio:</strong>
                                <ul class="compra-alert-list mt-2">
                                    <li v-for="(alert, index) in alerts" :key="index">{{ alert }}</li>
                                </ul>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="saving">
                                {{ saving ? 'Guardando...' : 'Registrar compra' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <ModalConfirm
            ref="anularModalRef"
            title="Anular compra"
            :message="anularMessage"
            hint="Esta accion revierte inventario y cambia el estado a anulada."
            confirm-text="Anular"
            :loading="saving"
            :error-message="anularError"
            @confirm="confirmAnularCompra"
            @hidden="onAnularModalHidden"
        />
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { computed, onMounted, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

import axios from '@/bootstrap';
import FormErrors from '@/components/FormErrors.vue';
import ModalConfirm from '@/components/components_ui/ModalConfirm.vue';

const compras = ref([]);
const catalogs = ref({ categorias: [], proveedores: [], productos: [] });
const proveedorGeneral = ref(null);
const loading = ref(true);
const saving = ref(false);
const formErrors = ref([]);
const alerts = ref([]);
const porcentajeUtilidadCompra = ref(25);
const anularError = ref('');
const selectedCompra = ref(null);
const detalleLoading = ref(false);
const detalleError = ref('');
const detalleCompra = ref(null);

const formModalRef = ref(null);
const anularModalRef = ref(null);
const detailModalRef = ref(null);
let formModal = null;
let detailModal = null;

const emptyItem = () => ({
    producto: null,
    producto_id: null,
    producto_search: '',
    cantidad: 1,
    unidad_medida: '',
    costo_unitario: 0,
    precio_venta: 0,
    precio_venta_manual: false,
    fecha_caducidad: '',
});

const emptyForm = () => ({
    proveedor: proveedorGeneral.value,
    fecha_compra: new Date().toISOString().slice(0, 10),
    tipo_documento: 'sin_documento',
    numero_documento: '',
    items: [emptyItem()],
});

const form = ref(emptyForm());

const totalCompra = computed(() => form.value.items.reduce((sum, item) => sum + itemSubtotal(item), 0));
const actionLocked = computed(() => loading.value || saving.value);
const anularMessage = computed(() => `¿Deseas anular la compra <strong>${selectedCompra.value?.numero ?? ''}</strong>?`);

onMounted(async () => {
    formModal = new Modal(formModalRef.value);
    detailModal = new Modal(detailModalRef.value);
    await Promise.all([loadCompras(), loadCatalogs()]);
});

function itemSubtotal(item) {
    return Number(item.cantidad || 0) * Number(item.costo_unitario || 0);
}

async function loadCompras() {
    loading.value = true;
    try {
        const { data } = await axios.get('/compras/get');
        compras.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadCatalogs() {
    const { data } = await axios.get('/compras/get/catalogs');
    catalogs.value = data.data;
    porcentajeUtilidadCompra.value = Number(data.data.porcentaje_utilidad_compra ?? 25);
    const generalId = data.data.proveedor_general_id;
    if (generalId) {
        const prov = data.data.proveedores.find((p) => p.id === generalId);
        if (prov) proveedorGeneral.value = prov;
    }
}

function openCreate() {
    form.value = emptyForm();
    formErrors.value = [];
    alerts.value = [];
    formModal.show();
}

function addItem() {
    form.value.items.push(emptyItem());
}

function productOptionLabel(producto) {
    return producto?.nombre ?? '';
}

function productOptionsForItem(item) {
    const base = catalogs.value.productos;
    const query = String(item.producto_search || '').trim().toLowerCase();
    const selectedIdsInOtherItems = new Set(
        form.value.items
            .filter((other) => other !== item)
            .map((other) => Number(other.producto_id || 0))
            .filter((id) => id > 0)
    );

    const available = base.filter((prod) => !selectedIdsInOtherItems.has(Number(prod.id)));

    if (!query) return available;

    return available.filter((prod) => {
        const nombre = String(prod.nombre || '').toLowerCase();
        const codigo = String(prod.codigo_barra || '').toLowerCase();
        const claves = String(prod.palabras_clave || '').toLowerCase();

        return nombre.includes(query) || codigo.includes(query) || claves.includes(query);
    });
}

function onProductSearchChange(item, searchText) {
    item.producto_search = String(searchText || '');
}

function onProductSelect(item, selectedOption) {
    const producto = selectedOption ?? null;
    item.producto = producto;

    if (!producto) {
        onProductClear(item);
        return;
    }

    applySelectedProduct(item, producto);
}

function onProductClear(item) {
    item.producto = null;
    item.producto_id = null;
    item.producto_search = '';
    item.unidad_medida = '';
    item.precio_venta_manual = false;
    item.precio_venta = 0;
    item.fecha_caducidad = '';
}

function applySelectedProduct(item, producto) {
    item.producto = producto;
    item.producto_id = Number(producto.id);
    item.producto_search = '';
    syncItemMeasure(item, producto);
    syncCaducidadRequirement(item, producto);
    syncSuggestedSalePrice(item);
}

function getItemProduct(item) {
    return item.producto
        ?? catalogs.value.productos.find((prod) => Number(prod.id) === Number(item.producto_id))
        ?? null;
}

function itemRequiresCaducidad(item) {
    const producto = getItemProduct(item);
    return Boolean(producto?.control_vencimiento);
}

function syncCaducidadRequirement(item, selectedProducto = null) {
    const producto = selectedProducto ?? getItemProduct(item);

    if (!producto?.control_vencimiento) {
        item.fecha_caducidad = '';
        return;
    }

    if (!item.fecha_caducidad) {
        item.fecha_caducidad = new Date().toISOString().slice(0, 10);
    }
}

function syncItemMeasure(item, selectedProducto = null) {
    const producto = selectedProducto
        ?? catalogs.value.productos.find((prod) => Number(prod.id) === Number(item.producto_id));

    if (!producto) {
        item.unidad_medida = '—';
        return;
    }

    const um = producto.unidad_medida ?? producto.unidadMedida ?? null;

    if (um && typeof um === 'object') {
        const nombre = String(um.nombre ?? '').trim();
        const abreviatura = String(um.abreviatura ?? '').trim();

        item.unidad_medida = nombre && abreviatura
            ? `${nombre} (${abreviatura})`
            : (nombre || abreviatura || '—');
        return;
    }

    if (typeof um === 'string' && um.trim()) {
        item.unidad_medida = um.trim();
        return;
    }

    item.unidad_medida = '—';
}

function calculateSuggestedSalePrice(cost) {
    const numericCost = Number(cost || 0);
    if (numericCost <= 0) return 0;

    const factor = 1 + (Number(porcentajeUtilidadCompra.value || 0) / 100);

    return Number((numericCost * factor).toFixed(4));
}

function syncSuggestedSalePrice(item, force = false) {
    if (item.precio_venta_manual && !force) return;
    item.precio_venta = calculateSuggestedSalePrice(item.costo_unitario);
}

function handleCostInput(item) {
    syncSuggestedSalePrice(item);
}

function handleSalePriceInput(item, event) {
    const rawValue = String(event?.target?.value ?? '').trim();

    if (rawValue === '') {
        item.precio_venta_manual = false;
        syncSuggestedSalePrice(item, true);
        return;
    }

    item.precio_venta_manual = true;
}

function normalizeQuantityInput(item) {
    const value = Number(item.cantidad || 0);

    if (!Number.isFinite(value) || value <= 0) {
        item.cantidad = 1;
        return;
    }

    item.cantidad = Math.max(1, Math.trunc(value));
}

function removeItem(index) {
    form.value.items.splice(index, 1);
}

async function save() {
    const validationErrors = [];

    if (!form.value.fecha_compra) {
        validationErrors.push('La fecha de compra es obligatoria.');
    }

    if (!form.value.proveedor?.id) {
        validationErrors.push('Seleccione un proveedor válido de la lista.');
    }

    if (form.value.tipo_documento !== 'sin_documento' && !String(form.value.numero_documento || '').trim()) {
        validationErrors.push('Debe ingresar el numero de documento cuando el tipo es recibo o factura.');
    }

    if (!form.value.items.length) {
        validationErrors.push('Debe agregar al menos un item.');
    }

    form.value.items.forEach((item, index) => {
        const row = index + 1;

        if (!item.producto?.id) validationErrors.push(`Item ${row}: el producto es obligatorio.`);
        if (!Number.isInteger(Number(item.cantidad)) || Number(item.cantidad) < 1) validationErrors.push(`Item ${row}: la cantidad debe ser un entero mayor o igual a 1.`);
        if (Number(item.costo_unitario || 0) <= 0) validationErrors.push(`Item ${row}: el costo unitario es obligatorio y debe ser mayor a 0.`);
        if (Number(item.precio_venta || 0) <= 0) validationErrors.push(`Item ${row}: el precio de venta es obligatorio y debe ser mayor a 0.`);
        if (itemRequiresCaducidad(item) && !item.fecha_caducidad) validationErrors.push(`Item ${row}: la fecha de caducidad es obligatoria para productos con control de vencimiento.`);
    });

    if (validationErrors.length) {
        formErrors.value = validationErrors;
        return;
    }

    saving.value = true;
    formErrors.value = [];
    alerts.value = [];
    try {
        const payload = {
            proveedor_id: form.value.proveedor.id,
            fecha_compra: form.value.fecha_compra,
            tipo_documento: form.value.tipo_documento,
            numero_documento: form.value.tipo_documento === 'sin_documento' ? null : (form.value.numero_documento || null),
            items: form.value.items.map((item) => ({
                producto_id: item.producto_id,
                cantidad: Math.max(1, Math.trunc(Number(item.cantidad || 0))),
                costo_unitario: Number(item.costo_unitario || 0),
                precio_venta: Number(item.precio_venta),
                fecha_caducidad: itemRequiresCaducidad(item) ? item.fecha_caducidad : null,
            })),
        };

        const { data } = await axios.post('/compras/store', payload);
        compras.value.unshift(data.data);
        alerts.value = data.alerts ?? [];
        formModal.hide();
    } catch (error) {
        const errors = error.response?.data?.errors;
        formErrors.value = errors ? Object.values(errors).flat() : [error.response?.data?.message ?? 'No se pudo registrar la compra.'];
    } finally {
        saving.value = false;
    }
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('es-GT');
}

function documentTypeLabel(value) {
    if (value === 'sin_documento') return 'Sin documento';
    if (value === 'recibo') return 'Recibo';
    if (value === 'factura') return 'Factura';
    return 'Sin documento';
}

function openAnularCompra(compra) {
    if (!compra?.id || compra.estado !== 'activo') return;

    selectedCompra.value = compra;
    anularError.value = '';
    anularModalRef.value?.open();
}

async function openDetalleCompra(compra) {
    if (!compra?.id) return;

    detalleCompra.value = null;
    detalleError.value = '';
    detalleLoading.value = true;
    detailModal?.show();

    try {
        const { data } = await axios.get(`/compras/get/${compra.id}`);
        detalleCompra.value = data.data;
    } catch (error) {
        detalleError.value = error.response?.data?.message ?? 'No se pudo cargar el detalle de la compra.';
    } finally {
        detalleLoading.value = false;
    }
}

function onAnularModalHidden() {
    anularError.value = '';
}

async function confirmAnularCompra() {
    if (!selectedCompra.value?.id) return;

    saving.value = true;
    formErrors.value = [];
    try {
        const { data } = await axios.patch(`/compras/anular/${selectedCompra.value.id}`);
        const idx = compras.value.findIndex((row) => row.id === selectedCompra.value.id);
        if (idx >= 0) {
            compras.value[idx] = {
                ...compras.value[idx],
                ...data.data,
            };
        }

        anularModalRef.value?.close();
    } catch (error) {
        const backend = error.response?.data?.errors;
        anularError.value = backend
            ? Object.values(backend).flat().join(' ')
            : (error.response?.data?.message ?? 'No se pudo anular la compra.');
    } finally {
        saving.value = false;
    }
}
</script>

<style scoped>
.compra-items-wrap {
    overflow: visible;
}

.compra-items-table,
.compra-items-table tbody,
.compra-items-table tr,
.compra-items-table td {
    overflow: visible;
}

:deep(.multiselect) {
    z-index: 1;
}

:deep(.multiselect__content-wrapper) {
    z-index: 4000;
}
</style>
