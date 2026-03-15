<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Usuarios</h2>
            <button class="btn btn-brand" :disabled="actionLocked" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-plus" class="me-2" />
                Nuevo
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Cargando información...</p>
        </div>

        <div v-else class="card border-0 shadow-sm">
            <div class="px-3 pt-3">
                <div class="d-flex align-items-center gap-2 flex-wrap">
                    <label class="form-label fw-semibold mb-0" for="filtro-estado-usuario">Filtrar estado:</label>
                    <select id="filtro-estado-usuario" v-model="statusFilter" class="form-select form-select-sm w-auto">
                        <option value="todos">Todos</option>
                        <option value="activo">Activo</option>
                        <option value="inactivo">Inactivo</option>
                        <option value="eliminado">Eliminado</option>
                    </select>

                    <label class="form-label fw-semibold mb-0 ms-sm-2" for="filtro-nombre-usuario">Nombre:</label>
                    <input
                        id="filtro-nombre-usuario"
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
                        <tr v-if="!displayedUsers.length">
                            <td colspan="7" class="text-center text-body-secondary py-4">Sin registros</td>
                        </tr>
                        <tr v-for="user in displayedUsers" :key="user.id">
                            <td class="fw-semibold">{{ user.username }}</td>
                            <td>{{ user.name }}</td>
                            <td>{{ user.email }}</td>
                            <td>{{ user.telefono ?? '—' }}</td>
                            <td>
                                <span v-if="user.role" class="badge text-bg-dark">{{ user.role.name }}</span>
                                <span v-else class="text-body-secondary small">Sin rol</span>
                            </td>
                            <td>
                                <span class="badge" :class="estadoBadgeClass(user)">
                                    {{ estadoLabel(user) }}
                                </span>
                            </td>
                            <td>
                                <button
                                    class="btn btn-sm btn-action-brand"
                                    :disabled="actionLocked || user.deleted_at || esAdminProtegido(user)"
                                    :title="user.activo ? 'Desactivar' : 'Activar'"
                                    @click="openToggleUserStatus(user)"
                                >
                                    <FontAwesomeIcon :icon="user.activo ? 'fa-solid fa-toggle-on' : 'fa-solid fa-toggle-off'" class="icon-action-edit" />
                                </button>
                                <button class="btn btn-sm btn-action-brand ms-2" :disabled="actionLocked || user.deleted_at" @click="openEdit(user)">
                                    <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                                </button>
                                <button
                                    class="btn btn-sm btn-action-brand ms-2"
                                    :disabled="actionLocked || user.deleted_at || esAdminProtegido(user)"
                                    @click="removeUser(user)"
                                >
                                    <FontAwesomeIcon icon="fa-solid fa-trash" class="icon-action-delete" />
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
                    <div class="modal-header modal-header-brand">
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
                                        v-if="!isEditing"
                                        id="u-username"
                                        v-model="form.username"
                                        type="text"
                                        class="form-control"
                                        required
                                        autocomplete="off"
                                    >
                                    <div v-else class="form-control bg-light-subtle">
                                        {{ form.username }}
                                    </div>
                                    <div v-if="isEditing" class="form-text">El nombre de usuario no se puede editar.</div>
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
                                    <label class="form-label fw-semibold" for="u-role">Rol</label>
                                    <select id="u-role" v-model="form.role_id" class="form-select">
                                        <option :value="null">— Sin rol —</option>
                                        <option v-for="role in catalogs.roles" :key="role.id" :value="role.id">
                                            {{ role.name }}
                                        </option>
                                    </select>
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
            title="Eliminar usuario"
            :message="confirmMessage"
            confirm-text="Eliminar"
            :loading="deleting"
            :error-message="confirmError"
            @confirm="confirmDelete"
        />

        <ModalConfirm
            ref="deactivateModalRef"
            title="Desactivar usuario"
            :message="deactivateMessage"
            hint="Mientras el usuario este inactivo no podra iniciar sesion."
            confirm-text="Desactivar"
            :loading="saving"
            @confirm="confirmDeactivateAndSave"
        />

        <ModalConfirm
            ref="toggleUserModalRef"
            :title="toggleUserTitle"
            :message="toggleUserMessage"
            :hint="toggleUserHint"
            :confirm-text="toggleUserConfirmText"
            :loading="saving"
            :error-message="toggleUserError"
            @confirm="confirmToggleUserStatus"
        />
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { computed, onMounted, ref } from 'vue';

