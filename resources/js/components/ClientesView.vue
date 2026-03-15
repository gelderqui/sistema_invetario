<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0">Clientes</h2>
      <button class="btn btn-brand" :disabled="actionLocked" @click="openCreate">
        <FontAwesomeIcon icon="fa-solid fa-plus" class="me-2" />
        Nuevo
      </button>
    </div>

    <div v-if="loading" class="text-center py-5">
      <p class="text-body-secondary mb-0">Cargando información...</p>
    </div>

    <div v-else class="card border-0 shadow-sm">
      <div class="table-responsive">
        <table class="table table-hover align-middle mb-0">
          <thead class="thead-brand">
            <tr>
              <th>Nombre</th>
              <th>NIT</th>
              <th>Telefono</th>
              <th>Email</th>
              <th>Estado</th>
              <th>Creado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!clientes.length">
              <td colspan="7" class="text-center text-body-secondary py-4">Sin registros</td>
            </tr>
            <tr v-for="c in clientes" :key="c.id">
              <td class="fw-semibold">{{ c.nombre }}</td>
              <td>{{ c.nit || '-' }}</td>
              <td>{{ c.telefono || '-' }}</td>
              <td>{{ c.email || '-' }}</td>
              <td>
                <span class="badge" :class="c.activo ? 'text-bg-success' : 'text-bg-secondary'">
                  {{ c.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="text-body-secondary small">{{ formatDate(c.created_at) }}</td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn btn-sm btn-action-brand" title="Editar" :disabled="actionLocked" @click="openEdit(c)">
                    <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                  </button>
                  <button
                    class="btn btn-sm btn-action-brand"
                    :title="c.activo ? 'Desactivar' : 'Activar'"
                    :disabled="actionLocked"
                    @click="openToggle(c)"
                  >
                    <FontAwesomeIcon
                      :icon="c.activo ? 'fa-solid fa-ban' : 'fa-solid fa-check'"
                      :class="c.activo ? 'icon-action-disable' : 'icon-action-enable'"
                    />
                  </button>
                  <button class="btn btn-sm btn-action-brand" title="Eliminar" :disabled="actionLocked" @click="openDelete(c)">
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
            <h5 class="modal-title">{{ editingId ? 'Editar cliente' : 'Nuevo cliente' }}</h5>
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
                <label class="form-label fw-semibold">NIT</label>
                <input v-model="form.nit" type="text" class="form-control" placeholder="CF, 1234567-8, etc.">
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
                <input id="cli-activo" v-model="form.activo" type="checkbox" class="form-check-input">
                <label class="form-check-label" for="cli-activo">Activo</label>
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

const clientes = ref([]);
const loading = ref(true);
const saving = ref(false);
const toggling = ref(false);
const deleting = ref(false);
const editingId = ref(null);
const selected = ref(null);
const formErrors = ref([]);
const confirmMode = ref('toggle');

const formModalRef = ref(null);
const confirmModalRef = ref(null);

let formModal = null;

const emptyForm = () => ({
  nombre: '',
  nit: 'CF',
  email: '',
  telefono: '',
  direccion: '',
  activo: true,
});

const form = ref(emptyForm());

onMounted(async () => {
  formModal = new Modal(formModalRef.value);
  await loadClientes();
});

const confirmTitle = computed(() => (confirmMode.value === 'delete' ? 'Eliminar cliente' : 'Confirmar estado'));
const confirmMessage = computed(() => {
  if (confirmMode.value === 'delete') return `¿Eliminar a <strong>${selected.value?.nombre ?? ''}</strong>?`;
  return `¿Cambiar estado del cliente <strong>${selected.value?.nombre ?? ''}</strong>?`;
});
const confirmConfirmText = computed(() => (confirmMode.value === 'delete' ? 'Eliminar' : 'Confirmar'));
const confirmLoading = computed(() => (confirmMode.value === 'delete' ? deleting.value : toggling.value));
const actionLocked = computed(() => loading.value || saving.value || toggling.value || deleting.value);

async function loadClientes() {
  loading.value = true;
  try {
    const { data } = await axios.get('/clientes/get');
    clientes.value = data.data;
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
    nit: item.nit ?? '',
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
  confirmModalRef.value?.open();
}

function openDelete(item) {
  selected.value = item;
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
      nit: form.value.nit || null,
      email: form.value.email || null,
      telefono: form.value.telefono || null,
      direccion: form.value.direccion || null,
    };

    if (editingId.value) {
      const { data } = await axios.put(`/clientes/update/${editingId.value}`, payload);
      const idx = clientes.value.findIndex((x) => x.id === editingId.value);
      if (idx !== -1) clientes.value[idx] = data.data;
    } else {
      const { data } = await axios.post('/clientes/store', payload);
      clientes.value.push(data.data);
      clientes.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
    }

    formModal.hide();
  } catch (error) {
    const errors = error.response?.data?.errors;
    formErrors.value = errors ? Object.values(errors).flat() : [error.response?.data?.message ?? 'Error al guardar.'];
  } finally {
    saving.value = false;
  }
}

async function confirmToggle() {
  toggling.value = true;
  try {
    const { data } = await axios.patch(`/clientes/toggle/${selected.value.id}`);
    const idx = clientes.value.findIndex((x) => x.id === selected.value.id);
    if (idx !== -1) clientes.value[idx].activo = data.data.activo;
    confirmModalRef.value?.close();
  } finally {
    toggling.value = false;
  }
}

async function confirmDelete() {
  deleting.value = true;
  try {
    await axios.delete(`/clientes/destroy/${selected.value.id}`);
    clientes.value = clientes.value.filter((x) => x.id !== selected.value.id);
    confirmModalRef.value?.close();
  } finally {
    deleting.value = false;
  }
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('es-GT');
}
</script>
