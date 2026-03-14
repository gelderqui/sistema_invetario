<template>
    <div>
        <!-- Encabezado -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Productos</h2>
            <button class="btn btn-brand" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-plus" class="me-2" />
                Nuevo
            </button>
        </div>

        <!-- Tabla -->
        <div v-if="loading" class="text-center py-5">
            <span class="spinner-border" />
        </div>

        <div v-else class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Codigo</th>
                            <th>Nombre</th>
                            <th>Categoria</th>
                            <th>Proveedor</th>
                            <th>Costo Prom.</th>
                            <th>Precio Venta</th>
                            <th>Stock Min.</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!productos.length">
                            <td colspan="10" class="text-center text-body-secondary py-4">Sin registros</td>
                        </tr>
                        <tr v-for="prod in productos" :key="prod.id">
                            <td><code>{{ prod.codigo }}</code></td>
                            <td class="fw-semibold">{{ prod.nombre }}</td>
                            <td>
                                <span v-if="prod.categoria" class="badge text-bg-light border">
                                    {{ prod.categoria.nombre }}
                                </span>
                                <span v-else class="text-body-secondary small">—</span>
                            </td>
                            <td>{{ prod.proveedor?.nombre ?? '—' }}</td>
                            <td>Q {{ Number(prod.costo_promedio ?? 0).toFixed(2) }}</td>
                            <td>Q {{ Number(prod.precio_venta ?? 0).toFixed(2) }}</td>
                            <td>{{ Number(prod.stock_minimo ?? 0).toFixed(2) }}</td>
                            <td>
                                <span
                                    class="badge"
                                    :class="prod.activo ? 'text-bg-success' : 'text-bg-secondary'"
                                >
                                    {{ prod.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-body-secondary small">{{ formatDate(prod.created_at) }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        title="Editar"
                                        @click="openEdit(prod)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                                    </button>
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        :title="prod.activo ? 'Desactivar' : 'Activar'"
                                        @click="openToggle(prod)"
                                    >
                                        <FontAwesomeIcon
                                            :icon="prod.activo ? 'fa-solid fa-ban' : 'fa-solid fa-check'"
                                            :class="prod.activo ? 'icon-action-disable' : 'icon-action-enable'"
                                        />
                                    </button>
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        title="Eliminar"
                                        @click="openDelete(prod)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-trash" class="icon-action-delete" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal crear / editar -->
        <div ref="formModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">
                            <FontAwesomeIcon :icon="editingId ? 'fa-solid fa-pen-to-square' : 'fa-solid fa-box'" class="me-2" />
                            {{ editingId ? 'Editar producto' : 'Nuevo producto' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>

                    <form novalidate @submit.prevent="save">
                        <div class="modal-body d-grid gap-3">
                            <div v-if="formErrors.length" class="alert alert-danger py-2 mb-0">
                                <ul class="mb-0 ps-3">
                                    <li v-for="(e, i) in formErrors" :key="i">{{ e }}</li>
                                </ul>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-nombre">Nombre *</label>
                                    <input
                                        id="p-nombre"
                                        v-model="form.nombre"
                                        type="text"
                                        class="form-control"
                                        required
                                        autocomplete="off"
                                    >
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-categoria">Categoria</label>
                                    <select id="p-categoria" v-model="form.categoria_id" class="form-select">
                                        <option :value="null">Sin categoria</option>
                                        <option
                                            v-for="cat in categoriasActivas"
                                            :key="cat.id"
                                            :value="cat.id"
                                        >
                                            {{ cat.nombre }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-proveedor">Proveedor referencial</label>
                                    <select id="p-proveedor" v-model="form.proveedor_id" class="form-select">
                                        <option :value="null">Sin proveedor</option>
                                        <option
                                            v-for="prov in proveedoresActivos"
                                            :key="prov.id"
                                            :value="prov.id"
                                        >
                                            {{ prov.nombre }}
                                        </option>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-codigo">Codigo *</label>
                                    <input
                                        id="p-codigo"
                                        v-model="form.codigo"
                                        type="text"
                                        class="form-control"
                                        required
                                        autocomplete="off"
                                    >
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-barra">Codigo de barra</label>
                                    <input
                                        id="p-barra"
                                        v-model="form.codigo_barra"
                                        type="text"
                                        class="form-control"
                                        autocomplete="off"
                                    >
                                </div>

                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-semibold" for="p-precio-venta">Precio venta</label>
                                    <input
                                        id="p-precio-venta"
                                        v-model.number="form.precio_venta"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        class="form-control"
                                    >
                                </div>

                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-semibold" for="p-costo-prom">Costo promedio</label>
                                    <input
                                        id="p-costo-prom"
                                        v-model.number="form.costo_promedio"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        class="form-control"
                                    >
                                </div>

                                <div class="col-12 col-sm-4">
                                    <label class="form-label fw-semibold" for="p-stock-min">Stock minimo</label>
                                    <input
                                        id="p-stock-min"
                                        v-model.number="form.stock_minimo"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        class="form-control"
                                    >
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="p-detalle">Detalle</label>
                                    <textarea
                                        id="p-detalle"
                                        v-model="form.detalle"
                                        class="form-control"
                                        rows="3"
                                    />
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="p-palabras">
                                        Palabras clave de busqueda
                                    </label>
                                    <input
                                        id="p-palabras"
                                        v-model="form.palabras_clave"
                                        type="text"
                                        class="form-control"
                                        placeholder="ej: leche, lacteo, bebida"
                                    >
                                    <div class="form-text">Separadas por comas para facilitar la busqueda.</div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input
                                            id="p-activo"
                                            v-model="form.activo"
                                            type="checkbox"
                                            class="form-check-input"
                                        >
                                        <label class="form-check-label" for="p-activo">Activo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                                <FontAwesomeIcon v-else icon="fa-solid fa-floppy-disk" class="me-2" />
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <!-- Modal activar / desactivar -->
        <div ref="toggleModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">
                            <FontAwesomeIcon
                                :icon="selected?.activo ? 'fa-solid fa-ban' : 'fa-solid fa-check'"
                                class="me-2"
                            />
                            {{ selected?.activo ? 'Desactivar' : 'Activar' }} producto
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            ¿Desea {{ selected?.activo ? 'desactivar' : 'activar' }} el producto
                            <strong>{{ selected?.nombre }}</strong>?
                        </p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                        <button
                            type="button"
                            class="btn btn-brand"
                            :disabled="toggling"
                            @click="confirmToggle"
                        >
                            <span v-if="toggling" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                            Confirmar
                        </button>
                    </div>
                </div>
            </div>
        </div>

        <!-- Modal eliminar -->
        <div ref="deleteModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">
                            <FontAwesomeIcon icon="fa-solid fa-triangle-exclamation" class="me-2" />
                            Eliminar producto
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">
                            ¿Eliminar el producto <strong>{{ selected?.nombre }}</strong>?
                        </p>
                        <p class="small text-body-secondary mb-0">Esta accion no se puede deshacer.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                        <button
                            type="button"
                            class="btn btn-brand"
                            :disabled="deleting"
                            @click="confirmDelete"
                        >
                            <span v-if="deleting" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                            Eliminar
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { onMounted, ref } from 'vue';

import axios from '@/bootstrap';

const productos = ref([]);
const categoriasActivas = ref([]);
const proveedoresActivos = ref([]);
const loading = ref(true);
const saving = ref(false);
const toggling = ref(false);
const deleting = ref(false);
const editingId = ref(null);
const selected = ref(null);
const formErrors = ref([]);

const formModalRef = ref(null);
const toggleModalRef = ref(null);
const deleteModalRef = ref(null);

let formModal = null;
let toggleModal = null;
let deleteModal = null;

const emptyForm = () => ({
    categoria_id: null,
    proveedor_id: null,
    nombre: '',
    codigo: '',
    codigo_barra: '',
    detalle: '',
    palabras_clave: '',
    precio_venta: 0,
    costo_promedio: 0,
    stock_minimo: 0,
    activo: true,
});

const form = ref(emptyForm());

onMounted(async () => {
    formModal = new Modal(formModalRef.value);
    toggleModal = new Modal(toggleModalRef.value);
    deleteModal = new Modal(deleteModalRef.value);
    await Promise.all([loadProductos(), loadCategorias(), loadProveedores()]);
});

async function loadProductos() {
    loading.value = true;
    try {
        const { data } = await axios.get('/catalogos/productos');
        productos.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadCategorias() {
    const { data } = await axios.get('/catalogos/categorias?solo_activas=1');
    categoriasActivas.value = data.data;
}

async function loadProveedores() {
    const { data } = await axios.get('/catalogos/proveedores');
    proveedoresActivos.value = (data.data ?? []).filter((item) => item.activo);
}

function openCreate() {
    editingId.value = null;
    form.value = emptyForm();
    formErrors.value = [];
    formModal.show();
}

function openEdit(prod) {
    editingId.value = prod.id;
    form.value = {
        categoria_id: prod.categoria_id ?? null,
        proveedor_id: prod.proveedor_id ?? null,
        nombre: prod.nombre,
        codigo: prod.codigo,
        codigo_barra: prod.codigo_barra ?? '',
        detalle: prod.detalle ?? '',
        palabras_clave: prod.palabras_clave ?? '',
        precio_venta: Number(prod.precio_venta ?? 0),
        costo_promedio: Number(prod.costo_promedio ?? 0),
        stock_minimo: Number(prod.stock_minimo ?? 0),
        activo: prod.activo,
    };
    formErrors.value = [];
    formModal.show();
}

function openToggle(prod) {
    selected.value = prod;
    toggleModal.show();
}

function openDelete(prod) {
    selected.value = prod;
    deleteModal.show();
}

async function save() {
    saving.value = true;
    formErrors.value = [];
    try {
        const payload = {
            ...form.value,
            codigo_barra: form.value.codigo_barra || null,
            proveedor_id: form.value.proveedor_id || null,
            precio_venta: Number(form.value.precio_venta || 0),
            costo_promedio: Number(form.value.costo_promedio || 0),
            stock_minimo: Number(form.value.stock_minimo || 0),
        };

        if (editingId.value) {
            const { data } = await axios.put(`/catalogos/productos/${editingId.value}`, payload);
            const idx = productos.value.findIndex((p) => p.id === editingId.value);
            if (idx !== -1) productos.value[idx] = data.data;
        } else {
            const { data } = await axios.post('/catalogos/productos', payload);
            productos.value.push(data.data);
            productos.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
        }
        formModal.hide();
    } catch (error) {
        const serverErrors = error.response?.data?.errors;
        formErrors.value = serverErrors
            ? Object.values(serverErrors).flat()
            : [error.response?.data?.message ?? 'Error al guardar.'];
    } finally {
        saving.value = false;
    }
}

async function confirmToggle() {
    toggling.value = true;
    try {
        const { data } = await axios.patch(`/catalogos/productos/${selected.value.id}/toggle`);
        const idx = productos.value.findIndex((p) => p.id === selected.value.id);
        if (idx !== -1) productos.value[idx] = { ...productos.value[idx], activo: data.data.activo };
        toggleModal.hide();
    } finally {
        toggling.value = false;
    }
}

async function confirmDelete() {
    deleting.value = true;
    try {
        await axios.delete(`/catalogos/productos/${selected.value.id}`);
        productos.value = productos.value.filter((p) => p.id !== selected.value.id);
        deleteModal.hide();
    } finally {
        deleting.value = false;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('es-GT');
}
</script>
