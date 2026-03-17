<template>
    <section class="manual-page">
        <div class="manual-hero card border-0 shadow-sm mb-4">
            <div class="card-body p-4 p-md-5">
                <p class="manual-kicker mb-2">DOCUMENTACION OPERATIVA</p>
                <h2 class="manual-title mb-2">{{ title }}</h2>
                <p class="manual-subtitle mb-0">
                    Referencia visual del flujo y reglas del sistema. Esta pantalla es de solo lectura.
                </p>
            </div>
        </div>

        <div class="card border-0 shadow-sm">
            <div class="card-body p-4 p-md-5">
                <div v-if="loading" class="text-body-secondary">Cargando manual...</div>
                <div v-else-if="errorMessage" class="alert alert-danger mb-0" role="alert">{{ errorMessage }}</div>
                <article v-else class="manual-content" v-html="manualHtml"></article>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue';
import axios from '@/bootstrap';

const loading = ref(false);
const errorMessage = ref('');
const title = ref('Manual de Usuario');
const markdown = ref('');

const manualHtml = computed(() => markdownToHtml(markdown.value));

onMounted(loadManual);

async function loadManual() {
    loading.value = true;
    errorMessage.value = '';

    try {
        const { data } = await axios.get('/manual/usuario/get');
        title.value = data?.data?.titulo || 'Manual de Usuario';
        markdown.value = data?.data?.markdown || '';
    } catch (error) {
        errorMessage.value = error?.response?.data?.message || 'No se pudo cargar el manual.';
    } finally {
        loading.value = false;
    }
}

function escapeHtml(text) {
    return String(text)
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/\"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

function applyInlineMarkdown(text) {
    const safe = escapeHtml(text);

    return safe
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/`(.+?)`/g, '<code>$1</code>');
}

function markdownToHtml(md) {
    if (!md) return '';

    const lines = String(md).replace(/\r\n/g, '\n').split('\n');
    const html = [];
    let inUl = false;
    let inOl = false;

    const closeLists = () => {
        if (inUl) {
            html.push('</ul>');
            inUl = false;
        }

        if (inOl) {
            html.push('</ol>');
            inOl = false;
        }
    };

    for (const rawLine of lines) {
        const line = rawLine.trim();

        if (!line) {
            closeLists();
            continue;
        }

        if (/^---+$/.test(line)) {
            closeLists();
            html.push('<hr>');
            continue;
        }

        const h2 = line.match(/^##\s+(.+)/);
        if (h2) {
            closeLists();
            html.push(`<h2>${applyInlineMarkdown(h2[1])}</h2>`);
            continue;
        }

        const h3 = line.match(/^###\s+(.+)/);
        if (h3) {
            closeLists();
            html.push(`<h3>${applyInlineMarkdown(h3[1])}</h3>`);
            continue;
        }

        const h1 = line.match(/^#\s+(.+)/);
        if (h1) {
            closeLists();
            html.push(`<h1>${applyInlineMarkdown(h1[1])}</h1>`);
            continue;
        }

        const ol = line.match(/^\d+\.\s+(.+)/);
        if (ol) {
            if (!inOl) {
                closeLists();
                html.push('<ol>');
                inOl = true;
            }
            html.push(`<li>${applyInlineMarkdown(ol[1])}</li>`);
            continue;
        }

        const ul = line.match(/^[-*]\s+(.+)/);
        if (ul) {
            if (!inUl) {
                closeLists();
                html.push('<ul>');
                inUl = true;
            }
            html.push(`<li>${applyInlineMarkdown(ul[1])}</li>`);
            continue;
        }

        closeLists();
        html.push(`<p>${applyInlineMarkdown(line)}</p>`);
    }

    closeLists();

    return html.join('\n');
}
</script>

<style scoped>
.manual-page {
    max-width: 1100px;
    margin: 0 auto;
}

.manual-hero {
    background: linear-gradient(140deg, #093f5b 0%, #0f6a83 45%, #f7a541 100%);
    color: #ffffff;
}

.manual-kicker {
    letter-spacing: 0.12em;
    font-size: 0.75rem;
    font-weight: 700;
    opacity: 0.88;
}

.manual-title {
    font-family: 'Trebuchet MS', 'Segoe UI', sans-serif;
    font-weight: 700;
    font-size: clamp(1.5rem, 2vw, 2rem);
}

.manual-subtitle {
    opacity: 0.95;
}

.manual-content {
    color: #263238;
    line-height: 1.72;
}

.manual-content :deep(h1),
.manual-content :deep(h2),
.manual-content :deep(h3) {
    font-family: 'Trebuchet MS', 'Segoe UI', sans-serif;
    color: #0f4e63;
    margin-top: 1.25rem;
    margin-bottom: 0.65rem;
}

.manual-content :deep(h1) {
    font-size: 1.8rem;
}

.manual-content :deep(h2) {
    font-size: 1.35rem;
    border-bottom: 2px solid #e6eef2;
    padding-bottom: 0.35rem;
}

.manual-content :deep(h3) {
    font-size: 1.1rem;
}

.manual-content :deep(p) {
    margin-bottom: 0.65rem;
}

.manual-content :deep(ul),
.manual-content :deep(ol) {
    margin-bottom: 0.9rem;
    padding-left: 1.3rem;
}

.manual-content :deep(code) {
    background: #eff4f8;
    color: #0c4f67;
    padding: 0.12rem 0.3rem;
    border-radius: 0.3rem;
    font-size: 0.9em;
}

.manual-content :deep(hr) {
    margin: 1.2rem 0;
    border: 0;
    border-top: 1px solid #d8e5ed;
}
</style>
