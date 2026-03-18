import { createRouter, createWebHistory } from 'vue-router';

import axios from '@/bootstrap';
import { beginLoading, endLoading } from '@/components/components_ui/loadingState';
import { useAuthStore } from '@/stores/auth';
import CajaView from '@/components/CajaView.vue';
import CapitalView from '@/components/CapitalView.vue';
import CategoriasView from '@/components/CategoriasView.vue';
import ClientesView from '@/components/ClientesView.vue';
import ComprasView from '@/components/ComprasView.vue';
import ConfiguracionesView from '@/components/ConfiguracionesView.vue';
import DevolucionesView from '@/components/DevolucionesView.vue';
import DashboardView from '@/components/DashboardView.vue';
import GastosView from '@/components/GastosView.vue';
import HistorialVentasView from '@/components/HistorialVentasView.vue';
import InventarioAlertasView from '@/components/InventarioAlertasView.vue';
import InventarioAjustesView from '@/components/InventarioAjustesView.vue';
import InventarioInicialView from '@/components/InventarioInicialView.vue';
import InventarioView from '@/components/InventarioView.vue';
import LoginView from '@/components_public/LoginView.vue';
import ManualUsuarioView from '@/components/ManualUsuarioView.vue';
import MedidasView from '@/components/MedidasView.vue';
import ProveedoresView from '@/components/ProveedoresView.vue';
import ProductosView from '@/components/ProductosView.vue';
import ReportesView from '@/components/ReportesView.vue';
import RolesView from '@/components/RolesView.vue';
import UnauthorizedView from '@/components/UnauthorizedView.vue';
import UsersView from '@/components/UsersView.vue';
import VentasView from '@/components/VentasView.vue';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/login',
        name: 'login',
        component: LoginView,
        meta: {
            guestOnly: true,
        },
    },
    {
        path: '/usuarios',
        alias: ['/configuracion/usuarios'],
        name: 'config-users',
        component: UsersView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/roles',
        alias: ['/configuracion/roles'],
        name: 'config-roles',
        component: RolesView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/configuraciones',
        alias: ['/configuracion/configuraciones'],
        name: 'config-configuraciones',
        component: ConfiguracionesView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/categorias',
        name: 'categorias',
        component: CategoriasView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/clientes',
        alias: ['/cliente'],
        name: 'cliente',
        component: ClientesView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/productos',
        name: 'productos',
        component: ProductosView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/categorias/medidas',
        name: 'medidas',
        component: MedidasView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/proveedores',
        name: 'proveedores',
        component: ProveedoresView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/compras',
        name: 'compras',
        component: ComprasView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/caja',
        redirect: '/caja/apertura',
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/caja/apertura',
        name: 'caja-apertura',
        component: CajaView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/caja/movimientos',
        name: 'caja-movimientos',
        component: CajaView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/caja/arqueo',
        name: 'caja-arqueo',
        component: CajaView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/caja/cierre',
        name: 'caja-cierre',
        component: CajaView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/capital',
        name: 'capital',
        component: CapitalView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/ventas',
        name: 'ventas',
        component: VentasView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/ventas/historial',
        name: 'historial-ventas',
        component: HistorialVentasView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/ventas/devoluciones',
        name: 'devoluciones',
        component: DevolucionesView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/inventario',
        redirect: '/inventario/stock',
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/inventario/inicial',
        name: 'inventario-inicial',
        component: InventarioInicialView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/inventario/stock',
        name: 'inventario',
        component: InventarioView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/inventario/movimientos',
        name: 'inventario-movimientos',
        component: InventarioView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/inventario/ajustes',
        name: 'inventario-ajustes',
        component: InventarioAjustesView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/inventario/alertas',
        name: 'inventario-alertas',
        component: InventarioAlertasView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/inventario/vencidos',
        name: 'inventario-vencidos',
        redirect: '/inventario/alertas',
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/gastos',
        name: 'gastos',
        component: GastosView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/reportes',
        name: 'reportes',
        component: ReportesView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/manual/usuario',
        name: 'manual-usuario',
        component: ManualUsuarioView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/error',
        name: 'error',
        component: UnauthorizedView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/:catchAll(.*)',
        name: 'not-found',
        component: UnauthorizedView,
        meta: {
            requiresAuth: true,
        },
    },
];

const router = createRouter({
    history: createWebHistory(),
    routes,
});

router.beforeEach(async (to) => {
    beginLoading();

    const authStore = useAuthStore();

    if (!authStore.initialized) {
        try {
            const { data } = await axios.get('/auth/me');
            authStore.setUser(data.data ?? data ?? null);
        } catch {
            authStore.clearUser();
        } finally {
            authStore.setInitialized(true);
        }
    }

    if (to.meta.requiresAuth && !authStore.isAuthenticated) {
        return {
            name: 'login',
            query: { redirect: to.fullPath },
        };
    }

    if (to.meta.guestOnly && authStore.isAuthenticated) {
        return { name: 'dashboard' };
    }

    return true;
});

router.afterEach(() => {
    endLoading();
});

router.onError(() => {
    endLoading();
});

export default router;
