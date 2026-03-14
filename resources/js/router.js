import { createRouter, createWebHistory } from 'vue-router';

import { useAuthStore } from '@/stores/auth';
import CategoriasView from '@/views/CategoriasView.vue';
import ClientesView from '@/views/ClientesView.vue';
import ComprasView from '@/views/ComprasView.vue';
import DashboardView from '@/views/DashboardView.vue';
import InventarioView from '@/views/InventarioView.vue';
import LoginView from '@/views/LoginView.vue';
import ProveedoresView from '@/views/ProveedoresView.vue';
import ProductosView from '@/views/ProductosView.vue';
import RolesView from '@/views/RolesView.vue';
import UnauthorizedView from '@/views/UnauthorizedView.vue';
import UsersView from '@/views/UsersView.vue';

const permissionByPath = [
    { pattern: /^\/$/, permission: 'dashboard' },
    { pattern: /^\/usuarios(\/|$)/, permission: 'users' },
    { pattern: /^\/roles(\/|$)/, permission: 'roles' },
    { pattern: /^\/categorias(\/|$)/, permission: 'categorias' },
    { pattern: /^\/cliente(\/|$)/, permission: 'cliente' },
    { pattern: /^\/productos(\/|$)/, permission: 'productos' },
    { pattern: /^\/proveedores(\/|$)/, permission: 'proveedores' },
    { pattern: /^\/compras(\/|$)/, permission: 'compras' },
    { pattern: /^\/inventario(\/|$)/, permission: 'inventario' },
];

function requiredPermissionForPath(path) {
    const match = permissionByPath.find((item) => item.pattern.test(path));

    return match?.permission ?? null;
}

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
        name: 'users',
        component: UsersView,
        meta: {
            requiresAuth: true,
        },
    },
    {
        path: '/roles',
        name: 'roles',
        component: RolesView,
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

    if (!to.meta.guestOnly && authStore.isAuthenticated) {
        const permission = requiredPermissionForPath(to.path);

        if (permission && !authStore.hasAnyPermission([permission])) {
            return { name: 'error' };
        }
    }

    return true;
});

export default router;
