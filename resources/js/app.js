import './bootstrap';
import '../css/app.css';
import 'bootstrap';
import 'bootstrap/dist/css/bootstrap.min.css';
import '@coreui/coreui/dist/css/coreui.min.css';

import { library } from '@fortawesome/fontawesome-svg-core';
import { fab } from '@fortawesome/free-brands-svg-icons';
import { far } from '@fortawesome/free-regular-svg-icons';
import { fas } from '@fortawesome/free-solid-svg-icons';
import { FontAwesomeIcon } from '@fortawesome/vue-fontawesome';
import { createApp } from 'vue';
import { createPinia } from 'pinia';

import AppLayout from './AppLayout.vue';
import router from './router.js';

window.addEventListener('unhandledrejection', (event) => {
	const reason = event?.reason;
	if (!reason) return;

	if (reason?.__notifiedToUser) {
		event.preventDefault();
		return;
	}

	const fallback = reason?.response?.data?.message || reason?.message || 'Ocurrio un error inesperado.';
	window.dispatchEvent(new CustomEvent('app:error', {
		detail: { message: String(fallback) },
	}));
	event.preventDefault();
});

library.add(fab, far, fas);

const app = createApp(AppLayout);

app.use(createPinia());
app.use(router);
window.__APP_ROUTER__ = router;
app.component('FontAwesomeIcon', FontAwesomeIcon);
app.mount('#app');
