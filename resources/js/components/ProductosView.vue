<template>
    <div>
        <!-- Encabezado -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Productos</h2>
            <button class="btn btn-brand" :disabled="actionLocked" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-plus" class="me-2" />
                Nuevo
            </button>
        </div>

        <!-- Tabla -->
        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Cargando información...</p>
        </div>

        <div v-else class="card border-0 shadow-sm">
            <div class="px-3 pt-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label class="form-label fw-semibold mb-0" for="filtro-estado-producto">Filtrar estado:</label>
                    <select id="filtro-estado-producto" v-model="statusFilter" class="form-select form-select-sm w-auto">
                        <option value="todos">Todos</option>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>

                    <label class="form-label fw-semibold mb-0 ms-sm-2" for="filtro-categoria-producto">Categoria:</label>
                    <select id="filtro-categoria-producto" v-model="categoryFilter" class="form-select form-select-sm w-auto">
                        <option value="todas">Todas</option>
                        <option value="sin_categoria">Sin categoria</option>
                        <option v-for="cat in categoryFilterOptions" :key="cat.id" :value="String(cat.id)">
                            {{ cat.nombre }}
                        </option>
                    </select>

                    <label class="form-label fw-semibold mb-0 ms-sm-2" for="filtro-nombre-producto">Nombre:</label>
                    <input
                        id="filtro-nombre-producto"
                        v-model.trim="nameFilter"
                        type="text"
                        class="form-control form-control-sm"
                        style="max-width: 260px;"
                        placeholder="Buscar por nombre"
                    >
                </div>
            </div>

            <div class="table-responsive mt-3">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Nombre</th>
                            <th>Categoria</th>
                            <th>Proveedor</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!displayedProductos.length">
                            <td colspan="6" class="text-center text-body-secondary py-4">Sin registros</td>
                        </tr>
                        <tr v-for="prod in displayedProductos" :key="prod.id">
                            <td class="fw-semibold">{{ prod.nombre }}</td>
                            <td>
                                <span v-if="prod.categoria" class="badge text-bg-light border">
                                    {{ prod.categoria.nombre }}
                                </span>
                                <span v-else class="text-body-secondary small">—</span>
                            </td>
                            <td>{{ prod.proveedor?.nombre ?? '—' }}</td>
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
                                        :disabled="actionLocked"
                                        @click="openEdit(prod)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                                    </button>
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        :title="prod.activo ? 'Desactivar' : 'Activar'"
                                        :disabled="actionLocked"
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
                                        :disabled="actionLocked"
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
                                    <Multiselect
                                        id="p-categoria"
                                        v-model="selectedCategoriaOption"
                                        :options="categoriasActivas"
                                        label="nombre"
                                        track-by="id"
                                        placeholder="Buscar categoria..."
                                        :searchable="true"
                                        :allow-empty="true"
                                        :close-on-select="true"
                                        :show-labels="false"
                                        select-label="Seleccionar"
                                        selected-label="Seleccionado"
                                        deselect-label="Quitar"
                                    />
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-proveedor">Proveedor referencial</label>
                                    <Multiselect
                                        id="p-proveedor"
                                        v-model="selectedProveedorOption"
                                        :options="proveedoresActivos"
                                        label="nombre"
                                        track-by="id"
                                        placeholder="Buscar proveedor..."
                                        :searchable="true"
                                        :allow-empty="true"
                                        :close-on-select="true"
                                        :show-labels="false"
                                        select-label="Seleccionar"
                                        selected-label="Seleccionado"
                                        deselect-label="Quitar"
                                    />
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
                                    <div v-if="form.codigo_barra" class="barcode-preview-box mt-2">
                                        <svg ref="barcodeSvgRef" class="barcode-preview-svg" />
                                        <div v-if="barcodePreviewError" class="form-text text-danger">{{ barcodePreviewError }}</div>
                                    </div>
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

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-medida">Unidad de medida *</label>
                                    <select id="p-medida" v-model="form.unidad_medida_id" class="form-select" required>
                                        <option :value="null" disabled>Seleccione unidad</option>
                                        <option
                                            v-for="medida in medidasActivas"
                                            :key="medida.id"
                                            :value="medida.id"
                                        >
                                            {{ medida.nombre }} ({{ medida.abreviatura }})
                                        </option>
                                    </select>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-stock-minimo">Stock minimo</label>
                                    <input
                                        id="p-stock-minimo"
                                        v-model.number="form.stock_minimo"
                                        type="number"
                                        step="0.0001"
                                        min="0"
                                        class="form-control"
                                    >
                                    <div class="form-text">Alerta cuando el inventario baje de este valor.</div>
                                </div>

                                <div class="col-12 col-sm-6">
                                    <div class="form-check form-switch mt-sm-2 pt-sm-1">
                                        <input
                                            id="p-control-vencimiento"
                                            v-model="form.control_vencimiento"
                                            type="checkbox"
                                            class="form-check-input"
                                        >
                                        <label class="form-check-label" for="p-control-vencimiento">Controla vencimiento</label>
                                        <div class="form-text">Activa el seguimiento de lotes por fecha de caducidad.</div>
                                    </div>
                                </div>

                                <div v-if="form.control_vencimiento" class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="p-dias-alerta">Alertar vencimiento (dias antes)</label>
                                    <input
                                        id="p-dias-alerta"
                                        v-model.number="form.dias_alerta_vencimiento"
                                        type="number"
                                        step="1"
                                        min="1"
                                        max="365"
                                        class="form-control"
                                    >
                                    <div class="form-text">Se generara una alerta cuando falten estos dias para que un lote venza.</div>
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
                                        <div class="form-text">Si lo desactivas, el producto dejara de mostrarse en Ventas y Compras, pero su historial se conserva.</div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="saving">
                                <FontAwesomeIcon icon="fa-solid fa-floppy-disk" class="me-2" />
                                {{ saving ? 'Guardando...' : 'Guardar' }}
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <ModalConfirm
            ref="confirmModalRef"
            :title="confirmTitle"
            :message="confirmMessage"
            :hint="confirmHint"
            :confirm-text="confirmConfirmText"
            :loading="confirmLoading"
            @confirm="confirmAction"
        />
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import JsBarcode from 'jsbarcode';
import { computed, nextTick, onMounted, ref, watch } from 'vue';
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

