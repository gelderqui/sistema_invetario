<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Configuraciones</h2>
        </div>

        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Cargando información...</p>
        </div>

        <div v-else class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Codigo</th>
                            <th>Descripcion</th>
                            <th>Valor</th>
                            <th>Ult. modificacion</th>
                            <th>Modificado por</th>
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
                            <td class="text-body-secondary small">{{ formatDateTime(item.updated_at) }}</td>
                            <td class="text-body-secondary small">{{ item.last_modified_by_user_name || 'Sistema' }}</td>
                            <td>
                                <div class="d-flex gap-1">
                                    <button class="btn btn-sm btn-action-brand" title="Editar" :disabled="actionLocked" @click="openEdit(item)">
                                        <FontAwesomeIcon icon="fa-solid fa-pencil" class="icon-action-edit" />
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
                                <label class="form-label fw-semibold">Codigo</label>
                                <p class="form-control-plaintext mb-0"><code>{{ form.codigo || '—' }}</code></p>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Descripcion</label>
                                <p class="form-control-plaintext mb-0">{{ form.descripcion || '—' }}</p>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Valor *</label>
                                <input
                                    v-model="form.value"
                                    :type="inputTypePorCodigo(form.codigo)"
                                    class="form-control"
                                    :min="inputMinPorCodigo(form.codigo)"
                                    step="1"
                                    maxlength="255"
                                    required
                                >
                                <div class="form-text">{{ ayudaPorCodigo(form.codigo) }}</div>
                            </div>

                            <p class="small text-body-secondary mb-0">
                                Estado actual: <strong>{{ form.activo ? 'Activo' : 'Inactivo' }}</strong>
                            </p>
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
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { computed, onMounted, ref } from 'vue';

import axios from '@/bootstrap';

const items = ref([]);
const loading = ref(true);
const saving = ref(false);
const editingId = ref(null);
const modalErrors = ref([]);

const modalRef = ref(null);

let bsModal = null;

const emptyForm = () => ({
    codigo: '',
    descripcion: '',
    value: '',
    activo: true,
});

const form = ref(emptyForm());

onMounted(async () => {
    bsModal = new Modal(modalRef.value);
    await loadItems();
});
const actionLocked = computed(() => loading.value || saving.value);

async function loadItems() {
    loading.value = true;
    try {
        const { data } = await axios.get('/configuraciones/get');
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

async function save() {
    saving.value = true;
    modalErrors.value = [];

    try {
        const payload = {
            value: form.value.value,
        };

        if (editingId.value) {
            const { data } = await axios.put(`/configuraciones/update/${editingId.value}`, payload);
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

function formatDateTime(value) {
    if (!value) return '—';
    return new Date(value).toLocaleString('es-GT');
}

function inputTypePorCodigo(codigo) {
    return esCodigoEntero(codigo) ? 'number' : 'text';
}

function inputMinPorCodigo(codigo) {
    if (!esCodigoEntero(codigo)) return undefined;
    return codigo === 'devolucion_limite_dias_cajero' ? 2 : 0;
}

function ayudaPorCodigo(codigo) {
    if (codigo === 'nombre_empresa') return 'Texto requerido.';
    if (codigo === 'devolucion_limite_dias_cajero') return 'Solo entero. Minimo 2.';
    if (codigo === 'porcentaje_utilidad_compra') return 'Porcentaje entero usado en compras para sugerir el precio de venta. Minimo 0.';
    if (esCodigoEntero(codigo)) return 'Solo entero. Minimo 0.';
    return 'Este campo es obligatorio.';
}

function esCodigoEntero(codigo) {
    return codigo !== 'nombre_empresa';
}
</script>
