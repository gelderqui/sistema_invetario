<template>
    <div>
        <!-- Encabezado -->
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Categorías</h2>
            <button class="btn btn-brand" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-plus" class="me-2" />
                Nueva
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
                            <th>Nombre</th>
                            <th>Descripcion</th>
                            <th>Productos</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!categorias.length">
                            <td colspan="6" class="text-center text-body-secondary py-4">Sin registros</td>
                        </tr>
                        <tr v-for="cat in categorias" :key="cat.id">
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
                                        @click="openEdit(cat)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                                    </button>
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        :title="cat.activo ? 'Desactivar' : 'Activar'"
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
                            {{ selected?.activo ? 'Desactivar' : 'Activar' }} categoría
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">
                        <p class="mb-0">
                            ¿Desea {{ selected?.activo ? 'desactivar' : 'activar' }} la categoría
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
                            Eliminar categoría
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">
                        <p class="mb-1">
                            ¿Eliminar la categoría <strong>{{ selected?.nombre }}</strong>?
                        </p>
                        <p class="small text-body-secondary mb-0">Esta acción no se puede deshacer.</p>

                        <div v-if="deleteError" class="alert alert-danger mt-3 py-2 mb-0 small">
                            {{ deleteError }}
                        </div>
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

const categorias = ref([]);
const loading = ref(true);
const saving = ref(false);
const toggling = ref(false);
const deleting = ref(false);
const editingId = ref(null);
const selected = ref(null);
const formErrors = ref([]);
const deleteError = ref('');

const formModalRef = ref(null);
const toggleModalRef = ref(null);
const deleteModalRef = ref(null);

let formModal = null;
let toggleModal = null;
let deleteModal = null;

const emptyForm = () => ({ nombre: '', descripcion: '', activo: true });
const form = ref(emptyForm());

onMounted(async () => {
    formModal = new Modal(formModalRef.value);
    toggleModal = new Modal(toggleModalRef.value);
    deleteModal = new Modal(deleteModalRef.value);
    await loadCategorias();
});

async function loadCategorias() {
    loading.value = true;
    try {
        const { data } = await axios.get('/catalogos/categorias/get');
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
    toggleModal.show();
}

function openDelete(cat) {
    selected.value = cat;
    deleteError.value = '';
    deleteModal.show();
}

async function save() {
    saving.value = true;
    formErrors.value = [];
    try {
        if (editingId.value) {
            const { data } = await axios.put(`/catalogos/categorias/update/${editingId.value}`, form.value);
            const idx = categorias.value.findIndex((c) => c.id === editingId.value);
            if (idx !== -1) categorias.value[idx] = { ...categorias.value[idx], ...data.data };
        } else {
            const { data } = await axios.post('/catalogos/categorias/store', form.value);
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
        const { data } = await axios.patch(`/catalogos/categorias/toggle/${selected.value.id}`);
        const idx = categorias.value.findIndex((c) => c.id === selected.value.id);
        if (idx !== -1) categorias.value[idx] = { ...categorias.value[idx], activo: data.data.activo };
        toggleModal.hide();
    } finally {
        toggling.value = false;
    }
}

async function confirmDelete() {
    deleting.value = true;
    deleteError.value = '';
    try {
        await axios.delete(`/catalogos/categorias/destroy/${selected.value.id}`);
        categorias.value = categorias.value.filter((c) => c.id !== selected.value.id);
        deleteModal.hide();
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