import axios from '@/bootstrap';
import ModalConfirm from '@/components/components_ui/ModalConfirm.vue';

const productos = ref([]);
const categoriasActivas = ref([]);
const proveedoresActivos = ref([]);
const medidasActivas = ref([]);
const loading = ref(true);
const saving = ref(false);
const toggling = ref(false);
const deleting = ref(false);
const editingId = ref(null);
const selected = ref(null);
const formErrors = ref([]);
const confirmMode = ref('toggle');
const statusFilter = ref('todos');
const categoryFilter = ref('todas');
const nameFilter = ref('');

const formModalRef = ref(null);
const confirmModalRef = ref(null);
const barcodeSvgRef = ref(null);
const barcodePreviewError = ref('');

let formModal = null;

const emptyForm = () => ({
    categoria_id: null,
    proveedor_id: null,
    nombre: '',
    codigo_barra: '',
    detalle: '',
    palabras_clave: '',
    unidad_medida_id: medidasActivas.value[0]?.id ?? null,
    stock_minimo: 0,
    control_vencimiento: false,
    dias_alerta_vencimiento: 15,
    activo: true,
});

const form = ref(emptyForm());

const normalizedBarcode = computed(() => String(form.value.codigo_barra || '').trim());
const selectedCategoriaOption = computed({
    get: () => categoriasActivas.value.find((item) => item.id === form.value.categoria_id) ?? null,
    set: (option) => {
        form.value.categoria_id = option?.id ?? null;
    },
});
const selectedProveedorOption = computed({
    get: () => proveedoresActivos.value.find((item) => item.id === form.value.proveedor_id) ?? null,
    set: (option) => {
        form.value.proveedor_id = option?.id ?? null;
    },
});

onMounted(async () => {
    formModal = new Modal(formModalRef.value);
    await Promise.all([loadProductos(), loadCategorias(), loadProveedores(), loadMedidas()]);
});

watch(
    () => form.value.codigo_barra,
    async () => {
        await nextTick();
        renderBarcodePreview();
    }
);

