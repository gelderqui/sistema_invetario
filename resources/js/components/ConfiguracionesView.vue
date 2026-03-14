<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Configuraciones</h2>
        </div>

        <div v-if="loading" class="text-center py-5">
            <span class="spinner-border" />
        </div>

        <div v-else class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Codigo</th>
                            <th>Descripcion</th>
                            <th>Valor</th>
                            <th>Estado</th>
                            <th>Creado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!items.length">
                            <td colspan="6" class="text-center text-body-secondary py-4">Sin registros</td>
                        </tr>
                        <tr v-for="item in items" :key="item.id">
                            <td><code>{{ item.codigo }}</code></td>
                            <td>{{ item.descripcion || '—' }}</td>
                            <td class="text-break" style="max-width: 420px;">{{ item.value || '—' }}</td>
                            <td>
                                <span class="badge" :class="item.activo ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ item.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td class="text-body-secondary small">{{ formatDate(item.created_at) }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-action-brand" title="Editar" @click="openEdit(item)">
                                        <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                                    </button>
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        :title="item.activo ? 'Desactivar' : 'Activar'"
                                        @click="openToggle(item)"
                                    >
                                        <FontAwesomeIcon
                                            :icon="item.activo ? 'fa-solid fa-ban' : 'fa-solid fa-check'"
                                            :class="item.activo ? 'icon-action-disable' : 'icon-action-enable'"
                                        />
                                    </button>
                                    <button class="btn btn-sm btn-action-brand" title="Eliminar" @click="openDelete(item)">
                                        <FontAwesomeIcon icon="fa-solid fa-trash" class="icon-action-delete" />
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div ref="modalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Editar configuracion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>

                    <form novalidate @submit.prevent="save">
                        <div class="modal-body d-grid gap-3">
                            <div v-if="modalErrors.length" class="alert alert-danger py-2 mb-0">
                                <ul class="mb-0 ps-3">
                                    <li v-for="(e, i) in modalErrors" :key="i">{{ e }}</li>
                                </ul>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Codigo *</label>
                                <input v-model="form.codigo" type="text" class="form-control" required readonly>
                                <div class="form-text">El codigo no se puede modificar.</div>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Descripcion</label>
                                <textarea v-model="form.descripcion" rows="4" class="form-control" />
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Valor</label>
                                <input v-model="form.value" type="text" class="form-control" maxlength="255">
                            </div>

                            <div class="form-check form-switch">
                                <input id="cfg-activo" v-model="form.activo" type="checkbox" class="form-check-input">
                                <label class="form-check-label" for="cfg-activo">Activo</label>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                                Guardar
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <div ref="toggleModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Confirmar estado</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">
                        ¿Cambiar estado de <strong>{{ selected?.codigo }}</strong>?
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-brand" :disabled="toggling" @click="confirmToggle">Confirmar</button>
                    </div>
                </div>
            </div>
        </div>

        <div ref="deleteModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Eliminar configuracion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">¿Eliminar <strong>{{ selected?.codigo }}</strong>?</div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                        <button type="button" class="btn btn-brand" :disabled="deleting" @click="confirmDelete">Eliminar</button>
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

const items = ref([]);
const loading = ref(true);
const saving = ref(false);
const toggling = ref(false);
const deleting = ref(false);
const editingId = ref(null);
const selected = ref(null);
const modalErrors = ref([]);

const modalRef = ref(null);
const toggleModalRef = ref(null);
const deleteModalRef = ref(null);

let bsModal = null;
let toggleModal = null;
let deleteModal = null;

const emptyForm = () => ({
    codigo: '',
    descripcion: '',
    value: '',
    activo: true,
});

const form = ref(emptyForm());

onMounted(async () => {
    bsModal = new Modal(modalRef.value);
    toggleModal = new Modal(toggleModalRef.value);
    deleteModal = new Modal(deleteModalRef.value);
    await loadItems();
});

async function loadItems() {
    loading.value = true;
    try {
        const { data } = await axios.get('/configuracion/configuraciones/get');
        items.value = data.data;
    } finally {
        loading.value = false;
    }
}

function openEdit(item) {
    editingId.value = item.id;
    form.value = {
        codigo: item.codigo,
        descripcion: item.descripcion ?? '',
        value: item.value ?? '',
        activo: item.activo,
    };
    modalErrors.value = [];
    bsModal.show();
}

function openToggle(item) {
    selected.value = item;
    toggleModal.show();
}

function openDelete(item) {
    selected.value = item;
    deleteModal.show();
}

async function save() {
    saving.value = true;
    modalErrors.value = [];

    try {
        const payload = {
            ...form.value,
            descripcion: form.value.descripcion || null,
            value: form.value.value || null,
        };

        if (editingId.value) {
            const { data } = await axios.put(`/configuracion/configuraciones/update/${editingId.value}`, payload);
            const index = items.value.findIndex((x) => x.id === editingId.value);
            if (index !== -1) items.value[index] = data.data;
        }

        bsModal.hide();
    } catch (error) {
        const serverErrors = error.response?.data?.errors;
        modalErrors.value = serverErrors
            ? Object.values(serverErrors).flat()
            : [error.response?.data?.message ?? 'Error al guardar.'];
    } finally {
        saving.value = false;
    }
}

async function confirmToggle() {
    toggling.value = true;
    try {
        const { data } = await axios.patch(`/configuracion/configuraciones/toggle/${selected.value.id}`);
        const index = items.value.findIndex((x) => x.id === selected.value.id);
        if (index !== -1) items.value[index].activo = data.data.activo;
        toggleModal.hide();
    } finally {
        toggling.value = false;
    }
}

async function confirmDelete() {
    deleting.value = true;
    try {
        await axios.delete(`/configuracion/configuraciones/destroy/${selected.value.id}`);
        items.value = items.value.filter((x) => x.id !== selected.value.id);
        deleteModal.hide();
    } finally {
        deleting.value = false;
    }
}

function formatDate(value) {
    if (!value) return '—';
    return new Date(value).toLocaleDateString('es-GT');
}
</script>
