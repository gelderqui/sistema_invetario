<template>
    <div ref="modalRef" class="modal fade" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header modal-header-brand">
                    <h5 class="modal-title">{{ title }}</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" />
                </div>

                <div class="modal-body">
                    <p class="mb-1" v-html="message"></p>
                    <p v-if="hint" class="small text-body-secondary mb-0">{{ hint }}</p>
                    <div v-if="errorMessage" class="alert alert-danger mt-3 py-2 mb-0 small">
                        {{ errorMessage }}
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-brand" data-bs-dismiss="modal">{{ cancelText }}</button>
                    <button type="button" class="btn btn-brand" :disabled="loading" @click="emit('confirm')">
                        {{ loading ? 'Procesando...' : confirmText }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { Modal } from 'bootstrap';
import { onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({
    title: {
        type: String,
        default: 'Confirmar',
    },
    message: {
        type: String,
        default: '',
    },
    hint: {
        type: String,
        default: '',
    },
    confirmText: {
        type: String,
        default: 'Confirmar',
    },
    cancelText: {
        type: String,
        default: 'Cancelar',
    },
    loading: {
        type: Boolean,
        default: false,
    },
    errorMessage: {
        type: String,
        default: '',
    },
});

const emit = defineEmits(['confirm', 'hidden']);
const modalRef = ref(null);
let modalInstance = null;

onMounted(() => {
    modalInstance = new Modal(modalRef.value);
    modalRef.value?.addEventListener('hidden.bs.modal', handleHidden);
});

onBeforeUnmount(() => {
    modalRef.value?.removeEventListener('hidden.bs.modal', handleHidden);
    modalInstance?.dispose();
});

function handleHidden() {
    emit('hidden');
}

function open() {
    modalInstance?.show();
}

function close() {
    modalInstance?.hide();
}

defineExpose({ open, close });
</script>
