<script setup>
/**
 * GlobalSearch — input de busqueda cross-entidad del top bar.
 *
 * Disparo: el usuario escribe en el input. Con debounce 300ms, hace
 * GET /search?q=X. La respuesta es JSON con grupos por modulo (Companies,
 * Contacts, Deals, Quotes, Invoices). Cada result es clickeable: click
 * cierra el dropdown y navega via Inertia router al show del registro.
 *
 * Keyboard:
 *   - Esc: cierra dropdown, limpia input.
 *   - Enter: si solo hay 1 resultado, lo abre.
 *   - Up/Down: navegacion entre resultados (futuro, no urgente).
 *
 * UX:
 *   - Minimo 2 caracteres antes de query (matchea backend).
 *   - Loading state mientras pendiente.
 *   - "Sin resultados" si q>=2 y nada matcheado.
 *   - Cerrado al hacer click fuera (via @click.outside-like con backdrop).
 */
import { ref, watch, onMounted, onUnmounted } from 'vue';
import { router } from '@inertiajs/vue3';
import { Input, Spin, Empty } from 'ant-design-vue';
import { SearchOutlined } from '@ant-design/icons-vue';

const query = ref('');
const groups = ref([]);
const loading = ref(false);
const open = ref(false);
const searchRoot = ref(null);

let debounceTimer = null;

watch(query, (v) => {
    if (debounceTimer) clearTimeout(debounceTimer);
    if (!v || v.length < 2) {
        groups.value = [];
        open.value = false;
        return;
    }
    debounceTimer = setTimeout(runSearch, 300);
});

async function runSearch() {
    loading.value = true;
    open.value = true;
    try {
        const url = route('search.global') + '?q=' + encodeURIComponent(query.value);
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
            credentials: 'same-origin',
        });
        if (!res.ok) {
            groups.value = [];
            return;
        }
        const data = await res.json();
        groups.value = data.groups || [];
    } catch (e) {
        groups.value = [];
    } finally {
        loading.value = false;
    }
}

function openResult(group, result) {
    open.value = false;
    query.value = '';
    groups.value = [];
    router.visit(route(group.route, result.slug ?? result.id));
}

function onEsc() {
    open.value = false;
    query.value = '';
    groups.value = [];
}

function onClickOutside(e) {
    if (!searchRoot.value) return;
    if (!searchRoot.value.contains(e.target)) {
        open.value = false;
    }
}

onMounted(() => {
    document.addEventListener('click', onClickOutside);
});
onUnmounted(() => {
    document.removeEventListener('click', onClickOutside);
});

const totalResults = () => groups.value.reduce((s, g) => s + g.results.length, 0);
</script>

<template>
    <div class="global-search" ref="searchRoot">
        <Input
            v-model:value="query"
            :placeholder="$t('global.search_placeholder') ?? 'Buscar empresas, contactos, deals...'"
            allow-clear
            size="middle"
            class="global-search-input"
            @keydown.esc="onEsc"
            @focus="() => { if (query.length >= 2) open = true; }"
        >
            <template #prefix><SearchOutlined /></template>
        </Input>

        <div v-if="open" class="global-search-dropdown">
            <div v-if="loading" class="gs-loading">
                <Spin size="small" /> <span>{{ $t('global.searching') ?? 'Buscando...' }}</span>
            </div>

            <Empty
                v-else-if="totalResults() === 0"
                :description="$t('global.no_results') ?? 'Sin resultados'"
                :image-style="{ height: 40 }"
                class="gs-empty"
            />

            <div v-else>
                <div v-for="group in groups" :key="group.module" class="gs-group">
                    <div class="gs-group-label">{{ group.label }}</div>
                    <button
                        v-for="result in group.results"
                        :key="`${group.module}-${result.id}`"
                        class="gs-result"
                        @click="openResult(group, result)"
                    >
                        <div class="gs-result-title">{{ result.title }}</div>
                        <div v-if="result.subtitle" class="gs-result-subtitle">{{ result.subtitle }}</div>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.global-search {
    position: relative;
    width: 100%;
    max-width: 360px;
}
.global-search-input :deep(.ant-input) {
    border-radius: 6px;
}

.global-search-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #d9d9d9);
    border-radius: 6px;
    box-shadow: 0 4px 16px rgba(0, 0, 0, 0.08);
    z-index: 1050;
    max-height: 480px;
    overflow-y: auto;
    padding: 8px 0;
}

.gs-loading,
.gs-empty {
    padding: 24px 16px;
    text-align: center;
    color: var(--color-text-muted, #888);
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
}

.gs-group {
    padding: 4px 0;
}
.gs-group + .gs-group {
    border-top: 1px solid var(--color-border-light, #f0f0f0);
    margin-top: 4px;
    padding-top: 8px;
}

.gs-group-label {
    font-size: 0.7rem;
    text-transform: uppercase;
    color: var(--color-text-muted, #888);
    padding: 4px 16px;
    font-weight: 600;
    letter-spacing: 0.04em;
}

.gs-result {
    width: 100%;
    text-align: left;
    background: transparent;
    border: none;
    padding: 8px 16px;
    cursor: pointer;
    display: block;
    color: var(--color-text, #333);
}
.gs-result:hover {
    background: var(--color-surface-alt, #f5f5f5);
}
.gs-result-title {
    font-weight: 500;
    font-size: 0.875rem;
}
.gs-result-subtitle {
    font-size: 0.75rem;
    color: var(--color-text-muted, #888);
    margin-top: 2px;
}

@media (max-width: 640px) {
    .global-search { max-width: 100%; }
}
</style>
