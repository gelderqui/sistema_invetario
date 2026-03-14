<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Compras</h2>
            <button class="btn btn-brand" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-cart-plus" class="me-2" />
                Nueva compra
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <span class="spinner-border" />
        </div>

        <div v-else class="card border-0 shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Numero</th>
                            <th>Proveedor</th>
                            <th>Fecha</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Estado</th>
                            <th>Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!compras.length">
                            <td colspan="7" class="text-center text-body-secondary py-4">Sin compras registradas</td>
                        </tr>
                        <tr v-for="compra in compras" :key="compra.id">
                            <td><code>{{ compra.numero }}</code></td>
                            <td>{{ compra.proveedor?.nombre ?? '-' }}</td>
                            <td>{{ formatDate(compra.fecha_compra) }}</td>
                            <td>{{ compra.detalles_count ?? 0 }}</td>
                            <td class="fw-semibold">Q {{ Number(compra.total ?? 0).toFixed(2) }}</td>
                            <td><span class="badge text-bg-success text-uppercase">{{ compra.estado }}</span></td>
                            <td class="text-body-secondary small">{{ formatDate(compra.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
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
                                    <select v-model="form.proveedor_id" class="form-select" required>
                                        <option :value="null">Seleccione proveedor</option>
                                        <option v-for="prov in catalogs.proveedores" :key="prov.id" :value="prov.id">
                                            {{ prov.nombre }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Observaciones</label>
                                <textarea v-model="form.observaciones" rows="2" class="form-control" />
                            </div>

                            <div class="d-flex justify-content-between align-items-center">
                                <h6 class="mb-0">Detalle de productos</h6>
                                <button type="button" class="btn btn-outline-brand btn-sm" @click="addItem">Agregar item</button>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm compra-items-table">
                                    <thead>
                                        <tr>
                                            <th style="min-width: 220px;">Producto</th>
                                            <th>Cantidad</th>
                                            <th>Costo unitario</th>
                                            <th>Precio venta</th>
                                            <th>Caducidad</th>
                                            <th>Peso</th>
                                            <th>Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!form.items.length">
                                            <td colspan="8" class="text-center text-body-secondary py-3">Agrega al menos un producto.</td>
                                        </tr>
                                        <tr v-for="(item, idx) in form.items" :key="idx">
                                            <td>
                                                <select v-model="item.producto_id" class="form-select form-select-sm">
                                                    <option :value="null">Seleccione</option>
                                                    <option v-for="prod in catalogs.productos" :key="prod.id" :value="prod.id">
                                                        {{ prod.codigo }} - {{ prod.nombre }}
                                                    </option>
                                                </select>
                                            </td>
                                            <td><input v-model.number="item.cantidad" type="number" step="0.0001" min="0.0001" class="form-control form-control-sm"></td>
                                            <td><input v-model.number="item.costo_unitario" type="number" step="0.0001" min="0.0001" class="form-control form-control-sm"></td>
                                            <td><input v-model.number="item.precio_venta" type="number" step="0.0001" min="0" class="form-control form-control-sm"></td>
                                            <td><input v-model="item.fecha_caducidad" type="date" class="form-control form-control-sm"></td>
                                            <td><input v-model.number="item.peso" type="number" step="0.0001" min="0" class="form-control form-control-sm"></td>
                                            <td class="fw-semibold">Q {{ itemSubtotal(item).toFixed(2) }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-action-brand" @click="removeItem(idx)">
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
                                <div class="text-end small text-body-secondary">El precio de venta puede modificarse por item.</div>
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
                                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                                {{ saving ? 'Guardando...' : 'Registrar compra' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { computed, onMounted, ref } from 'vue';

import axios from '@/bootstrap';
import FormErrors from '@/components/FormErrors.vue';

const compras = ref([]);
const catalogs = ref({ proveedores: [], productos: [] });
const loading = ref(true);
const saving = ref(false);
const formErrors = ref([]);
const alerts = ref([]);

const formModalRef = ref(null);
let formModal = null;

const emptyItem = () => ({
    producto_id: null,
    cantidad: 1,
    costo_unitario: 0,
    precio_venta: 0,
    fecha_caducidad: null,
    peso: null,
});

const emptyForm = () => ({
    proveedor_id: null,
    fecha_compra: new Date().toISOString().slice(0, 10),
    observaciones: '',
    items: [emptyItem()],
});

const form = ref(emptyForm());

const totalCompra = computed(() => form.value.items.reduce((sum, item) => sum + itemSubtotal(item), 0));

onMounted(async () => {
    formModal = new Modal(formModalRef.value);
    await Promise.all([loadCompras(), loadCatalogs()]);
});

function itemSubtotal(item) {
    return Number(item.cantidad || 0) * Number(item.costo_unitario || 0);
}

async function loadCompras() {
    loading.value = true;
    try {
        const { data } = await axios.get('/compras');
        compras.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadCatalogs() {
    const { data } = await axios.get('/compras/catalogs');
    catalogs.value = data.data;
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

function removeItem(index) {
    form.value.items.splice(index, 1);
}

async function save() {
    saving.value = true;
    formErrors.value = [];
    alerts.value = [];
    try {
        const payload = {
            proveedor_id: form.value.proveedor_id,
            fecha_compra: form.value.fecha_compra,
            observaciones: form.value.observaciones || null,
            items: form.value.items.filter((item) => item.producto_id).map((item) => ({
                producto_id: item.producto_id,
                cantidad: Number(item.cantidad || 0),
                costo_unitario: Number(item.costo_unitario || 0),
                precio_venta: item.precio_venta === '' || item.precio_venta === null ? null : Number(item.precio_venta),
                fecha_caducidad: item.fecha_caducidad || null,
                peso: item.peso === '' || item.peso === null ? null : Number(item.peso),
            })),
        };

        const { data } = await axios.post('/compras', payload);
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
</script>
