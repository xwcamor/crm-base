import { ref, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import dayjs from 'dayjs';

/**
 * Genérico para listados con filtros + paginación + sort + saved-views.
 *
 * Cada módulo declara:
 *   - `serverFilters`: props.filters (lo que vino del backend)
 *   - `hydrate`:  (serverFilters) => filtersLocal  — backend ⇒ form local
 *   - `toQuery`:  (filtersLocal, serverFilters) => queryParams — form local ⇒ request
 *   - `summary`:  (filtersLocal, t) => 'human readable' — para PDF/Word
 *   - `empty`:    () => filtersLocal default — para clearFilters / "vista por defecto"
 *   - `only`:     keys del partial reload (ej. ['regions', 'filters'])
 *
 * Acepta un t opcional para el summary (i18n).
 */
export function useModuleFilters({ serverFilters, hydrate, toQuery, summary, empty, only, t }) {
    const filters = ref(hydrate(serverFilters));

    const reload = (extra = {}) => {
        router.reload({
            only,
            data: { ...toQuery(filters.value, serverFilters), page: 1, ...extra },
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    // Auto-reload cada vez que cambia un filtro (deep) — patrón pull, sin debounce
    // global (cada field decide su debounce en FilterBar).
    watch(filters, () => reload(), { deep: true });

    // Genérico: "filtro activo" = valor no nulo, no array vacío, no false (toggles).
    const hasActiveFilters = computed(() => {
        const f = filters.value;
        return Object.values(f).some((v) => {
            if (v === null || v === undefined || v === '' || v === false) return false;
            if (Array.isArray(v)) return v.some(x => x !== null && x !== undefined);
            return true;
        });
    });

    const clearFilters = () => { filters.value = empty(); };

    const filtersSummary = computed(() => summary ? summary(filters.value, t) : '');

    return {
        filters,
        reload,
        hasActiveFilters,
        clearFilters,
        filtersSummary,
        // Helpers para que el caller serialice el state actual a request params.
        buildQueryData: (extra = {}) => ({ ...toQuery(filters.value, serverFilters), page: 1, ...extra }),
    };
}

/**
 * Helper para serializar/deserializar rangos de fechas (dayjs ↔ ISO string)
 * usado tanto en hydrate como en saved-views.
 */
export const dateRangeFromISO = (from, to) =>
    (from && to) ? [dayjs(from), dayjs(to)] : null;

export const dateRangeToISO = (range) =>
    range?.[0] ? [range[0].format('YYYY-MM-DD'), range[1]?.format('YYYY-MM-DD')] : null;
