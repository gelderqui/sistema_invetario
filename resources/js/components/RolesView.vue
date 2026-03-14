<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Roles</h2>
            <button class="btn btn-brand" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-plus" class="me-2" />
                Nuevo
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <span class="spinner-border" />
        </div>

        <div v-else class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Nombre</th>
                            <th>Codigo</th>
                            <th>Descripcion</th>
                            <th>Permisos</th>
                            <th>Usuarios</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!roles.length">
                            <td colspan="7" class="text-center text-body-secondary py-4">Sin registros</td>
                        </tr>
                        <tr v-for="role in roles" :key="role.id">
                            <td class="fw-semibold">{{ role.name }}</td>
                            <td><code>{{ role.code }}</code></td>
                            <td>{{ role.description ?? '—' }}</td>
                            <td>
                                <span class="badge text-bg-light border">
                                    {{ role.permissions?.length ?? 0 }} permisos
                                </span>
                            </td>
                            <td>
                                <span class="badge text-bg-light border">
                                    {{ role.users_count ?? 0 }} usuarios
                                </span>
                            </td>
                            <td>
                                <span class="badge" :class="role.activo ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ role.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        title="Editar"
                                        @click="openEdit(role)"
                                    >
                                        <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                                    </button>
                                    <button
                                        class="btn btn-sm btn-action-brand"
                                        :disabled="role.code === 'admin'"
                                        :title="role.code === 'admin' ? 'El rol admin no se puede eliminar' : 'Eliminar'"
                                        @click="role.code !== 'admin' && openDelete(role)"
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
        <div ref="modalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">
                            <FontAwesomeIcon :icon="editingId ? 'fa-solid fa-pen-to-square' : 'fa-solid fa-user-shield'" class="me-2" />
                            {{ editingId ? 'Editar rol' : 'Nuevo rol' }}
                        </h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>

                    <form novalidate @submit.prevent="save">
                        <div class="modal-body d-grid gap-3">
                            <div v-if="modalErrors.length" class="alert alert-danger py-2 mb-0">
                                <ul class="mb-0 ps-3">
                                    <li v-for="(e, i) in modalErrors" :key="i">{{ e }}</li>
                                </ul>
                            </div>

                            <div class="row g-3">
                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="r-name">Nombre *</label>
                                    <input
                                        id="r-name"
                                        v-model="form.name"
                                        type="text"
                                        class="form-control"
                                        required
                                    >
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="r-code">Codigo *</label>
                                    <input
                                        id="r-code"
                                        v-model="form.code"
                                        type="text"
                                        class="form-control"
                                        required
                                        placeholder="ej. supervisor"
                                    >
                                    <div class="form-text">Solo letras, numeros y guiones.</div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="r-desc">Descripcion</label>
                                    <input
                                        id="r-desc"
                                        v-model="form.description"
                                        type="text"
                                        class="form-control"
                                    >
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input
                                            id="r-activo"
                                            v-model="form.activo"
                                            type="checkbox"
                                            class="form-check-input"
                                        >
                                        <label class="form-check-label" for="r-activo">Activo</label>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Permisos</label>

                                    <div
                                        v-for="(group, module) in permissionsByModule"
                                        :key="module"
                                        class="mb-3"
                                    >
                                        <p class="text-uppercase small text-body-secondary fw-semibold mb-2 border-bottom pb-1">
                                            {{ module }}
                                        </p>
                                        <div class="d-flex flex-wrap gap-3">
                                            <div
                                                v-for="perm in group"
                                                :key="perm.id"
                                                class="form-check"
                                            >
                                                <input
                                                    :id="`perm-${perm.id}`"
                                                    v-model="form.permission_ids"
                                                    :value="perm.id"
                                                    type="checkbox"
                                                    class="form-check-input"
                                                >
                                                <label :for="`perm-${perm.id}`" class="form-check-label small">
                                                    {{ perm.name }}
                                                </label>
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="!allPermissions.length" class="text-body-secondary small">
                                        Cargando permisos...
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

        <div ref="deleteModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Eliminar rol</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>
                    <div class="modal-body">
                        ¿Eliminar el rol <strong>{{ selected?.name }}</strong>?
                    </div>
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
import { computed, onMounted, ref } from 'vue';

import axios from '@/bootstrap';

const roles = ref([]);
const allPermissions = ref([]);
const loading = ref(true);
const saving = ref(false);
const deleting = ref(false);
const modalErrors = ref([]);
const editingId = ref(null);
const selected = ref(null);
const modalRef = ref(null);
const deleteModalRef = ref(null);

let bsModal = null;
let deleteModal = null;

const emptyForm = () => ({
    name: '',
    code: '',
    description: '',
    activo: true,
    permission_ids: [],
});

const form = ref(emptyForm());

const permissionsByModule = computed(() =>
    allPermissions.value.reduce((acc, perm) => {
        const mod = perm.module ?? 'general';
        if (!acc[mod]) acc[mod] = [];
        acc[mod].push(perm);
        return acc;
    }, {})
);

onMounted(async () => {
    bsModal = new Modal(modalRef.value);
    deleteModal = new Modal(deleteModalRef.value);
    await Promise.all([loadRoles(), loadPermissions()]);
});

async function loadRoles() {
    loading.value = true;

    try {
        const { data } = await axios.get('/configuracion/roles/get');
        roles.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadPermissions() {
    const { data } = await axios.get('/configuracion/permissions/get');
    allPermissions.value = data.data;
}

function openCreate() {
    editingId.value = null;
    form.value = emptyForm();
    modalErrors.value = [];
    bsModal.show();
}

function openEdit(role) {
    editingId.value = role.id;
    form.value = {
        name: role.name,
        code: role.code,
        description: role.description ?? '',
        activo: role.activo,
        permission_ids: role.permissions?.map((p) => p.id) ?? [],
    };
    modalErrors.value = [];
    bsModal.show();
}

function openDelete(role) {
    selected.value = role;
    deleteModal.show();
}

async function save() {
    saving.value = true;
    modalErrors.value = [];

    try {
        if (editingId.value) {
            const { data } = await axios.put(`/configuracion/roles/update/${editingId.value}`, form.value);
            const index = roles.value.findIndex((r) => r.id === editingId.value);
            if (index !== -1) {
                roles.value[index] = data.data;
            }
        } else {
            const { data } = await axios.post('/configuracion/roles/store', form.value);
            roles.value.push(data.data);
        }

        bsModal.hide();
    } catch (error) {
        const serverErrors = error.response?.data?.errors;

        if (serverErrors && typeof serverErrors === 'object') {
            modalErrors.value = Object.values(serverErrors).flat();
        } else {
            modalErrors.value = [error.response?.data?.message ?? 'Error al guardar.'];
        }
    } finally {
        saving.value = false;
    }
}

async function confirmDelete() {
    deleting.value = true;
    try {
        await axios.delete(`/configuracion/roles/destroy/${selected.value.id}`);
        roles.value = roles.value.filter((r) => r.id !== selected.value.id);
        deleteModal.hide();
    } catch (error) {
        const serverErrors = error.response?.data?.errors;
        modalErrors.value = serverErrors
            ? Object.values(serverErrors).flat()
            : [error.response?.data?.message ?? 'No se pudo eliminar el rol.'];
    } finally {
        deleting.value = false;
    }
}
</script>
