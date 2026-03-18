<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Inventario inicial</h2>
            <button class="btn btn-outline-brand" :disabled="loading" @click="reloadAll">Actualizar</button>
        </div>

        <FormErrors :errors="errors" />

        <div class="alert alert-info">
            Este modulo carga existencias iniciales sin registrar compras. Solo crea inventario, lotes y trazabilidad.
        </div>

        <div class="card border-0 shadow-sm mb-4">
            <div class="card-body row g-3 align-items-end">
                <div class="col-12 col-md-4">
                    <label class="form-label fw-semibold">Producto</label>
                    <select v-model="form.producto_id" class="form-select">
                        <option :value="null">Seleccione</option>
                        <option v-for="p in productos" :key="p.id" :value="p.id">
                            {{ p.nombre }} (Stock: {{ Number(p.stock_actual || 0).toFixed(0) }})
                        </option>
                    </select>
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label fw-semibold">Cantidad</label>
                    <input v-model.number="form.cantidad" type="number" step="1" min="1" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label fw-semibold">Costo unitario</label>
                    <input v-model.number="form.costo_unitario" type="number" step="0.0001" min="0" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label fw-semibold">Precio venta</label>
                    <input v-model.number="form.precio_venta" type="number" step="0.0001" min="0.0001" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label fw-semibold">Fecha entrada</label>
                    <input v-model="form.fecha_entrada" type="date" class="form-control">
                </div>
                <div class="col-12 col-md-2">
                    <label class="form-label fw-semibold">Fecha vencimiento</label>
                    <input v-model="form.fecha_vencimiento" type="date" class="form-control" :disabled="!selectedProducto?.control_vencimiento">
                </div>
                <div class="col-12 col-md-9">
                    <label class="form-label fw-semibold">Observacion</label>
                    <input v-model="form.observacion" type="text" class="form-control" placeholder="Detalle opcional">
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-brand" :disabled="saving" @click="guardar">Registrar inventario inicial</button>
                </div>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive">
                <table class="table table-hover align-middle mb-0">
                    <thead>
                        <tr>
                            <th>Fecha registro</th>
                            <th>Producto</th>
                            <th>Cantidad</th>
                            <th>Costo unitario</th>
                            <th>Precio venta</th>
                            <th>Stock anterior</th>
                            <th>Stock nuevo</th>
                            <th>Referencia</th>
                            <th>Nota</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!totalMovimientosIniciales">
                            <td colspan="9" class="text-center text-body-secondary py-3">Sin cargas iniciales registradas.</td>
                        </tr>
                        <tr v-for="m in paginatedMovimientosIniciales" :key="m.id">
                            <td>{{ fmtDateTime(m.created_at) }}</td>
                            <td>{{ m.producto?.nombre || '-' }}</td>
                            <td class="text-success">{{ Number(m.cantidad || 0).toFixed(0) }}</td>
                            <td>Q {{ Number(m.costo_unitario || 0).toFixed(2) }}</td>
                            <td>Q {{ Number(m.precio_venta || 0).toFixed(2) }}</td>
                            <td>{{ Number(m.stock_anterior || 0).toFixed(0) }}</td>
                            <td>{{ Number(m.stock_nuevo || 0).toFixed(0) }}</td>
                            <td>{{ m.referencia || '-' }}</td>
                            <td>{{ m.nota || '-' }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <TablePagination
                v-model:page="pageInicial"
                v-model:perPage="perPageInicial"
                :total-items="totalMovimientosIniciales"
            />
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from '@/bootstrap';
import FormErrors from '@/components/FormErrors.vue';
import TablePagination from '@/components/components_ui/TablePagination.vue';

const loading = ref(false);
const saving = ref(false);
const errors = ref([]);
const productos = ref([]);
const movimientos = ref([]);
const pageInicial = ref(1);
const perPageInicial = ref(10);

const form = ref(emptyForm());

const selectedProducto = computed(() => productos.value.find((p) => p.id === form.value.producto_id) ?? null);
const totalMovimientosIniciales = computed(() => movimientos.value.length);
const totalPagesInicial = computed(() => Math.max(1, Math.ceil(totalMovimientosIniciales.value / perPageInicial.value)));
const safePageInicial = computed(() => Math.min(Math.max(pageInicial.value, 1), totalPagesInicial.value));
const paginatedMovimientosIniciales = computed(() => {
    const start = (safePageInicial.value - 1) * perPageInicial.value;
    return movimientos.value.slice(start, start + perPageInicial.value);
});

onMounted(reloadAll);

async function reloadAll() {
    loading.value = true;
    try {
        const [catalogsRes, movimientosRes] = await Promise.all([
            axios.get('/inventario/inicial/get/catalogs'),
            axios.get('/inventario/inicial/get'),
        ]);

        productos.value = catalogsRes?.data?.data?.productos ?? [];
        movimientos.value = movimientosRes?.data?.data ?? [];
    } finally {
        loading.value = false;
    }
}

async function guardar() {
    errors.value = [];
    saving.value = true;
    try {
        await axios.post('/inventario/inicial/store', {
            producto_id: form.value.producto_id,
            cantidad: Math.trunc(Number(form.value.cantidad || 0)),
            costo_unitario: Number(form.value.costo_unitario || 0),
            precio_venta: Number(form.value.precio_venta || 0),
            fecha_entrada: form.value.fecha_entrada,
            fecha_vencimiento: form.value.fecha_vencimiento || null,
            observacion: form.value.observacion || null,
        });

        form.value = {
            ...emptyForm(),
            fecha_entrada: form.value.fecha_entrada,
        };

        await reloadAll();
    } catch (error) {
        const backend = error.response?.data?.errors;
        errors.value = backend ? Object.values(backend).flat() : [error.response?.data?.message ?? 'No se pudo registrar inventario inicial.'];
    } finally {
        saving.value = false;
    }
}

function emptyForm() {
    return {
        producto_id: null,
        cantidad: null,
        costo_unitario: null,
        precio_venta: null,
        fecha_entrada: new Date().toISOString().slice(0, 10),
        fecha_vencimiento: null,
        observacion: '',
    };
}

function fmtDateTime(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('es-GT');
}
</script>
