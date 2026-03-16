<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Ventas (POS)</h2>
            <button class="btn btn-brand" :disabled="actionLocked" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-cash-register" class="me-2" />
                Nueva venta
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Cargando informacion...</p>
        </div>

        <div v-else class="card border-0 shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Numero</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Metodo</th>
                            <th>Creado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!ventas.length">
                            <td colspan="8" class="text-center text-body-secondary py-4">Sin ventas registradas</td>
                        </tr>
                        <tr v-for="venta in ventas" :key="venta.id">
                            <td><code>{{ venta.numero }}</code></td>
                            <td>{{ venta.cliente?.nombre ?? 'Consumidor final' }}</td>
                            <td>{{ formatDate(venta.fecha_venta) }}</td>
                            <td>
                                <span :class="['badge text-uppercase', venta.estado === 'anulada' ? 'text-bg-danger' : 'text-bg-success']">
                                    {{ venta.estado }}
                                </span>
                            </td>
                            <td>{{ venta.detalles_count ?? 0 }}</td>
                            <td class="fw-semibold">Q {{ formatMoney(venta.total) }}</td>
                            <td class="text-uppercase">{{ venta.metodo_pago }}</td>
                            <td class="text-body-secondary small">{{ formatDate(venta.created_at) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div ref="formModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-xl">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Nueva venta</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>

                    <form novalidate @submit.prevent="save">
                        <div class="modal-body d-grid gap-3">
                            <FormErrors :errors="formErrors" />

                            <div class="row g-3">
                                <div class="col-12 col-md-4">
                                    <label class="form-label fw-semibold">Fecha venta *</label>
                                    <input v-model="form.fecha_venta" type="date" class="form-control" required>
                                </div>

                                <div class="col-12 col-md-8">
                                    <label class="form-label fw-semibold">Cliente</label>
                                    <Multiselect
                                        v-model="form.cliente"
                                        :options="catalogs.clientes"
                                        label="nombre"
                                        track-by="id"
                                        placeholder="Buscar cliente..."
                                        :searchable="true"
                                        :allow-empty="true"
                                        :close-on-select="true"
                                        :show-labels="false"
                                    />
                                </div>
                            </div>

                            <div class="card border-0 bg-light-subtle">
                                <div class="card-body row g-2 align-items-end">
                                    <div class="col-12 col-md-6">
                                        <label class="form-label fw-semibold mb-1">Buscar producto</label>
                                        <input
                                            v-model="finder.query"
                                            list="ventas-productos-list"
                                            type="text"
                                            class="form-control"
                                            placeholder="Nombre, barra o palabras clave"
                                            autocomplete="off"
                                            @input="resolveFinderProduct"
                                            @keydown.enter.prevent="quickAddFinder"
                                        >
                                        <datalist id="ventas-productos-list">
                                            <option v-for="prod in catalogs.productos" :key="prod.id" :value="productSearchText(prod)" />
                                        </datalist>
                                        <div class="form-text">Tip: puedes escanear y presionar Enter para agregar rapido.</div>
                                    </div>

                                    <div class="col-6 col-md-2">
                                        <label class="form-label fw-semibold mb-1">Cantidad</label>
                                        <input
                                            v-model.number="finder.cantidad"
                                            type="number"
                                            step="1"
                                            min="1"
                                            class="form-control"
                                            @keydown.enter.prevent="quickAddFinder"
                                        >
                                    </div>

                                    <div class="col-6 col-md-2">
                                        <label class="form-label fw-semibold mb-1">Precio</label>
                                        <input
                                            v-model.number="finder.precio_unitario"
                                            type="number"
                                            step="0.0001"
                                            min="0"
                                            class="form-control"
                                        >
                                    </div>

                                    <div class="col-12 col-md-2 d-grid">
                                        <button type="button" class="btn btn-brand" @click="quickAddFinder">Agregar</button>
                                    </div>
                                </div>
                            </div>

                            <div class="table-responsive">
                                <table class="table table-sm align-middle mb-0">
                                    <thead>
                                        <tr>
                                            <th>Producto</th>
                                            <th>Cantidad</th>
                                            <th>Precio</th>
                                            <th>Subtotal</th>
                                            <th></th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr v-if="!form.items.length">
                                            <td colspan="5" class="text-center text-body-secondary py-3">No hay productos agregados.</td>
                                        </tr>
                                        <tr v-for="(item, idx) in form.items" :key="idx">
                                            <td>
                                                <div class="fw-semibold">{{ item.producto_nombre }}</div>
                                                <div class="small text-body-secondary">Stock: {{ Number(item.stock_actual ?? 0).toFixed(0) }}</div>
                                            </td>
                                            <td>
                                                <input v-model.number="item.cantidad" type="number" step="1" min="1" class="form-control form-control-sm">
                                            </td>
                                            <td>
                                                <input v-model.number="item.precio_unitario" type="number" step="0.0001" min="0" class="form-control form-control-sm">
                                            </td>
                                            <td class="fw-semibold">Q {{ formatMoney(itemSubtotal(item)) }}</td>
                                            <td>
                                                <button type="button" class="btn btn-sm btn-action-brand" :disabled="saving" @click="removeItem(idx)">
                                                    <FontAwesomeIcon icon="fa-solid fa-trash" class="icon-action-delete" />
                                                </button>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>

                            <div class="row g-3 align-items-end">
                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-semibold">Metodo de pago *</label>
                                    <select v-model="form.metodo_pago" class="form-select" required>
                                        <option v-for="metodo in catalogs.metodos_pago" :key="metodo" :value="metodo">{{ metodo }}</option>
                                    </select>
                                </div>

                                <div class="col-12 col-md-3">
                                    <label class="form-label fw-semibold">Descuento</label>
                                    <input v-model.number="form.descuento" type="number" step="0.0001" min="0" class="form-control">
                                </div>

                                <div v-if="form.metodo_pago === 'efectivo'" class="col-12 col-md-3">
                                    <label class="form-label fw-semibold">Recibido</label>
                                    <input v-model.number="form.monto_recibido" type="number" step="0.0001" min="0" class="form-control">
                                </div>

                                <div class="col-12 col-md-3">
                                    <div class="small text-body-secondary">Subtotal</div>
                                    <div class="h6 mb-1">Q {{ formatMoney(subtotalVenta) }}</div>
                                    <div class="small text-body-secondary">Total</div>
                                    <div class="h5 mb-0">Q {{ formatMoney(totalVenta) }}</div>
                                    <div v-if="form.metodo_pago === 'efectivo' && form.monto_recibido" class="small mt-1">
                                        Cambio: <strong>Q {{ formatMoney(cambioVenta) }}</strong>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="saving || !form.items.length">
                                {{ saving ? 'Guardando...' : 'Finalizar venta' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <TicketReceiptModal ref="receiptModalRef" title="Recibo de venta" />
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { computed, onMounted, ref } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

import axios from '@/bootstrap';
import FormErrors from '@/components/FormErrors.vue';
import TicketReceiptModal from '@/components/TicketReceiptModal.vue';
import { formatMoney } from '@/utils/formatters';

const ventas = ref([]);
const catalogs = ref({ clientes: [], productos: [], metodos_pago: [] });
const loading = ref(true);
const saving = ref(false);
const formErrors = ref([]);
const consumidorFinal = ref(null);

const finder = ref({
    query: '',
    producto: null,
    cantidad: 1,
    precio_unitario: 0,
});

const formModalRef = ref(null);
const receiptModalRef = ref(null);
let formModal = null;

const emptyForm = () => ({
    cliente: consumidorFinal.value,
    fecha_venta: new Date().toISOString().slice(0, 10),
    metodo_pago: 'efectivo',
    descuento: 0,
    monto_recibido: null,
    observaciones: '',
    items: [],
});

const form = ref(emptyForm());

const subtotalVenta = computed(() => form.value.items.reduce((sum, item) => sum + itemSubtotal(item), 0));
const totalVenta = computed(() => Math.max(0, Number(subtotalVenta.value) - Number(form.value.descuento || 0)));
const cambioVenta = computed(() => Math.max(0, Number(form.value.monto_recibido || 0) - Number(totalVenta.value || 0)));
const actionLocked = computed(() => loading.value || saving.value);

onMounted(async () => {
    formModal = new Modal(formModalRef.value);
    await Promise.all([loadVentas(), loadCatalogs()]);
});

async function loadVentas() {
    loading.value = true;
    try {
        const { data } = await axios.get('/ventas/get');
        ventas.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadCatalogs() {
    const { data } = await axios.get('/ventas/get/catalogs');
    catalogs.value = data.data;
    const id = data.data.consumidor_final_id;
    consumidorFinal.value = data.data.clientes.find((c) => c.id === id) ?? null;
}

function openCreate() {
    form.value = emptyForm();
    finder.value = {
        query: '',
        producto: null,
        cantidad: 1,
        precio_unitario: 0,
    };
    formErrors.value = [];
    formModal.show();
}

function productSearchText(producto) {
    const parts = [producto.nombre];

    if (producto.codigo_barra) parts.push(`Barra: ${producto.codigo_barra}`);
    if (producto.palabras_clave) parts.push(`Clave: ${producto.palabras_clave}`);

    return parts.join(' | ');
}

function resolveFinderProduct() {
    const query = String(finder.value.query || '').trim().toLowerCase();

    if (!query) {
        finder.value.producto = null;
        finder.value.precio_unitario = 0;
        return;
    }

    const exactByCode = catalogs.value.productos.find((p) => (p.codigo_barra || '').toLowerCase() === query);
    if (exactByCode) {
        finder.value.producto = exactByCode;
        finder.value.precio_unitario = Number(exactByCode.precio_venta || 0);
        return;
    }

    const matches = catalogs.value.productos.filter((p) => {
        const nombre = (p.nombre || '').toLowerCase();
        const codigoBarra = (p.codigo_barra || '').toLowerCase();
        const palabrasClave = (p.palabras_clave || '').toLowerCase();
        return nombre.includes(query) || codigoBarra.includes(query) || palabrasClave.includes(query);
    });

    if (matches.length === 1) {
        finder.value.producto = matches[0];
        finder.value.precio_unitario = Number(matches[0].precio_venta || 0);
    }
}

function quickAddFinder() {
    resolveFinderProduct();

    const producto = finder.value.producto;
    if (!producto) {
        formErrors.value = ['No se encontro producto para el texto ingresado.'];
        return;
    }

    const cantidad = Math.trunc(Number(finder.value.cantidad || 0));
    if (cantidad <= 0) {
        formErrors.value = ['La cantidad debe ser mayor a cero.'];
        return;
    }

    const stockActual = Number(producto.stock_actual || 0);

    const existing = form.value.items.find((i) => i.producto_id === producto.id);
    if (existing) {
        const nuevaCantidad = Number(existing.cantidad || 0) + cantidad;
        if (nuevaCantidad > stockActual) {
            formErrors.value = [`Stock insuficiente para ${producto.nombre}. Disponible: ${stockActual}.`];
            return;
        }
        existing.cantidad = nuevaCantidad;
        existing.precio_unitario = Number(finder.value.precio_unitario || existing.precio_unitario || 0);
    } else {
        if (cantidad > stockActual) {
            formErrors.value = [`Stock insuficiente para ${producto.nombre}. Disponible: ${stockActual}.`];
            return;
        }

        form.value.items.push({
            producto_id: producto.id,
            producto_nombre: producto.nombre,
            stock_actual: stockActual,
            cantidad,
            precio_unitario: Number(finder.value.precio_unitario || producto.precio_venta || 0),
        });
    }

    formErrors.value = [];
    finder.value.query = '';
    finder.value.producto = null;
    finder.value.cantidad = 1;
    finder.value.precio_unitario = 0;
}

function removeItem(index) {
    form.value.items.splice(index, 1);
}

function itemSubtotal(item) {
    return Number(item.cantidad || 0) * Number(item.precio_unitario || 0);
}

async function save() {
    saving.value = true;
    formErrors.value = [];

    try {
        const payload = {
            cliente_id: form.value.cliente?.id ?? null,
            fecha_venta: form.value.fecha_venta,
            metodo_pago: form.value.metodo_pago,
            descuento: Number(form.value.descuento || 0),
            monto_recibido: form.value.metodo_pago === 'efectivo' ? Number(form.value.monto_recibido || 0) : null,
            observaciones: form.value.observaciones || null,
            items: form.value.items.map((item) => ({
                producto_id: item.producto_id,
                cantidad: Number(item.cantidad || 0),
                precio_unitario: Number(item.precio_unitario || 0),
            })),
        };

        const { data } = await axios.post('/ventas/store', payload);
        ventas.value.unshift(data.data);
        formModal.hide();

        const ticketUrl = `/ventas/${data.data.id}/ticket`;
        receiptModalRef.value?.open(ticketUrl);
    } catch (error) {
        const errors = error.response?.data?.errors;
        formErrors.value = errors ? Object.values(errors).flat() : [error.response?.data?.message ?? 'No se pudo registrar la venta.'];
    } finally {
        saving.value = false;
    }
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('es-GT');
}
</script>