import axios from '@/bootstrap';
import ModalConfirm from '@/components/components_ui/ModalConfirm.vue';

const users = ref([]);
const catalogs = ref({ roles: [] });
const loading = ref(true);
const saving = ref(false);
const deleting = ref(false);
const modalErrors = ref([]);
const editingId = ref(null);
const modalRef = ref(null);
const confirmModalRef = ref(null);
const deactivateModalRef = ref(null);
const toggleUserModalRef = ref(null);
const selectedUser = ref(null);
const selectedUserForToggle = ref(null);
const confirmError = ref('');
const toggleUserError = ref('');
const statusFilter = ref('todos');
const nameFilter = ref('');

let bsModal = null;

const emptyForm = () => ({
    username: '',
    name: '',
    email: '',
    telefono: '',
    password: '',
    activo: true,
    role_id: null,
});

const form = ref(emptyForm());
const actionLocked = computed(() => loading.value || saving.value || deleting.value);
const isEditing = computed(() => editingId.value !== null);
const confirmMessage = computed(() => {
    const username = selectedUser.value?.username ?? '';
    return `El usuario <strong>${username}</strong> sera eliminado de forma logica. Quedara con estado <strong>Eliminado</strong>, no podra iniciar sesion y no podra activarse nuevamente.`;
});
const deactivateMessage = computed(() => {
    const username = form.value?.username ?? '';
    return `¿Deseas desactivar al usuario <strong>${username}</strong>? Mientras este inactivo no podra iniciar sesion.`;
});
const toggleUserTitle = computed(() => selectedUserForToggle.value?.activo ? 'Desactivar usuario' : 'Activar usuario');
const toggleUserConfirmText = computed(() => selectedUserForToggle.value?.activo ? 'Desactivar' : 'Activar');
const toggleUserMessage = computed(() => {
    if (!selectedUserForToggle.value) return '';
    const accion = selectedUserForToggle.value.activo ? 'desactivar' : 'activar';
    return `¿Deseas ${accion} al usuario <strong>${selectedUserForToggle.value.username}</strong>?`;
});
const toggleUserHint = computed(() => {
    if (!selectedUserForToggle.value) return '';
    if (selectedUserForToggle.value.activo) {
        return 'Si desactivas este usuario, no podra iniciar sesion hasta volver a activarlo.';
    }

    return 'Si activas este usuario, podra iniciar sesion nuevamente.';
});
const displayedUsers = computed(() => {
    const query = String(nameFilter.value ?? '').trim().toLowerCase();

    return [...users.value]
        .sort((a, b) => {
            const estadoDiff = userStatusOrder(a) - userStatusOrder(b);
            if (estadoDiff !== 0) return estadoDiff;

            return String(a.name ?? '').localeCompare(String(b.name ?? ''), 'es', { sensitivity: 'base' });
        })
        .filter((user) => {
            if (statusFilter.value === 'todos') return true;

            if (statusFilter.value === 'eliminado') return Boolean(user.deleted_at);
            if (statusFilter.value === 'activo') return !user.deleted_at && Boolean(user.activo);
            if (statusFilter.value === 'inactivo') return !user.deleted_at && !Boolean(user.activo);

            return true;
        })
        .filter((user) => {
            if (!query) return true;

            return String(user.name ?? '').toLowerCase().includes(query);
        });
});

onMounted(async () => {
    bsModal = new Modal(modalRef.value);
    await Promise.all([loadUsers(), loadCatalogs()]);
});

