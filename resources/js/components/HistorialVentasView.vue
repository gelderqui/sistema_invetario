<template>
    <div>
        <div class="d-flex justify-content-between align-items-center mb-4">
            <h2 class="h4 mb-0">Historial</h2>
            <button class="btn btn-outline-brand" :disabled="loading" @click="load">Actualizar</button>
        </div>

        <div class="card border-0 shadow-sm mb-3">
            <div class="card-body row g-3 align-items-end">
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Tipo historial</label>
                    <select v-model="filtros.tipo" class="form-select" @change="load">
                        <option value="ventas">Ventas</option>
                        <option value="devoluciones">Devoluciones</option>
                    </select>
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Desde</label>
                    <input v-model="filtros.desde" type="date" class="form-control">
                </div>
                <div class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Hasta</label>
                    <input v-model="filtros.hasta" type="date" class="form-control">
                </div>
                <div v-if="filtros.tipo === 'ventas' && esAdmin" class="col-12 col-md-3">
                    <label class="form-label fw-semibold">Usuario</label>
                    <select v-model="filtros.usuario_id" class="form-select">
                        <option value="all">Todos</option>
                        <option v-for="usuario in usuarios" :key="usuario.id" :value="String(usuario.id)">
                            {{ labelUsuario(usuario) }}
                        </option>
                    </select>
                </div>
                <div class="col-12 col-md-3 d-grid">
                    <button class="btn btn-brand" :disabled="loading || rangoFechasInvalido" @click="load">Filtrar</button>
                </div>
            </div>
        </div>

        <div v-if="rangoFechasInvalido" class="alert alert-warning py-2 px-3 mb-3" role="alert">
            El rango de fechas es invalido: "Hasta" no puede ser menor que "Desde".
        </div>

        <div class="card border-0 shadow-sm">
            <div class="table-responsive" v-if="filtros.tipo === 'ventas'">
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>Numero</th>
                            <th v-if="esAdmin">Usuario</th>
                            <th>Cliente</th>
                            <th>Fecha</th>
                            <th>Estado</th>
                            <th>Items</th>
                            <th>Total</th>
                            <th>Metodo</th>
                            <th>Creado</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length">
                            <td :colspan="esAdmin ? 10 : 9" class="text-center text-body-secondary py-4">Sin ventas registradas.</td>
                        </tr>
                        <tr v-for="venta in rows" :key="venta.id">
                            <td><code>{{ venta.numero }}</code></td>
                            <td v-if="esAdmin">{{ labelUsuario(venta.usuario) }}</td>
                            <td>{{ venta.cliente?.nombre ?? 'Consumidor final' }}</td>
                            <td>{{ fmtDate(venta.fecha_venta) }}</td>
                            <td>
                                <span :class="['badge text-uppercase', venta.estado === 'anulada' ? 'text-bg-danger' : 'text-bg-success']">
                                    {{ venta.estado }}
                                </span>
                            </td>
                            <td>{{ venta.detalles_count ?? 0 }}</td>
                            <td class="fw-semibold">Q {{ formatMoney(venta.total) }}</td>
                            <td class="text-uppercase">{{ venta.metodo_pago }}</td>
                            <td class="text-body-secondary small">{{ fmtDate(venta.created_at) }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-brand" title="Imprimir recibo" @click="imprimirVenta(venta.id)">
                                    <FontAwesomeIcon icon="fa-solid fa-print" />
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger ms-2"
                                    :disabled="loading || venta.estado !== 'activo' || !esMismoDia(venta.fecha_venta) || !puedeAnularVenta(venta)"
                                    :title="tituloAnularVenta(venta)"
                                    @click="anularVenta(venta.id, venta.numero)"
                                >
                                    Anular
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <div class="table-responsive" v-else>
                <table class="table table-hover align-middle mb-0">
                    <thead class="thead-brand">
                        <tr>
                            <th>ID</th>
                            <th>Venta</th>
                            <th>Fecha</th>
                            <th>Usuario</th>
                            <th>Estado</th>
                            <th>Total</th>
                            <th>Detalles</th>
                            <th class="text-end">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!rows.length">
                            <td colspan="8" class="text-center text-body-secondary py-4">Sin devoluciones registradas.</td>
                        </tr>
                        <tr v-for="row in rows" :key="row.id">
                            <td>#{{ row.id }}</td>
                            <td>{{ row.venta?.numero || '-' }}</td>
                            <td>{{ fmtDate(row.fecha) }}</td>
                            <td>{{ row.usuario?.name || '-' }}</td>
                            <td>
                                <span :class="['badge text-uppercase', row.estado === 'anulada' ? 'text-bg-danger' : 'text-bg-success']">
                                    {{ row.estado }}
                                </span>
                            </td>
                            <td class="fw-semibold">Q {{ formatMoney(row.total) }}</td>
                            <td>{{ row.detalles?.length || 0 }}</td>
                            <td class="text-end">
                                <button type="button" class="btn btn-sm btn-outline-brand" title="Imprimir recibo" @click="imprimirDevolucion(row.id)">
                                    <FontAwesomeIcon icon="fa-solid fa-print" />
                                </button>
                                <button
                                    type="button"
                                    class="btn btn-sm btn-outline-danger ms-2"
                                    :disabled="loading || row.estado !== 'activo'"
                                    @click="anularDevolucion(row.id)"
                                >
                                    Anular
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from '@/bootstrap';
import { useAuthStore } from '@/stores/auth';
import { formatMoney } from '@/utils/formatters';

const authStore = useAuthStore();
const loading = ref(false);
const rows = ref([]);
const usuarios = ref([]);
const filtros = ref({
    tipo: 'ventas',
    desde: new Date().toISOString().slice(0, 10),
    hasta: new Date().toISOString().slice(0, 10),
    usuario_id: 'all',
});

const esAdmin = computed(() => authStore.user?.role?.code === 'admin');
const usuarioActualId = computed(() => Number(authStore.user?.id ?? 0));
const rangoFechasInvalido = computed(() => {
    if (!filtros.value.desde || !filtros.value.hasta) return false;
    return filtros.value.hasta < filtros.value.desde;
});

onMounted(load);

async function load() {
    if (rangoFechasInvalido.value) return;

    loading.value = true;
    try {
        const endpoint = filtros.value.tipo === 'ventas'
            ? '/ventas/historial/get'
            : '/ventas/devoluciones/get';

        const { data } = await axios.get(endpoint, {
            params: {
                desde: filtros.value.desde,
                hasta: filtros.value.hasta,
                usuario_id: filtros.value.tipo === 'ventas' && esAdmin.value && filtros.value.usuario_id !== 'all'
                    ? filtros.value.usuario_id
                    : undefined,
            },
        });

        rows.value = data?.data ?? [];

        if (esAdmin.value && filtros.value.tipo === 'ventas') {
            usuarios.value = data?.meta?.usuarios ?? [];
        }
    } finally {
        loading.value = false;
    }
}

function fmtDate(value) {
    if (!value) return '-';
    return new Date(value).toLocaleString('es-GT');
}

function imprimirVenta(id) {
    if (!id) return;
    window.open(`/api/ventas/${id}/ticket`, '_blank', 'noopener');
}

function imprimirDevolucion(id) {
    if (!id) return;
    window.open(`/api/ventas/devoluciones/${id}/ticket`, '_blank', 'noopener');
}

async function anularVenta(id, numero) {
    if (!id) return;

    const ok = window.confirm(`Se anulara la venta ${numero}. Esta accion revierte inventario y caja. Deseas continuar?`);
    if (!ok) return;

    loading.value = true;
    try {
        await axios.patch(`/ventas/anular/${id}`);
        await load();
    } finally {
        loading.value = false;
    }
}

async function anularDevolucion(id) {
    if (!id) return;

    const ok = window.confirm(`Se anulara la devolucion #${id}. Esta accion revierte inventario. Deseas continuar?`);
    if (!ok) return;

    loading.value = true;
    try {
        await axios.patch(`/ventas/devoluciones/anular/${id}`);
        await load();
    } finally {
        loading.value = false;
    }
}

function esMismoDia(value) {
    if (!value) return false;

    const fecha = new Date(value);
    const hoy = new Date();

    return fecha.getFullYear() === hoy.getFullYear()
        && fecha.getMonth() === hoy.getMonth()
        && fecha.getDate() === hoy.getDate();
}

function puedeAnularVenta(venta) {
    return Number(venta?.add_user ?? 0) === usuarioActualId.value;
}

function tituloAnularVenta(venta) {
    if (!esMismoDia(venta?.fecha_venta)) return 'Solo se anula en el mismo dia';
    if (!puedeAnularVenta(venta)) return 'Solo el usuario que registro la venta puede anularla';
    return 'Anular venta';
}

function labelUsuario(usuario) {
    if (!usuario) return '-';

    const base = usuario.username ? `${usuario.name} (${usuario.username})` : usuario.name;

    if (usuario.deleted_at) return `${base} - Eliminado`;
    if (usuario.activo === false) return `${base} - Inactivo`;

    return base;
}
</script>
