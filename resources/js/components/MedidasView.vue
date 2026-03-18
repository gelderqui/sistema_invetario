<template>
  <div>
    <div class="d-flex justify-content-between align-items-center mb-4">
      <h2 class="h4 mb-0">Unidades de medida</h2>
      <button class="btn btn-brand" :disabled="actionLocked" @click="openCreate">
        <FontAwesomeIcon icon="fa-solid fa-plus" class="me-2" />
        Nueva
      </button>
    </div>

    <div v-if="loading" class="text-center py-5">
      <p class="text-body-secondary mb-0">Cargando informacion...</p>
    </div>

    <div v-else class="card border-0 shadow-sm">
      <div class="px-3 pt-3">
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <label class="form-label fw-semibold mb-0" for="filtro-estado-medida">Filtrar estado:</label>
          <select id="filtro-estado-medida" v-model="statusFilter" class="form-select form-select-sm w-auto">
            <option value="todos">Todos</option>
            <option value="activo">Activo</option>
            <option value="inactivo">Inactivo</option>
          </select>

          <label class="form-label fw-semibold mb-0 ms-sm-2" for="filtro-nombre-medida">Nombre:</label>
          <input
            id="filtro-nombre-medida"
            v-model.trim="nameFilter"
            type="text"
            class="form-control form-control-sm"
            style="max-width: 260px;"
            placeholder="Buscar por nombre o abreviatura"
          >
        </div>
      </div>

      <div class="table-responsive mt-3">
        <table class="table table-hover align-middle mb-0">
          <thead class="thead-brand">
            <tr>
              <th>Nombre</th>
              <th>Abreviatura</th>
              <th>Productos</th>
              <th>Estado</th>
              <th>Creado</th>
              <th></th>
            </tr>
          </thead>
          <tbody>
            <tr v-if="!totalMedidas">
              <td colspan="6" class="text-center text-body-secondary py-4">Sin registros</td>
            </tr>
            <tr v-for="item in paginatedMedidas" :key="item.id">
              <td class="fw-semibold">{{ item.nombre }}</td>
              <td><span class="badge text-bg-light border">{{ item.abreviatura }}</span></td>
              <td>{{ item.productos_count ?? 0 }}</td>
              <td>
                <span class="badge" :class="item.activo ? 'text-bg-success' : 'text-bg-secondary'">
                  {{ item.activo ? 'Activo' : 'Inactivo' }}
                </span>
              </td>
              <td class="text-body-secondary small">{{ formatDate(item.created_at) }}</td>
              <td>
                <div class="d-flex gap-1">
                  <button class="btn btn-sm btn-action-brand" title="Editar" :disabled="actionLocked" @click="openEdit(item)">
                    <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
                  </button>
                  <button
                    class="btn btn-sm btn-action-brand"
                    :title="item.activo ? 'Desactivar' : 'Activar'"
                    :disabled="actionLocked"
                    @click="openToggle(item)"
                  >
                    <FontAwesomeIcon
                      :icon="item.activo ? 'fa-solid fa-ban' : 'fa-solid fa-check'"
                      :class="item.activo ? 'icon-action-disable' : 'icon-action-enable'"
                    />
                  </button>
                  <button class="btn btn-sm btn-action-brand" title="Eliminar" :disabled="actionLocked" @click="openDelete(item)">
                    <FontAwesomeIcon icon="fa-solid fa-trash" class="icon-action-delete" />
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <TablePagination
        v-model:page="pageMedidas"
        v-model:perPage="perPageMedidas"
        :total-items="totalMedidas"
      />
    </div>

    <div ref="formModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <div class="modal-header modal-header-brand">
            <h5 class="modal-title">{{ editingId ? 'Editar unidad de medida' : 'Nueva unidad de medida' }}</h5>
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
                <label class="form-label fw-semibold">Abreviatura *</label>
                <input v-model="form.abreviatura" type="text" class="form-control" required maxlength="10">
              </div>

              <div class="form-check form-switch">
                <input id="medida-activo" v-model="form.activo" type="checkbox" class="form-check-input">
                <label class="form-check-label" for="medida-activo">Activo</label>
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
import FormErrors from '@/components/FormErrors.vue';
import ModalConfirm from '@/components/components_ui/ModalConfirm.vue';
import TablePagination from '@/components/components_ui/TablePagination.vue';

const medidas = ref([]);
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
const pageMedidas = ref(1);
const perPageMedidas = ref(10);

const formModalRef = ref(null);
const confirmModalRef = ref(null);

