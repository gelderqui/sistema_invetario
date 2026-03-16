<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Gastos</h2>
            <button class="btn btn-brand" :disabled="loading || saving" @click="openCreate">
                <FontAwesomeIcon icon="fa-solid fa-receipt" class="me-2" />
                Registrar gasto
            </button>
        </div>

        <div v-if="loading" class="text-center py-5">
            <p class="text-body-secondary mb-0">Cargando informacion...</p>
        </div>

        <div v-else class="card border-0 shadow-sm mb-4">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Fecha</th>
                            <th>Tipo</th>
                            <th>Descripcion</th>
                            <th>Monto</th>
                            <th>Metodo</th>
                            <th>Usuario</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!gastos.length">
                            <td colspan="6" class="text-center text-body-secondary py-4">Sin gastos registrados</td>
                        </tr>
                        <tr v-for="gasto in gastos" :key="gasto.id">
                            <td>{{ formatDate(gasto.fecha) }}</td>
                            <td>{{ gasto.tipo_gasto?.nombre ?? '-' }}</td>
                            <td>{{ gasto.descripcion }}</td>
                            <td class="fw-semibold">Q {{ Number(gasto.monto ?? 0).toFixed(2) }}</td>
                            <td class="text-uppercase">{{ gasto.metodo_pago }}</td>
                            <td>{{ gasto.usuario?.name ?? '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <div ref="formModalRef" class="modal fade" tabindex="-1" aria-hidden="true">
            <div class="modal-dialog modal-lg">
                <div class="modal-content">
                    <div class="modal-header modal-header-brand">
                        <h5 class="modal-title">Registrar gasto</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" />
                    </div>

                    <form novalidate @submit.prevent="save">
                        <div class="modal-body d-grid gap-3">
                            <FormErrors :errors="formErrors" />

                            <div class="row g-3">
                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Tipo de gasto *</label>
                                    <Multiselect
                                        v-model="form.tipo_gasto"
                                        :options="catalogs.tipos_gasto"
                                        label="nombre"
                                        track-by="id"
                                        placeholder="Seleccionar tipo..."
                                        :searchable="true"
                                        :allow-empty="false"
                                        :close-on-select="true"
                                        :show-labels="false"
                                    />
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Fecha *</label>
                                    <input v-model="form.fecha" type="date" class="form-control" required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Monto *</label>
                                    <input v-model.number="form.monto" type="number" step="0.0001" min="0.01" class="form-control" required>
                                </div>

                                <div class="col-12 col-md-6">
                                    <label class="form-label fw-semibold">Metodo de pago *</label>
                                    <select v-model="form.metodo_pago" class="form-select" required>
                                        <option v-for="metodo in catalogs.metodos_pago" :key="metodo" :value="metodo">
                                            {{ metodo }}
                                        </option>
                                    </select>
                                </div>
                            </div>

                            <div>
                                <label class="form-label fw-semibold">Descripcion *</label>
                                <textarea
                                    v-model="form.descripcion"
                                    rows="2"
                                    class="form-control"
                                    :placeholder="isTipoOtros ? 'Escribe el detalle del gasto en Otros' : 'Detalle del gasto'"
                                    required
                                />
                            </div>
                        </div>

                        <div class="modal-footer">
                            <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">Cancelar</button>
                            <button type="submit" class="btn btn-brand" :disabled="saving">
                                {{ saving ? 'Guardando...' : 'Guardar gasto' }}
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
import Multiselect from 'vue-multiselect';
import 'vue-multiselect/dist/vue-multiselect.css';

import axios from '@/bootstrap';
import FormErrors from '@/components/FormErrors.vue';

const gastos = ref([]);
const catalogs = ref({ tipos_gasto: [], metodos_pago: ['caja', 'caja_chica', 'banco'] });
const loading = ref(true);
const saving = ref(false);
const formErrors = ref([]);

const formModalRef = ref(null);
let formModal = null;

const emptyForm = () => ({
    tipo_gasto: null,
    descripcion: '',
    monto: null,
    fecha: new Date().toISOString().slice(0, 10),
    metodo_pago: 'caja',
});

const form = ref(emptyForm());

const isTipoOtros = computed(() => {
    const nombre = form.value.tipo_gasto?.nombre ?? '';
    return nombre.toLowerCase() === 'otros';
});

onMounted(async () => {
    formModal = new Modal(formModalRef.value);
    await Promise.all([loadGastos(), loadCatalogs()]);
});

async function loadGastos() {
    loading.value = true;
    try {
        const { data } = await axios.get('/gastos/get');
        gastos.value = data.data;
    } finally {
        loading.value = false;
    }
}

async function loadCatalogs() {
    const { data } = await axios.get('/gastos/get/catalogs');
    catalogs.value = data.data;
}

function openCreate() {
    form.value = emptyForm();
    formErrors.value = [];
    formModal.show();
}

async function save() {
    if (!form.value.tipo_gasto?.id) {
        formErrors.value = ['Debe seleccionar un tipo de gasto.'];
        return;
    }

    saving.value = true;
    formErrors.value = [];

    try {
        const payload = {
            tipo_gasto_id: form.value.tipo_gasto.id,
            descripcion: String(form.value.descripcion || '').trim(),
            monto: Number(form.value.monto || 0),
            fecha: form.value.fecha,
            metodo_pago: form.value.metodo_pago,
        };

        const { data } = await axios.post('/gastos/store', payload);
        gastos.value.unshift(data.data);
        formModal.hide();
    } catch (error) {
        const errors = error.response?.data?.errors;
        formErrors.value = errors ? Object.values(errors).flat() : [error.response?.data?.message ?? 'No se pudo registrar el gasto.'];
    } finally {
        saving.value = false;
    }
}

function formatDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleDateString('es-GT');
}
</script>
