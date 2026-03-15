<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0">Proveedores</h2>
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
          <label class="form-label fw-semibold mb-0" for="filtro-estado-proveedor">Filtrar estado:</label>
          <select id="filtro-estado-proveedor" v-model="statusFilter" class="form-select form-select-sm w-auto">
            <option value="todos">Todos</option>
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
          </select>

          <label class="form-label fw-semibold mb-0 ms-sm-2" for="filtro-nombre-proveedor">Nombre:</label>
          <input
            id="filtro-nombre-proveedor"
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
              <th>Contacto</th>
              <th>Telefono</th>
              <th>Email</th>
              <th>Estado</th>
              <th>Creado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!displayedProveedores.length">
              <td colspan="7" class="text-center text-body-secondary py-4">Sin registros</td>
            </tr>
            <tr v-for="p in displayedProveedores" :key="p.id">
              <td class="fw-semibold">{{ p.nombre }}</td>
              <td>{{ p.contacto || '-' }}</td>
              <td>{{ p.telefono || '-' }}</td>
              <td>{{ p.email || '-' }}</td>
              <td>
                <span class="badge" :class="p.activo ? 'text-bg-success' : 'text-bg-secondary'">
                  {{ p.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="text-body-secondary small">{{ formatDate(p.created_at) }}</td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn btn-sm btn-action-brand" title="Editar" :disabled="actionLocked" @click="openEdit(p)">
                    <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                  </button>
                  <button
                    class="btn btn-sm btn-action-brand"
                    :title="p.activo ? 'Desactivar' : 'Activar'"
                    :disabled="actionLocked"
                    @click="openToggle(p)"
                  >
                    <FontAwesomeIcon
                      :icon="p.activo ? 'fa-solid fa-ban' : 'fa-solid fa-check'"
                      :class="p.activo ? 'icon-action-disable' : 'icon-action-enable'"
                    />
                  </button>
                  <button class="btn btn-sm btn-action-brand" title="Eliminar" :disabled="actionLocked" @click="openDelete(p)">
                    <FontAwesomeIcon icon="fa-solid fa-trash" class="icon-action-delete" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div ref="formModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header modal-header-brand">
            <h5 class="modal-title">{{ editingId ? 'Editar proveedor' : 'Nuevo proveedor' }}</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" />
          </div>

          <form novalidate @submit.prevent="save">
            <div class="modal-body d-grid gap-3">
              <FormErrors :errors="formErrors" />

              <div>
                <label class="form-label fw-semibold">Nombre *</label>
                <input v-model="form.nombre" type="text" class="form-control" required>
              </div>

              <div>
                <label class="form-label fw-semibold">Contacto</label>
                <input v-model="form.contacto" type="text" class="form-control">
              </div>

              <div class="row g-3">
                <div class="col-12 col-sm-6">
                  <label class="form-label fw-semibold">Telefono</label>
                  <input v-model="form.telefono" type="text" class="form-control">
                </div>
                <div class="col-12 col-sm-6">
                  <label class="form-label fw-semibold">Email</label>
                  <input v-model="form.email" type="email" class="form-control">
                </div>
              </div>

              <div>
                <label class="form-label fw-semibold">Direccion</label>
                <input v-model="form.direccion" type="text" class="form-control">
              </div>

              <div class="form-check form-switch">
                <input id="prov-activo" v-model="form.activo" type="checkbox" class="form-check-input">
                <label class="form-check-label" for="prov-activo">Activo</label>
              </div>
            </div>

            <div class="modal-footer">
              <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
              <button type="submit" class="btn btn-brand" :disabled="saving">
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
      :confirm-text="confirmConfirmText"
      :loading="confirmLoading"
      :error-message="confirmMode === 'delete' ? confirmError : ''"
      @confirm="confirmAction"
    />
  </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { computed, onMounted, ref } from 'vue';

import axios from '@/bootstrap';
import ModalConfirm from '@/components/components_ui/ModalConfirm.vue';
import FormErrors from '@/components/FormErrors.vue';

const proveedores = ref([]);
const loading = ref(true);
const saving = ref(false);
const toggling = ref(false);
const deleting = ref(false);
const editingId = ref(null);
const selected = ref(null);
const formErrors = ref([]);
const confirmMode = ref('toggle');
const confirmError = ref('');
const statusFilter = ref('todos');
const nameFilter = ref('');

const formModalRef = ref(null);
const confirmModalRef = ref(null);

let formModal = null;

const emptyForm = () => ({
  nombre: '',
  contacto: '',
  email: '',
  telefono: '',
  direccion: '',
  activo: true,
});

const form = ref(emptyForm());

onMounted(async () => {
  formModal = new Modal(formModalRef.value);
  await loadProveedores();
});

const confirmTitle = computed(() => (confirmMode.value === 'delete' ? 'Eliminar proveedor' : 'Confirmar estado'));
const confirmMessage = computed(() => {
  if (confirmMode.value === 'delete') return `¿Eliminar a <strong>${selected.value?.nombre ?? ''}</strong>?`;
  return `¿Cambiar estado del proveedor <strong>${selected.value?.nombre ?? ''}</strong>?`;
});
const confirmConfirmText = computed(() => (confirmMode.value === 'delete' ? 'Eliminar' : 'Confirmar'));
const confirmLoading = computed(() => (confirmMode.value === 'delete' ? deleting.value : toggling.value));
const actionLocked = computed(() => loading.value || saving.value || toggling.value || deleting.value);
const displayedProveedores = computed(() => {
  const query = String(nameFilter.value ?? '').trim().toLowerCase();

  return [...proveedores.value]
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

async function loadProveedores() {
  loading.value = true;
  try {
    const { data } = await axios.get('/catalogos/proveedores/get');
    proveedores.value = data.data;
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

function openEdit(item) {
  editingId.value = item.id;
  form.value = {
    nombre: item.nombre,
    contacto: item.contacto ?? '',
    email: item.email ?? '',
    telefono: item.telefono ?? '',
    direccion: item.direccion ?? '',
    activo: item.activo,
  };
  formErrors.value = [];
  formModal.show();
}

function openToggle(item) {
  selected.value = item;
  confirmMode.value = 'toggle';
  confirmError.value = '';
  confirmModalRef.value?.open();
}

function openDelete(item) {
  selected.value = item;
  confirmMode.value = 'delete';
  confirmError.value = '';
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
      contacto: form.value.contacto || null,
      email: form.value.email || null,
      telefono: form.value.telefono || null,
      direccion: form.value.direccion || null,
    };

    if (editingId.value) {
      const { data } = await axios.put(`/catalogos/proveedores/update/${editingId.value}`, payload);
      const idx = proveedores.value.findIndex((x) => x.id === editingId.value);
      if (idx !== -1) proveedores.value[idx] = data.data;
    } else {
      const { data } = await axios.post('/catalogos/proveedores/store', payload);
      proveedores.value.push(data.data);
    }

    formModal.hide();
  } catch (error) {
    const errors = error.response?.data?.errors;
    formErrors.value = errors ? Object.values(errors).flat() : [error.response?.data?.message ?? 'Error al guardar proveedor.'];
  } finally {
    saving.value = false;
  }
}

async function confirmToggle() {
  toggling.value = true;
  try {
    const { data } = await axios.patch(`/catalogos/proveedores/toggle/${selected.value.id}`);
    const idx = proveedores.value.findIndex((x) => x.id === selected.value.id);
    if (idx !== -1) proveedores.value[idx] = { ...proveedores.value[idx], activo: data.data.activo };
    confirmModalRef.value?.close();
  } finally {
    toggling.value = false;
  }
}

async function confirmDelete() {
  deleting.value = true;
  try {
    await axios.delete(`/catalogos/proveedores/destroy/${selected.value.id}`);
    proveedores.value = proveedores.value.filter((x) => x.id !== selected.value.id);
    confirmModalRef.value?.close();
  } catch (error) {
    confirmError.value = error.response?.data?.message ?? 'No se pudo eliminar el proveedor.';
  } finally {
    deleting.value = false;
  }
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('es-GT');
}
</script>