let formModal = null;

const emptyForm = () => ({
  nombre: '',
  abreviatura: '',
  activo: true,
});

const form = ref(emptyForm());

onMounted(async () => {
  formModal = new Modal(formModalRef.value);
  await loadMedidas();
});

const confirmTitle = computed(() => (confirmMode.value === 'delete' ? 'Eliminar unidad de medida' : 'Confirmar estado'));
const confirmMessage = computed(() => {
  if (confirmMode.value === 'delete') return `¿Eliminar la unidad <strong>${selected.value?.nombre ?? ''}</strong>?`;
  return `¿Cambiar estado de la unidad <strong>${selected.value?.nombre ?? ''}</strong>?`;
});
const confirmConfirmText = computed(() => (confirmMode.value === 'delete' ? 'Eliminar' : 'Confirmar'));
const confirmLoading = computed(() => (confirmMode.value === 'delete' ? deleting.value : toggling.value));
const actionLocked = computed(() => loading.value || saving.value || toggling.value || deleting.value);
const displayedMedidas = computed(() => {
  const query = String(nameFilter.value ?? '').trim().toLowerCase();

  return [...medidas.value]
    .filter((item) => {
      if (statusFilter.value === 'todos') return true;
      if (statusFilter.value === 'activo') return Boolean(item.activo);
      if (statusFilter.value === 'inactivo') return !Boolean(item.activo);
      return true;
    })
    .filter((item) => {
      if (!query) return true;
      const nombre = String(item.nombre ?? '').toLowerCase();
      const abrev = String(item.abreviatura ?? '').toLowerCase();
      return nombre.includes(query) || abrev.includes(query);
    })
    .sort((a, b) => String(a.nombre ?? '').localeCompare(String(b.nombre ?? ''), 'es', { sensitivity: 'base' }));
});
const totalMedidas = computed(() => displayedMedidas.value.length);
const totalPagesMedidas = computed(() => Math.max(1, Math.ceil(totalMedidas.value / perPageMedidas.value)));
const safePageMedidas = computed(() => Math.min(Math.max(pageMedidas.value, 1), totalPagesMedidas.value));
const paginatedMedidas = computed(() => {
  const start = (safePageMedidas.value - 1) * perPageMedidas.value;
  return displayedMedidas.value.slice(start, start + perPageMedidas.value);
});

async function loadMedidas() {
  loading.value = true;
  try {
    const { data } = await axios.get('/medidas/get');
    medidas.value = data.data;
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
    abreviatura: item.abreviatura,
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
      nombre: String(form.value.nombre || '').trim(),
      abreviatura: String(form.value.abreviatura || '').trim(),
      activo: Boolean(form.value.activo),
    };

    if (editingId.value) {
      const { data } = await axios.put(`/medidas/update/${editingId.value}`, payload);
      const idx = medidas.value.findIndex((x) => x.id === editingId.value);
      if (idx !== -1) medidas.value[idx] = { ...medidas.value[idx], ...data.data };
    } else {
      const { data } = await axios.post('/medidas/store', payload);
      medidas.value.push({ ...data.data, productos_count: 0 });
    }

    formModal.hide();
  } catch (error) {
    const errors = error.response?.data?.errors;
    formErrors.value = errors ? Object.values(errors).flat() : [error.response?.data?.message ?? 'Error al guardar unidad de medida.'];
  } finally {
    saving.value = false;
  }
}

async function confirmToggle() {
  toggling.value = true;
  try {
    const { data } = await axios.patch(`/medidas/toggle/${selected.value.id}`);
    const idx = medidas.value.findIndex((x) => x.id === selected.value.id);
    if (idx !== -1) medidas.value[idx] = { ...medidas.value[idx], activo: data.data.activo };
    confirmModalRef.value?.close();
  } catch (error) {
    confirmError.value = error.response?.data?.message ?? 'No se pudo cambiar estado.';
  } finally {
    toggling.value = false;
  }
}

async function confirmDelete() {
  deleting.value = true;
  try {
    await axios.delete(`/medidas/destroy/${selected.value.id}`);
    medidas.value = medidas.value.filter((x) => x.id !== selected.value.id);
    confirmModalRef.value?.close();
  } catch (error) {
    confirmError.value = error.response?.data?.message ?? 'No se pudo eliminar la unidad de medida.';
  } finally {
    deleting.value = false;
  }
}

function formatDate(value) {
  if (!value) return '-';
  return new Date(value).toLocaleDateString('es-GT');
}
</script>
