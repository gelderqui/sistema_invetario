import { createRouter, createWebHistory } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import CategoriasView from '@/views/CategoriasView.vue';
import ComprasView from '@/views/ComprasView.vue';
import DashboardView from '@/views/DashboardView.vue';
import InventarioView from '@/views/InventarioView.vue';
import LoginView from '@/views/LoginView.vue';
import ProveedoresView from '@/views/ProveedoresView.vue';
import ProductosView from '@/views/ProductosView.vue';
import RolesView from '@/views/RolesView.vue';
import UnauthorizedView from '@/views/UnauthorizedView.vue';
import UsersView from '@/views/UsersView.vue';

const routes = [
    {
        path: '/',
        name: 'dashboard',
        component: DashboardView,
        meta: {
            requiresAuth: true,
            permissions: ['dashboard.view'],
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
        name: 'users',
        component: UsersView,
        meta: {
            requiresAuth: true,
            permissions: ['users.manage'],
        },
    },
    {
        path: '/roles',
        name: 'roles',
        component: RolesView,
        meta: {
            requiresAuth: true,
            permissions: ['roles.manage'],
        },
    },
    {
        path: '/categorias',
        name: 'categorias',
        component: CategoriasView,
        meta: {
            requiresAuth: true,
            permissions: ['inventory.manage'],
        },
    },
    {
        path: '/productos',
        name: 'productos',
        component: ProductosView,
        meta: {
            requiresAuth: true,
            permissions: ['inventory.manage'],
        },
    },
    {
        path: '/proveedores',
        name: 'proveedores',
        component: ProveedoresView,
        meta: {
            requiresAuth: true,
            permissions: ['inventory.manage'],
        },
    },
    {
        path: '/compras',
        name: 'compras',
        component: ComprasView,
        meta: {
            requiresAuth: true,
            permissions: ['purchases.view', 'purchases.create'],
        },
    },
    {
        path: '/inventario',
        name: 'inventario',
        component: InventarioView,
        meta: {
            requiresAuth: true,
            permissions: ['inventory.view'],
        },
    },
    {
        path: '/unauthorized',
        name: 'unauthorized',
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
    const authStore = useAuthStore();

    if (!authStore.initialized) {
        await authStore.initialize();
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

    if (to.meta.permissions && !authStore.hasAnyPermission(to.meta.permissions)) {
        return { name: 'unauthorized' };
    }

    return true;
});

export default router;
