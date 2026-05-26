import { computed } from 'vue';

/**
 * Cableado de Saved Views para un módulo: serializa el state actual (filters
 * + visibleColumns + sort + direction + perPage) y aplica state guardado.
 * Genérico — depende solo de los refs del módulo y dos serializadores.
 *
 * @param {object} opts
 * @param {Ref<object>} opts.filters             - ref del form de filtros local
 * @param {Ref<string[]>} opts.visibleColumnKeys - keys de columnas visibles
 * @param {ComputedRef<object[]>} opts.allColumns - lista total de columnas (para defaults)
 * @param {object} opts.serverFilters            - props.filters (sort/dir/per_page actuales)
 * @param {Function} opts.serialize              - (filters) => JSON-safe object
 * @param {Function} opts.deserialize            - (state.filters) => filters local
 * @param {Function} opts.clearFilters           - reset al estado vacío
 * @param {Function} opts.reload                 - reload de Inertia con extra params
 * @param {object} [opts.defaults]               - sort/direction/perPage default
 */
export function useModuleSavedViews({
    filters,
    visibleColumnKeys,
    allColumns,
    serverFilters,
    serialize,
    deserialize,
    clearFilters,
    reload,
    defaults = { sort: 'id', direction: 'desc', perPage: 10 },
}) {
    const currentViewState = computed(() => ({
        filters:        serialize(filters.value),
        visibleColumns: visibleColumnKeys.value,
        sort:           serverFilters.sort      ?? defaults.sort,
        direction:      serverFilters.direction ?? defaults.direction,
        perPage:        serverFilters.per_page  ?? defaults.perPage,
    }));

    const applySavedState = (state) => {
        if (!state) {
            // "Vista por defecto": limpia filtros, columnas visibles del default,
            // sort/per_page al inicio.
            clearFilters();
            visibleColumnKeys.value = allColumns.value
                .filter((c) => !c.defaultHidden)
                .map((c) => c.key);
            reload({
                sort:      defaults.sort,
                direction: defaults.direction,
                per_page:  defaults.perPage,
            });
            return;
        }

        filters.value = deserialize(state.filters);

        if (Array.isArray(state.visibleColumns) && state.visibleColumns.length) {
            visibleColumnKeys.value = state.visibleColumns.filter(
                (k) => allColumns.value.some((c) => c.key === k),
            );
        }

        reload({
            sort:      state.sort      ?? defaults.sort,
            direction: state.direction ?? defaults.direction,
            per_page:  state.perPage   ?? defaults.perPage,
        });
    };

    return { currentViewState, applySavedState };
}
