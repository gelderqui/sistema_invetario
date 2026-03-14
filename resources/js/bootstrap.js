import axios from 'axios';

import { beginLoading, endLoading } from '@/components/components_ui/loadingState';

window.axios = axios;

window.axios.defaults.baseURL = '/api';
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
window.axios.defaults.headers.common.Accept = 'application/json';
window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

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

		if (status === 401 && currentPath !== '/login') {
			window.location.href = `/login?redirect=${encodeURIComponent(currentPath)}`;
		}

		if ((status === 403 || status === 404) && currentPath !== '/login' && currentPath !== '/error') {
			window.location.href = '/error';
		}

		return Promise.reject(error);
	}
);

export default window.axios;
