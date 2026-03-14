<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0">Proveedores</h2>
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
              <th>Contacto</th>
              <th>Telefono</th>
              <th>Email</th>
              <th>Estado</th>
              <th>Creado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!proveedores.length">
              <td colspan="7" class="text-center text-body-secondary py-4">Sin registros</td>
            </tr>
            <tr v-for="p in proveedores" :key="p.id">
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
                  <button class="btn btn-sm btn-action-brand" title="Editar" @click="openEdit(p)">
                    <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                  </button>
                  <button
                    class="btn btn-sm btn-action-brand"
                    :title="p.activo ? 'Desactivar' : 'Activar'"
                    @click="openToggle(p)"
                  >
                    <FontAwesomeIcon
                      :icon="p.activo ? 'fa-solid fa-ban' : 'fa-solid fa-check'"
                      :class="p.activo ? 'icon-action-disable' : 'icon-action-enable'"
                    />
                  </button>
                  <button class="btn btn-sm btn-action-brand" title="Eliminar" @click="openDelete(p)">
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
                <span v-if="saving" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                {{ saving ? 'Guardando...' : 'Guardar' }}
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
            ¿Cambiar estado del proveedor <strong>{{ selected?.nombre }}</strong>?
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
            <h5 class="modal-title">Eliminar proveedor</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" />
          </div>
          <div class="modal-body">¿Eliminar a <strong>{{ selected?.nombre }}</strong>?</div>
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
import FormErrors from '@/components/FormErrors.vue';

const proveedores = ref([]);
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
  toggleModal = new Modal(toggleModalRef.value);
  deleteModal = new Modal(deleteModalRef.value);
  await loadProveedores();
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
  toggleModal.show();
}

function openDelete(item) {
  selected.value = item;
  deleteModal.show();
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
      proveedores.value.sort((a, b) => a.nombre.localeCompare(b.nombre));
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
    toggleModal.hide();
  } finally {
    toggling.value = false;
  }
}

async function confirmDelete() {
  deleting.value = true;
  try {
    await axios.delete(`/catalogos/proveedores/destroy/${selected.value.id}`);
    proveedores.value = proveedores.value.filter((x) => x.id !== selected.value.id);
    deleteModal.hide();
  } catch (error) {
    formErrors.value = [error.response?.data?.message ?? 'No se pudo eliminar el proveedor.'];
  } finally {
    deleting.value = false;
  }
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('es-GT');
}
</script>