const confirmTitle = computed(() => (confirmMode.value === 'delete' ? 'Eliminar producto' : `${selected.value?.activo ? 'Desactivar' : 'Activar'} producto`));
const confirmMessage = computed(() => {
    if (confirmMode.value === 'delete') {
        return `¿Eliminar el producto <strong>${selected.value?.nombre ?? ''}</strong>?`;
    }

    return `¿Desea ${selected.value?.activo ? 'desactivar' : 'activar'} el producto <strong>${selected.value?.nombre ?? ''}</strong>?`;
});
const confirmHint = computed(() => {
    if (confirmMode.value === 'delete') {
        return 'Esta accion no se puede deshacer.';
    }

    if (selected.value?.activo) {
        return 'Al desactivarlo, dejara de mostrarse en Ventas y Compras hasta que vuelva a activarse.';
    }

    return 'Al activarlo, volvera a mostrarse en Ventas y Compras.';
});
const confirmConfirmText = computed(() => (confirmMode.value === 'delete' ? 'Eliminar' : 'Confirmar'));
const confirmLoading = computed(() => (confirmMode.value === 'delete' ? deleting.value : toggling.value));
const actionLocked = computed(() => loading.value || saving.value || toggling.value || deleting.value);
const categoryFilterOptions = computed(() => {
    const map = new Map();

    for (const item of productos.value) {
        if (!item?.categoria?.id) continue;
        if (!map.has(item.categoria.id)) {
            map.set(item.categoria.id, {
                id: item.categoria.id,
                nombre: item.categoria.nombre,
            });
        }
    }

    return Array.from(map.values()).sort((a, b) => String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es', { sensitivity: 'base' }));
});
const displayedProductos = computed(() => {
    const query = String(nameFilter.value ?? '').trim().toLowerCase();

    return [...productos.value]
        .filter((item) => {
            if (statusFilter.value === 'todos') return true;
            if (statusFilter.value === 'activo') return Boolean(item.activo);
            if (statusFilter.value === 'inactivo') return !Boolean(item.activo);
            return true;
        })
        .filter((item) => {
            if (categoryFilter.value === 'todas') return true;
            if (categoryFilter.value === 'sin_categoria') return !item.categoria_id;

            return String(item.categoria_id ?? '') === categoryFilter.value;
        })
        .filter((item) => {
            if (!query) return true;

            return String(item.nombre ?? '').toLowerCase().includes(query);
        })
        .sort((a, b) => String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es', { sensitivity: 'base' }));
});

async function loadProductos() {
    loading.value = true;
    try {
        const { data } = await axios.get('/productos/get');
        productos.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadCategorias() {
    const { data } = await axios.get('/categorias/get?solo_activas=1');
    categoriasActivas.value = data.data;
}

async function loadProveedores() {
    const { data } = await axios.get('/proveedores/get');
    proveedoresActivos.value = (data.data ?? []).filter((item) => item.activo);
}

async function loadMedidas() {
    const { data } = await axios.get('/medidas/get');
    medidasActivas.value = data.data ?? [];
}

function openCreate() {
    editingId.value = null;
    form.value = emptyForm();
    barcodePreviewError.value = '';
    formErrors.value = [];
    formModal.show();
}

function openEdit(prod) {
    editingId.value = prod.id;
    form.value = {
        categoria_id:         prod.categoria_id ?? null,
        proveedor_id:         prod.proveedor_id ?? null,
        nombre:               prod.nombre,
        codigo_barra:         prod.codigo_barra ?? '',
        detalle:              prod.detalle ?? '',
        palabras_clave:       prod.palabras_clave ?? '',
        unidad_medida_id:     prod.unidad_medida_id ?? null,
        stock_minimo:             prod.stock_minimo ?? 0,
        control_vencimiento:      prod.control_vencimiento ?? false,
        dias_alerta_vencimiento:  prod.dias_alerta_vencimiento ?? 15,
        activo:                   prod.activo,
    };
    barcodePreviewError.value = '';
    formErrors.value = [];
    formModal.show();
}

function renderBarcodePreview() {
    const svg = barcodeSvgRef.value;
    if (!svg) return;

    const value = normalizedBarcode.value;
    if (!value) {
        barcodePreviewError.value = '';
        svg.innerHTML = '';
        return;
    }

    try {
        JsBarcode(svg, value, {
            format: 'CODE128',
            displayValue: true,
            fontSize: 12,
            height: 44,
            margin: 0,
        });
        barcodePreviewError.value = '';
    } catch {
        barcodePreviewError.value = 'No se pudo generar vista previa para este codigo.';
        svg.innerHTML = '';
    }
}

function openToggle(prod) {
    selected.value = prod;
    confirmMode.value = 'toggle';
    confirmModalRef.value?.open();
}

function openDelete(prod) {
    selected.value = prod;
    confirmMode.value = 'delete';
    confirmModalRef.value?.open();
}

async function confirmAction() {
    if (confirmMode.value === 'delete') {
        await confirmDelete();
        return;
    }

    await confirmToggle();
}

async function save() {
    saving.value = true;
    formErrors.value = [];
    try {
        const payload = {
            ...form.value,
            codigo_barra: form.value.codigo_barra || null,
            proveedor_id: form.value.proveedor_id || null,
        };

        if (editingId.value) {
            const { data } = await axios.put(`/productos/update/${editingId.value}`, payload);
            const idx = productos.value.findIndex((p) => p.id === editingId.value);
            if (idx !== -1) productos.value[idx] = data.data;
        } else {
            const { data } = await axios.post('/productos/store', payload);
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
        const { data } = await axios.patch(`/productos/toggle/${selected.value.id}`);
        const idx = productos.value.findIndex((p) => p.id === selected.value.id);
        if (idx !== -1) productos.value[idx] = { ...productos.value[idx], activo: data.data.activo };
        confirmModalRef.value?.close();
    } finally {
        toggling.value = false;
    }
}

async function confirmDelete() {
    deleting.value = true;
    try {
        await axios.delete(`/productos/destroy/${selected.value.id}`);
        productos.value = productos.value.filter((p) => p.id !== selected.value.id);
        confirmModalRef.value?.close();
    } finally {
        deleting.value = false;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('es-GT');
}
</script>

<style scoped>
.barcode-preview-box {
    border: 1px dashed #cfd4dc;
    border-radius: 0.5rem;
    background: #fff;
    padding: 0.5rem;
}

.barcode-preview-svg {
    display: block;
    width: 100%;
    max-height: 64px;
}
</style>
