import { createRouter, createWebHistory } from 'vue-router';

import axios from '@/bootstrap';
import { beginLoading, endLoading } from '@/components/components_ui/loadingState';
import { useAuthStore } from '@/stores/auth';
import CategoriasView from '@/components/CategoriasView.vue';
import ClientesView from '@/components/ClientesView.vue';
import ComprasView from '@/components/ComprasView.vue';
import ConfiguracionesView from '@/components/ConfiguracionesView.vue';
import DashboardView from '@/components/DashboardView.vue';
import InventarioView from '@/components/InventarioView.vue';
import LoginView from '@/components_public/LoginView.vue';
import ProveedoresView from '@/components/ProveedoresView.vue';
import ProductosView from '@/components/ProductosView.vue';
import RolesView from '@/components/RolesView.vue';
import UnauthorizedView from '@/components/UnauthorizedView.vue';
import UsersView from '@/components/UsersView.vue';

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
        path: '/configuracion/usuarios',
        name: 'config-users',
        component: UsersView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/configuracion/roles',
        name: 'config-roles',
        component: RolesView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/configuracion/configuraciones',
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
        path: '/cliente',
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
        path: '/inventario',
        name: 'inventario',
        component: InventarioView,
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
