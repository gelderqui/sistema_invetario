<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Usuarios</h2>
            <button class="btn btn-dark" @click="openCreate">
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
                    <thead class="table-light">
                        <tr>
                            <th>Usuario</th>
                            <th>Nombre</th>
                            <th>Correo</th>
                            <th>Telefono</th>
                            <th>Roles</th>
                            <th>Estado</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!users.length">
                            <td colspan="7" class="text-center text-body-secondary py-4">Sin registros</td>
                        </tr>
                        <tr v-for="user in users" :key="user.id">
                            <td class="fw-semibold">{{ user.username }}</td>
                            <td>{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ user.telefono ?? '—' }}</td>
                            <td>
                                <span
                                    v-for="role in user.roles"
                                    :key="role.id"
                                    class="badge text-bg-dark me-1"
                                >
                                    {{ role.name }}
                                </span>
                                <span v-if="!user.roles?.length" class="text-body-secondary small">Sin rol</span>
                            </td>
                            <td>
                                <span class="badge" :class="user.activo ? 'text-bg-success' : 'text-bg-secondary'">
                                    {{ user.activo ? 'Activo' : 'Inactivo' }}
                                </span>
                            </td>
                            <td>
                                <button class="btn btn-sm btn-outline-secondary" @click="openEdit(user)">
                                    <FontAwesomeIcon icon="fa-solid fa-pencil" />
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Modal crear / editar -->
        <div ref="modalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog">
                <div class="modal-content">
                    <div class="modal-header">
                        <h5 class="modal-title">
                            <FontAwesomeIcon :icon="editingId ? 'fa-solid fa-user-pen' : 'fa-solid fa-user-plus'" class="me-2" />
                            {{ editingId ? 'Editar usuario' : 'Nuevo usuario' }}
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
                                    <label class="form-label fw-semibold" for="u-username">Usuario *</label>
                                    <input
                                        id="u-username"
                                        v-model="form.username"
                                        type="text"
                                        class="form-control"
                                        required
                                        autocomplete="off"
                                    >
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="u-name">Nombre completo *</label>
                                    <input
                                        id="u-name"
                                        v-model="form.name"
                                        type="text"
                                        class="form-control"
                                        required
                                    >
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="u-email">Correo *</label>
                                    <input
                                        id="u-email"
                                        v-model="form.email"
                                        type="email"
                                        class="form-control"
                                        required
                                    >
                                </div>

                                <div class="col-12 col-sm-6">
                                    <label class="form-label fw-semibold" for="u-telefono">Telefono</label>
                                    <input
                                        id="u-telefono"
                                        v-model="form.telefono"
                                        type="text"
                                        class="form-control"
                                    >
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold" for="u-password">
                                        Contrasena {{ editingId ? '(dejar vacio para no cambiar)' : '*' }}
                                    </label>
                                    <input
                                        id="u-password"
                                        v-model="form.password"
                                        type="password"
                                        class="form-control"
                                        :required="!editingId"
                                        autocomplete="new-password"
                                    >
                                </div>

                                <div class="col-12">
                                    <label class="form-label fw-semibold">Roles</label>
                                    <div class="d-flex flex-wrap gap-3">
                                        <div
                                            v-for="role in catalogs.roles"
                                            :key="role.id"
                                            class="form-check"
                                        >
                                            <input
                                                :id="`role-${role.id}`"
                                                v-model="form.role_ids"
                                                :value="role.id"
                                                type="checkbox"
                                                class="form-check-input"
                                            >
                                            <label :for="`role-${role.id}`" class="form-check-label">
                                                {{ role.name }}
                                            </label>
                                        </div>
                                    </div>
                                </div>

                                <div class="col-12">
                                    <div class="form-check form-switch">
                                        <input
                                            id="u-activo"
                                            v-model="form.activo"
                                            type="checkbox"
                                            class="form-check-input"
                                        >
                                        <label class="form-check-label" for="u-activo">Activo</label>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-dark" :disabled="saving">
                                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                                <FontAwesomeIcon v-else icon="fa-solid fa-floppy-disk" class="me-2" />
                                Guardar
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
import { onMounted, ref } from 'vue';

import axios from '@/bootstrap';

const users = ref([]);
const catalogs = ref({ roles: [] });
const loading = ref(true);
const saving = ref(false);
const modalErrors = ref([]);
const editingId = ref(null);
const modalRef = ref(null);

let bsModal = null;

const emptyForm = () => ({
    username: '',
    name: '',
    email: '',
    telefono: '',
    password: '',
    activo: true,
    role_ids: [],
});

const form = ref(emptyForm());

onMounted(async () => {
    bsModal = new Modal(modalRef.value);
    await Promise.all([loadUsers(), loadCatalogs()]);
});

async function loadUsers() {
    loading.value = true;

    try {
        const { data } = await axios.get('/admin/users');
        users.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadCatalogs() {
    const { data } = await axios.get('/admin/users/catalogs');
    catalogs.value = data;
}

function openCreate() {
    editingId.value = null;
    form.value = emptyForm();
    modalErrors.value = [];
    bsModal.show();
}

function openEdit(user) {
    editingId.value = user.id;
    form.value = {
        username: user.username,
        name: user.name,
        email: user.email,
        telefono: user.telefono ?? '',
        password: '',
        activo: user.activo,
        role_ids: user.roles?.map((r) => r.id) ?? [],
    };
    modalErrors.value = [];
    bsModal.show();
}

async function save() {
    saving.value = true;
    modalErrors.value = [];

    try {
        if (editingId.value) {
            const { data } = await axios.put(`/admin/users/${editingId.value}`, form.value);
            const index = users.value.findIndex((u) => u.id === editingId.value);
            if (index !== -1) {
                users.value[index] = data.data;
            }
        } else {
            const { data } = await axios.post('/admin/users', form.value);
            users.value.push(data.data);
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
</script>
