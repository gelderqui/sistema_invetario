import axios from 'axios';

import { beginLoading, endLoading } from '@/components/components_ui/loadingState';
import { useAuthStore } from '@/stores/auth';

window.axios = axios;

window.axios.defaults.baseURL = '/api';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.headers.common.Accept = 'application/json';
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

let sessionRedirectPending = false;

function extractApiErrorMessage(error) {
	const data = error?.response?.data;
	const errors = data?.errors;

	if (errors && typeof errors === 'object') {
		const first = Object.values(errors).flat()[0];
		if (first) return String(first);
	}

	if (data?.message) {
		return String(data.message);
	}

	if (error?.message) {
		return String(error.message);
	}

	return 'Ocurrio un error inesperado.';
}

function notifyGlobalError(error) {
	if (error?.__notifiedToUser) {
		return;
	}

	error.__notifiedToUser = true;
	const message = extractApiErrorMessage(error);
	const status = error?.response?.status ?? null;

	window.dispatchEvent(new CustomEvent('app:error', {
		detail: { message, status },
	}));
}

function getCurrentRelativeUrl() {
	return `${window.location.pathname}${window.location.search}${window.location.hash}`;
}

function navigateTo(path) {
	const router = window.__APP_ROUTER__;

	if (router) {
		return router.replace(path);
	}

	return window.location.replace(path);
}

function redirectExpiredSession(error, currentRelativeUrl) {
	error.__notifiedToUser = true;

	const authStore = useAuthStore();
	authStore.clearUser();
	authStore.setInitialized(true);

	if (sessionRedirectPending) {
		return;
	}

	sessionRedirectPending = true;
	const loginPath = `/login?session_expired=1&redirect=${encodeURIComponent(currentRelativeUrl)}`;

	Promise.resolve(navigateTo(loginPath)).finally(() => {
		sessionRedirectPending = false;
	});
}

window.axios.interceptors.request.use(
	(config) => {
		if (!config?.skipGlobalLoading) {
			beginLoading();
		}

		return config;
	},
	(error) => {
		endLoading();
		return Promise.reject(error);
	}
);

window.axios.interceptors.response.use(
	(response) => {
		if (!response?.config?.skipGlobalLoading) {
			endLoading();
		}

		return response;
	},
	(error) => {
		if (!error?.config?.skipGlobalLoading) {
			endLoading();
		}

		const status = error?.response?.status;
		const currentPath = window.location.pathname;
		const currentRelativeUrl = getCurrentRelativeUrl();

		if ((status === 401 || status === 419) && currentPath !== '/login') {
			redirectExpiredSession(error, currentRelativeUrl);

			return Promise.reject(error);
		}

		if ((status === 403 || status === 404) && currentPath !== '/login' && currentPath !== '/error') {
			navigateTo('/error');
		}

		notifyGlobalError(error);

		return Promise.reject(error);
	}
);

export default window.axios;
