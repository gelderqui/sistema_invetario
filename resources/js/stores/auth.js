import { defineStore } from 'pinia';

export const useAuthStore = defineStore('auth', {
    state: () => ({
        initialized: false,
        user: null,
    }),

    getters: {
        isAuthenticated: (state) => Boolean(state.user),
        permissionCodes: (state) => state.user?.permissions?.map((permission) => permission.code) ?? [],
        roleCodes: (state) => (state.user?.role ? [state.user.role.code] : []),
    },

    actions: {
        setUser(user) {
            this.user = user;
        },

        clearUser() {
            this.user = null;
        },

        setInitialized(value = true) {
            this.initialized = Boolean(value);
        },
    },
});