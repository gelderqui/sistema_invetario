<template>
    <div class="d-flex justify-content-center bg-white px-3 py-4">
        <div style="width: min(100%, 380px);">
            <div class="card border-0 rounded-4" style="box-shadow: none;">
                <div class="card-body p-4 p-sm-5">
                    <div class="text-center mb-4">
                        <div class="bg-dark text-white rounded-circle d-inline-flex align-items-center justify-content-center mb-3" style="width:56px;height:56px;">
                            <FontAwesomeIcon icon="fa-solid fa-store" size="lg" />
                        </div>
                        <h1 class="h4 fw-bold mb-1">{{ nombreSistema }}</h1>
                    </div>

                    <div v-if="errorMessages.length" class="alert alert-danger d-flex gap-2" role="alert">
                        <FontAwesomeIcon icon="fa-solid fa-circle-exclamation" class="mt-1" />
                        <ul class="mb-0 ps-3">
                            <li v-for="(message, index) in errorMessages" :key="`${index}-${message}`">{{ message }}</li>
                        </ul>
                    </div>

                    <form class="d-grid gap-3" novalidate @submit.prevent="submit">

                        <div>
                            <label class="form-label fw-semibold" for="username">Usuario</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <FontAwesomeIcon icon="fa-solid fa-user" />
                                </span>
                                <input
                                    id="username"
                                    v-model="form.username"
                                    type="text"
                                    class="form-control form-control-lg"
                                    autocomplete="username"
                                    placeholder=""
                                    required
                                >
                            </div>
                        </div>

                        <div>
                            <label class="form-label fw-semibold" for="password">Contraseña</label>
                            <div class="input-group">
                                <span class="input-group-text">
                                    <FontAwesomeIcon icon="fa-solid fa-lock" />
                                </span>
                                <input
                                    id="password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    class="form-control form-control-lg"
                                    autocomplete="current-password"
                                    placeholder=""
                                    required
                                >
                                <button
                                    type="button"
                                    class="btn btn-outline-secondary"
                                    :title="showPassword ? 'Ocultar contraseña' : 'Mostrar contraseña'"
                                    @click="showPassword = !showPassword"
                                >
                                    <FontAwesomeIcon :icon="showPassword ? 'fa-solid fa-eye-slash' : 'fa-solid fa-eye'" />
                                </button>
                            </div>
                        </div>

                        <div class="form-check">
                            <input id="remember" v-model="form.remember" class="form-check-input" type="checkbox">
                            <label class="form-check-label" for="remember">Mantener sesión iniciada</label>
                        </div>

                        <button type="submit" class="btn btn-dark btn-lg w-100 mt-1" :disabled="loading">
                            <span v-if="loading" class="spinner-border spinner-border-sm me-2" aria-hidden="true" />
                            <FontAwesomeIcon v-else icon="fa-solid fa-right-to-bracket" class="me-2" />
                            Ingresar
                        </button>

                    </form>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue';
import { useRoute, useRouter } from 'vue-router';

import axios from '@/bootstrap';
import { useAuthStore } from '@/stores/auth';

const authStore = useAuthStore();
const route = useRoute();
const router = useRouter();

const form = ref({
    username: '',
    password: '',
    remember: false,
});

const errorMessages = ref([]);
const showPassword = ref(false);
const nombreSistema = ref('Sistema POS e Inventario');
const loading = ref(false);

onMounted(async () => {
    try {
        const { data } = await axios.get('/configuraciones/get/login');
        nombreSistema.value = data?.nombre_empresa ?? nombreSistema.value;
    } catch {
        // Usa valor por defecto si el endpoint no esta disponible.
    }
});

async function submit() {
    errorMessages.value = [];
    loading.value = true;

    try {
        await axios.get('/sanctum/csrf-cookie', { baseURL: '' });
        const { data } = await axios.post('/auth/login', form.value);
        authStore.setUser(data.user?.data ?? data.user ?? null);
        authStore.setInitialized(true);
        await router.push(route.query.redirect ?? { name: 'dashboard' });
    } catch (error) {
        const serverErrors = error.response?.data?.errors;

        if (serverErrors && typeof serverErrors === 'object') {
            errorMessages.value = Object.values(serverErrors)
                .flat()
                .filter((message) => typeof message === 'string' && message.trim() !== '');
        }

        if (!errorMessages.value.length) {
            errorMessages.value = [
                error.response?.data?.message ?? 'No fue posible iniciar sesión.',
            ];
        }
    } finally {
        loading.value = false;
    }
}
</script>