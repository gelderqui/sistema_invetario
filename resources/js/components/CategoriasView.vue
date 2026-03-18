<template>
    <div>
        <!-- Encabezado -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Categorías</h2>
            <button class="btn btn-brand" :disabled="actionLocked" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-plus" class="me-2" />
                Nueva
            </button>
        </div>

        <!-- Tabla -->
        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Cargando información...</p>
        </div>

        <div v-else class="card border-0 shadow-sm">
            <div class="px-3 pt-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label class="form-label fw-semibold mb-0" for="filtro-estado-categoria">Filtrar estado:</label>
                    <select id="filtro-estado-categoria" v-model="statusFilter" class="form-select form-select-sm w-auto">
                        <option value="todos">Todos</option>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                    </select>

                    <label class="form-label fw-semibold mb-0 ms-sm-2" for="filtro-nombre-categoria">Nombre:</label>
                    <input
                        id="filtro-nombre-categoria"
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
                            <th>Descripcion</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!totalCategorias">
                            <td colspan="6" class="text-center text-body-secondary py-4">Sin registros</td>
                        </tr>
                        <tr v-for="cat in paginatedCategorias" :key="cat.id">
                            <td class="fw-semibold">{{ cat.nombre }}</td>
                            <td class="text-body-secondary">{{ cat.descripcion ?? '—' }}</td>
                            <td>
                                <span class="badge text-bg-light border">{{ cat.productos_count }}</span>
                            </td>
                            <td>
                                <span
                                    class="badge"
                                    :class="cat.activo ? 'text-bg-success' : 'text-bg-secondary'"
                                >
                                    {{ cat.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-body-secondary small">{{ formatDate(cat.created_at) }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        title="Editar"
                                        :disabled="actionLocked"
                                        @click="openEdit(cat)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                                    </button>
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        :title="cat.activo ? 'Desactivar' : 'Activar'"
                                        :disabled="actionLocked"
                                        @click="openToggle(cat)"
                                    >
                                        <FontAwesomeIcon
                                            :icon="cat.activo ? 'fa-solid fa-ban' : 'fa-solid fa-check'"
                                            :class="cat.activo ? 'icon-action-disable' : 'icon-action-enable'"
                                        />
                                    </button>
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        title="Eliminar"
                                        :disabled="actionLocked"
                                        @click="openDelete(cat)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-trash" class="icon-action-delete" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination
                v-model:page="pageCategorias"
                v-model:perPage="perPageCategorias"
                :total-items="totalCategorias"
            />
        </div>

        <!-- Modal crear / editar -->
        <div ref="formModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">
                            <FontAwesomeIcon :icon="editingId ? 'fa-solid fa-pen-to-square' : 'fa-solid fa-plus'" class="me-2" />
                            {{ editingId ? 'Editar categoría' : 'Nueva categoría' }}
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

                            <div>
                                <label class="form-label fw-semibold" for="cat-nombre">Nombre *</label>
                                <input
                                    id="cat-nombre"
                                    v-model="form.nombre"
                                    type="text"
                                    class="form-control"
                                    required
                                    autocomplete="off"
                                >
                            </div>

                            <div>
                                <label class="form-label fw-semibold" for="cat-descripcion">Descripcion</label>
                                <input
                                    id="cat-descripcion"
                                    v-model="form.descripcion"
                                    type="text"
                                    class="form-control"
                                >
                            </div>

                            <div class="form-check form-switch">
                                <input
                                    id="cat-activo"
                                    v-model="form.activo"
                                    type="checkbox"
                                    class="form-check-input"
                                >
                                <label class="form-check-label" for="cat-activo">Activo</label>
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
            :error-message="confirmMode === 'delete' ? deleteError : ''"
            @confirm="confirmAction"
        />
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { computed, onMounted, ref } from 'vue';

import axios from '@/bootstrap';
import ModalConfirm from '@/components/components_ui/ModalConfirm.vue';
import TablePagination from '@/components/components_ui/TablePagination.vue';

const categorias = ref([]);
const loading = ref(true);
const saving = ref(false);
const toggling = ref(false);
const deleting = ref(false);
const editingId = ref(null);
const selected = ref(null);
const formErrors = ref([]);
const deleteError = ref('');
const confirmMode = ref('toggle');
const statusFilter = ref('todos');
const nameFilter = ref('');
const pageCategorias = ref(1);
const perPageCategorias = ref(10);

const formModalRef = ref(null);
const confirmModalRef = ref(null);

let formModal = null;

const emptyForm = () => ({ nombre: '', descripcion: '', activo: true });
const form = ref(emptyForm());

onMounted(async () => {
    formModal = new Modal(formModalRef.value);
    await loadCategorias();
});

const confirmTitle = computed(() => {
    if (confirmMode.value === 'delete') return 'Eliminar categoría';
    return `${selected.value?.activo ? 'Desactivar' : 'Activar'} categoría`;
});

const confirmMessage = computed(() => {
    if (confirmMode.value === 'delete') {
        return `¿Eliminar la categoría <strong>${selected.value?.nombre ?? ''}</strong>?`;
    }

    return `¿Desea ${selected.value?.activo ? 'desactivar' : 'activar'} la categoría <strong>${selected.value?.nombre ?? ''}</strong>?`;
});

const confirmHint = computed(() => (confirmMode.value === 'delete' ? 'Esta acción no se puede deshacer.' : ''));
const confirmConfirmText = computed(() => (confirmMode.value === 'delete' ? 'Eliminar' : 'Confirmar'));
const confirmLoading = computed(() => (confirmMode.value === 'delete' ? deleting.value : toggling.value));
const actionLocked = computed(() => loading.value || saving.value || toggling.value || deleting.value);
const displayedCategorias = computed(() => {
    const query = String(nameFilter.value ?? '').trim().toLowerCase();

    return [...categorias.value]
        .filter((item) => {
            if (statusFilter.value === 'todos') return true;
            if (statusFilter.value === 'activo') return Boolean(item.activo);
            if (statusFilter.value === 'inactivo') return !Boolean(item.activo);
            return true;
        })
        .filter((item) => {
            if (!query) return true;

            return String(item.nombre ?? '').toLowerCase().includes(query);
        })
        .sort((a, b) => String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es', { sensitivity: 'base' }));
});
const totalCategorias = computed(() => displayedCategorias.value.length);
const totalPagesCategorias = computed(() => Math.max(1, Math.ceil(totalCategorias.value / perPageCategorias.value)));
const safePageCategorias = computed(() => Math.min(Math.max(pageCategorias.value, 1), totalPagesCategorias.value));
const paginatedCategorias = computed(() => {
    const start = (safePageCategorias.value - 1) * perPageCategorias.value;
    return displayedCategorias.value.slice(start, start + perPageCategorias.value);
});

async function loadCategorias() {
    loading.value = true;
    try {
        const { data } = await axios.get('/categorias/get');
        categorias.value = data.data;
    } finally {
        loading.value = false;
    }
}

function openCreate() {
    editingId.value = null;
    form.value = emptyForm();
    formErrors.value = [];
    formModal.show();
}

function openEdit(cat) {
    editingId.value = cat.id;
    form.value = { nombre: cat.nombre, descripcion: cat.descripcion ?? '', activo: cat.activo };
    formErrors.value = [];
    formModal.show();
}

function openToggle(cat) {
    selected.value = cat;
    confirmMode.value = 'toggle';
    deleteError.value = '';
    confirmModalRef.value?.open();
}

function openDelete(cat) {
    selected.value = cat;
    confirmMode.value = 'delete';
    deleteError.value = '';
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
        if (editingId.value) {
            const { data } = await axios.put(`/categorias/update/${editingId.value}`, form.value);
            const idx = categorias.value.findIndex((c) => c.id === editingId.value);
            if (idx !== -1) categorias.value[idx] = { ...categorias.value[idx], ...data.data };
        } else {
            const { data } = await axios.post('/categorias/store', form.value);
            categorias.value.push({ ...data.data, productos_count: 0 });
            categorias.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
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
        const { data } = await axios.patch(`/categorias/toggle/${selected.value.id}`);
        const idx = categorias.value.findIndex((c) => c.id === selected.value.id);
        if (idx !== -1) categorias.value[idx] = { ...categorias.value[idx], activo: data.data.activo };
        confirmModalRef.value?.close();
    } finally {
        toggling.value = false;
    }
}

async function confirmDelete() {
    deleting.value = true;
    deleteError.value = '';
    try {
        await axios.delete(`/categorias/destroy/${selected.value.id}`);
        categorias.value = categorias.value.filter((c) => c.id !== selected.value.id);
        confirmModalRef.value?.close();
    } catch (error) {
        deleteError.value = error.response?.data?.message ?? 'Error al eliminar.';
    } finally {
        deleting.value = false;
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—';
    return new Date(dateStr).toLocaleDateString('es-GT');
}
</script>
