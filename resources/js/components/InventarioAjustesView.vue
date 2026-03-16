<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Ajustes de inventario</h2>
            <button class="btn btn-outline-brand" :disabled="loading" @click="reloadAll">Actualizar</button>
        </div>

        <FormErrors :errors="errors" />

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Producto</label>
                    <select v-model="form.producto_id" class="form-select">
                        <option :value="null">Seleccione</option>
                        <option v-for="p in productos" :key="p.id" :value="p.id">{{ p.nombre }} (Stock: {{ Number(p.stock_actual || 0).toFixed(0) }})</option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label fw-semibold">Cantidad (+/-)</label>
                    <input v-model.number="form.cantidad" type="number" step="1" class="form-control">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Motivo</label>
                    <select v-model="form.motivo_id" class="form-select">
                        <option :value="null">Seleccione</option>
                        <option v-for="m in motivos" :key="m.id" :value="m.id">{{ m.nombre }} ({{ m.tipo }})</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Fecha</label>
                    <input v-model="form.fecha" type="date" class="form-control">
                </div>
                <div class="col-12 col-md-9">
                    <label class="form-label fw-semibold">Observacion</label>
                    <input v-model="form.observacion" type="text" class="form-control" placeholder="Detalle del ajuste">
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-brand" :disabled="saving" @click="guardar">Registrar ajuste</button>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha</th>
                            <th>Producto</th>
                            <th>Motivo</th>
                            <th>Cantidad</th>
                            <th>Usuario</th>
                            <th>Observacion</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!ajustes.length">
                            <td colspan="6" class="text-center text-body-secondary py-3">Sin ajustes registrados.</td>
                        </tr>
                        <tr v-for="a in ajustes" :key="a.id">
                            <td>{{ fmtDate(a.fecha) }}</td>
                            <td>{{ a.producto?.nombre || '-' }}</td>
                            <td>{{ a.motivo?.nombre || '-' }}</td>
                            <td :class="Number(a.cantidad) < 0 ? 'text-danger' : 'text-success'">{{ Number(a.cantidad || 0).toFixed(0) }}</td>
                            <td>{{ a.usuario?.name || '-' }}</td>
                            <td>{{ a.observacion || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import axios from '@/bootstrap';
import FormErrors from '@/components/FormErrors.vue';

const loading = ref(false);
const saving = ref(false);
const errors = ref([]);
const productos = ref([]);
const motivos = ref([]);
const ajustes = ref([]);

const form = ref({
    producto_id: null,
    cantidad: null,
    motivo_id: null,
    fecha: new Date().toISOString().slice(0, 10),
    observacion: '',
});

onMounted(reloadAll);

async function reloadAll() {
    loading.value = true;
    try {
        const [catalogsRes, ajustesRes] = await Promise.all([
            axios.get('/inventario/ajustes/get/catalogs'),
            axios.get('/inventario/ajustes/get'),
        ]);
        productos.value = catalogsRes?.data?.data?.productos ?? [];
        motivos.value = catalogsRes?.data?.data?.motivos ?? [];
        ajustes.value = ajustesRes?.data?.data ?? [];
    } finally {
        loading.value = false;
    }
}

async function guardar() {
    errors.value = [];
    saving.value = true;
    try {
        await axios.post('/inventario/ajustes/store', {
            producto_id: form.value.producto_id,
            cantidad: Math.trunc(Number(form.value.cantidad || 0)),
            motivo_id: form.value.motivo_id,
            fecha: form.value.fecha,
            observacion: form.value.observacion || null,
        });

        form.value.cantidad = null;
        form.value.observacion = '';

        await reloadAll();
    } catch (error) {
        const backend = error.response?.data?.errors;
        errors.value = backend ? Object.values(backend).flat() : [error.response?.data?.message ?? 'No se pudo registrar ajuste.'];
    } finally {
        saving.value = false;
    }
}

function fmtDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('es-GT');
}
</script>
