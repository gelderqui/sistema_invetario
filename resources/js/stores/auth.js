import { defineStore } from 'pinia';

import axios from '@/bootstrap';
import { hasAnyPermission } from '@/utils/permissions';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        initialized: false,
        loading: false,
        user: null,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.user),
        permissionCodes: (state) => state.user?.permissions?.map((permission) => permission.code) ?? [],
        roleCodes: (state) => (state.user?.role ? [state.user.role.code] : []),
    },

    actions: {
        async initialize() {
            if (this.initialized) {
                return;
            }

            try {
                await this.fetchUser();
            } catch {
                this.user = null;
            } finally {
                this.initialized = true;
            }
        },

        async fetchCsrfCookie() {
            await axios.get('/sanctum/csrf-cookie', { baseURL: '' });
        },

        async fetchUser() {
            const { data } = await axios.get('/auth/me');
            this.user = data.data ?? data;

            return this.user;
        },

        async login(credentials) {
            this.loading = true;

            try {
                await this.fetchCsrfCookie();
                const { data } = await axios.post('/auth/login', credentials);
                this.user = data.user?.data ?? data.user;
                this.initialized = true;

                return this.user;
            } finally {
                this.loading = false;
            }
        },

        async logout() {
            await axios.post('/auth/logout');
            this.user = null;
        },

        async changePassword(payload) {
            const { data } = await axios.put('/auth/password', payload);

            return data;
        },

        hasAnyPermission(requiredPermissions = []) {
            return hasAnyPermission(this.user, requiredPermissions);
        },
    },
});