async function loadUsers() {
    loading.value = true;

    try {
        const { data } = await axios.get('/usuarios/get');
        users.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadCatalogs() {
    const { data } = await axios.get('/usuarios/get/catalogs');
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
        role_id: user.role?.id ?? null,
    };
    modalErrors.value = [];
    bsModal.show();
}

async function save() {
    if (shouldConfirmDeactivation()) {
        deactivateModalRef.value?.open();
        return;
    }

    await persistUser();
}

async function persistUser() {
    saving.value = true;
    modalErrors.value = [];

    try {
        if (editingId.value) {
            const { data } = await axios.put(`/usuarios/update/${editingId.value}`, form.value);
            const index = users.value.findIndex((u) => u.id === editingId.value);
            if (index !== -1) {
                users.value[index] = data.data;
            }
        } else {
            const { data } = await axios.post('/usuarios/store', form.value);
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

async function confirmDeactivateAndSave() {
    await persistUser();
    deactivateModalRef.value?.close();
}

function openToggleUserStatus(user) {
    if (!user?.id || user.deleted_at || esAdminProtegido(user)) return;

    selectedUserForToggle.value = user;
    toggleUserError.value = '';
    toggleUserModalRef.value?.open();
}

async function confirmToggleUserStatus() {
    if (!selectedUserForToggle.value?.id) return;

    saving.value = true;
    toggleUserError.value = '';

    const target = selectedUserForToggle.value;

    try {
        const payload = {
            name: target.name,
            email: target.email,
            telefono: target.telefono ?? '',
            password: '',
            activo: !target.activo,
            role_id: target.role?.id ?? null,
        };

        const { data } = await axios.put(`/usuarios/update/${target.id}`, payload);
        const index = users.value.findIndex((u) => u.id === target.id);
        if (index !== -1) {
            users.value[index] = data.data;
        }

        toggleUserModalRef.value?.close();
    } catch (error) {
        const serverErrors = error.response?.data?.errors;
        if (serverErrors && typeof serverErrors === 'object') {
            toggleUserError.value = Object.values(serverErrors).flat().join(' ');
        } else {
            toggleUserError.value = error.response?.data?.message ?? 'No se pudo actualizar el estado del usuario.';
        }
    } finally {
        saving.value = false;
    }
}

function shouldConfirmDeactivation() {
    if (!editingId.value || form.value.activo) return false;

    const currentUser = users.value.find((u) => u.id === editingId.value);
    if (!currentUser || currentUser.deleted_at) return false;

    return Boolean(currentUser.activo);
}

function removeUser(user) {
    if (!user?.id || user.deleted_at || esAdminProtegido(user)) return;

    selectedUser.value = user;
    confirmError.value = '';
    confirmModalRef.value?.open();
}

async function confirmDelete() {
    if (!selectedUser.value?.id) return;

    deleting.value = true;
    confirmError.value = '';
    try {
        await axios.delete(`/usuarios/destroy/${selectedUser.value.id}`);
        await loadUsers();
        confirmModalRef.value?.close();
    } catch (error) {
        const serverErrors = error.response?.data?.errors;
        if (serverErrors && typeof serverErrors === 'object') {
            confirmError.value = Object.values(serverErrors).flat().join(' ');
        } else {
            confirmError.value = error.response?.data?.message ?? 'No se pudo eliminar el usuario.';
        }
    } finally {
        deleting.value = false;
    }
}

function estadoLabel(user) {
    if (user.deleted_at) return 'Eliminado';
    return user.activo ? 'Activo' : 'Inactivo';
}

function estadoBadgeClass(user) {
    if (user.deleted_at) return 'text-bg-danger';
    return user.activo ? 'text-bg-success' : 'text-bg-secondary';
}

function userStatusOrder(user) {
    if (user.deleted_at) return 3;
    return user.activo ? 1 : 2;
}

function esAdminProtegido(user) {
    const username = String(user?.username ?? '').toLowerCase();
    const email = String(user?.email ?? '').toLowerCase();
    return username === 'admin' || email === 'admin@admin.local';
}
</script>
