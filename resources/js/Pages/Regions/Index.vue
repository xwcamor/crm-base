<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Card, Tag } from 'ant-design-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import FilterChips from '@/Components/Common/FilterChips.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ExportDialog from '@/Components/Common/ExportDialog.vue';
import ImportDialog from '@/Components/Common/ImportDialog.vue';
import TableSkeleton from '@/Components/Common/TableSkeleton.vue';

import RegionsPageHeader from '@/Components/Regions/RegionsPageHeader.vue';
import ModuleToolbar from '@/Components/Common/ModuleToolbar.vue';
import RegionsBulkBar from '@/Components/Regions/RegionsBulkBar.vue';
import RegionsBulkDeleteModal from '@/Components/Regions/RegionsBulkDeleteModal.vue';
import RegionsDetailDrawer from '@/Components/Regions/RegionsDetailDrawer.vue';
import RegionsMobileBottomBar from '@/Components/Regions/RegionsMobileBottomBar.vue';
import RegionsMobileDrawers from '@/Components/Regions/RegionsMobileDrawers.vue';
import RegionsEmptyState from '@/Components/Regions/RegionsEmptyState.vue';
import RegionsFavoriteCell from '@/Components/Regions/RegionsFavoriteCell.vue';
import RegionsActionsCell from '@/Components/Regions/RegionsActionsCell.vue';

import { useAuth } from '@/Composables/useAuth';
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts';
import { useModuleTour } from '@/Composables/useModuleTour';
import { useModuleFavorites } from '@/Composables/useModuleFavorites';
import { useModuleUndoToast } from '@/Composables/useModuleUndoToast';
import { useModuleBulkActions } from '@/Composables/useModuleBulkActions';
import { useModuleDrawer } from '@/Composables/useModuleDrawer';
import { useModuleFilters } from '@/Composables/useModuleFilters';
import { useModuleSavedViews } from '@/Composables/useModuleSavedViews';
import { useViewport } from '@/Composables/useViewport';
import { usePageLoading } from '@/Composables/usePageLoading';
import { useI18n } from '@/Plugins/i18n';
import { useModuleListMeta } from '@/Composables/useModuleListMeta';
import { useColumnPreferences } from '@/Composables/useColumnPreferences';
import { useDateFormat } from '@/Composables/useDateFormat';

import {
    regionsFilterFields, regionsEmptyFilters, hydrateRegionsFilters,
    regionsFiltersToQuery, regionsFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { regionsTableColumns } from './config/columns';
import { regionsExportableColumns, regionsExportEndpoints } from './config/exports';
import { regionsTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const props = defineProps({
    regions:      { type: Object, required: true },
    filters:      { type: Object, required: true },
    // Map { csv: 0, excel: 25000, pdf: 5000, word: 10000 } — del config/regions.php.
    // 0 = sin límite (CSV streaming). El ExportDialog deshabilita formatos
    // cuando el count actual excede su límite.
    exportLimits: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTime } = useDateFormat();

// ─── Viewport + loading ──────────────────────────────────────────────────
const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { loading: tableLoading } = usePageLoading('/regions', 'regions');

// ─── Filters (composable + config) ───────────────────────────────────────
const filterFields = computed(() => regionsFilterFields(t));
const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateRegionsFilters,
    toQuery:       regionsFiltersToQuery,
    summary:       regionsFiltersSummary,
    empty:         regionsEmptyFilters,
    only:          ['regions', 'filters'],
    t,
});

const showSkeleton = computed(() =>
    tableLoading.value && (!props.regions?.data || props.regions.data.length === 0)
);

// ─── Cross-module composables ────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('regions', 'regions');
useModuleUndoToast('system_management.regions.undo_last_delete');

const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, bulkSetActive, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'system_management.regions.bulk_set_active',
    bulkDeleteRoute:    'system_management.regions.bulk_delete',
    resourceLabel:      t('regions.records'),
});

const { isHighlighted, counterLabel } = useModuleListMeta({
    pagination: computed(() => props.regions),
    hasActiveFilters,
    t,
});

// ─── Drawer detalles + track recent view ─────────────────────────────────
const { open: drawerVisible, selected: selectedRegion, openDetails } = useModuleDrawer({ module: 'regions' });

// ─── Columns ─────────────────────────────────────────────────────────────
const allColumns = computed(() => regionsTableColumns(t));
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

const tablePagination = computed(() => ({
    current:  props.regions.current_page,
    pageSize: props.regions.per_page,
    total:    props.regions.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100', '200'],
    showTotal: (total, range) => `${range[0]}-${range[1]} ${t('global.of')} ${total}`,
}));

const onTableChange = (pag, _filters, sorter) => {
    const direction = sorter?.order === 'ascend' ? 'asc'
                    : sorter?.order === 'descend' ? 'desc'
                    : props.filters.direction;
    const sort = sorter?.field || props.filters.sort;
    reload({ page: pag.current, per_page: pag.pageSize, sort, direction });
};

// ─── Export / Import ─────────────────────────────────────────────────────
const exportOpen = ref(false);
const importOpen = ref(false);
const openExport = () => { exportOpen.value = true; };
const openImport = () => { importOpen.value = true; };
const exportableColumns = computed(() => regionsExportableColumns(t));
const exportEndpoints   = computed(() => regionsExportEndpoints());

// ─── Mobile drawers + navigation ────────────────────────────────────────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('system_management.regions.create'));
const goToTrash   = () => router.visit(route('system_management.regions.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'regions' }));
const goToEditAll = () => router.visit(route('system_management.regions.edit_all'));
const goToEdit   = (record) => router.visit(route('system_management.regions.edit',   record.slug));
const goToDelete = (record) => router.visit(route('system_management.regions.delete', record.slug));

// ─── Duplicate ───────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('system_management.regions.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Keyboard shortcuts ──────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => can('regions.create') && router.visit(route('system_management.regions.create')),
    'esc': () => {
        if (drawerVisible.value)          drawerVisible.value = false;
        else if (exportOpen.value)        exportOpen.value = false;
        else if (importOpen.value)        importOpen.value = false;
        else if (bulkOpen.value)          bulkOpen.value = false;
        else if (filtersDrawerOpen.value) filtersDrawerOpen.value = false;
        else if (otrosDrawerOpen.value)   otrosDrawerOpen.value = false;
    },
    'ctrl+f': () => {
        if (isMobileScreen.value) filtersDrawerOpen.value = true;
        else document.querySelector('.filter-bar input, .filter-bar .ant-select-selector')?.focus();
    },
});

// ─── Saved Views ─────────────────────────────────────────────────────────
const { currentViewState, applySavedState } = useModuleSavedViews({
    filters,
    visibleColumnKeys,
    allColumns,
    serverFilters: props.filters,
    serialize:     serializeSavedFilters,
    deserialize:   deserializeSavedFilters,
    clearFilters,
    reload,
});

// ─── Onboarding tour ─────────────────────────────────────────────────────
const tour = useModuleTour({ module: 'regions', steps: () => regionsTourSteps(t) });
</script>

<template>
    <Head :title="$t('sidebar.regions')" />

    <div>
        <div class="page-header">
            <RegionsPageHeader
                :title="$t('sidebar.regions')"
                :counter-label="counterLabel"
            />

            <ModuleToolbar
                module="regions"
                :all-columns="allColumns"
                v-model:visible-columns="visibleColumnKeys"
                :can-create="can('regions.create')"
                :can-edit="can('regions.edit')"
                :is-super="isSuper"
                :can-see-audit="canSeeAudit"
                @open-export="openExport"
                @open-import="openImport"
                @restart-tour="tour.restart()"
                :view-state="currentViewState"
                @apply-view="applySavedState"
            />
        </div>

        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="regions"
            data-tour="filters"
        />

        <!-- Filter chips: auto-animate al agregar/quitar tags -->
        <div v-auto-animate>
            <FilterChips :fields="filterFields" v-model="filters" />
        </div>

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <!-- Bulk bar: aparece/desaparece con transición suave -->
            <div v-auto-animate>
                <RegionsBulkBar
                    v-if="selectedRowKeys.length > 0"
                    :count="selectedRowKeys.length"
                    :is-mobile="isMobileScreen"
                    :bulk-activating="bulkActivating"
                    :can-edit="can('regions.edit')"
                    :can-delete="can('regions.delete')"
                    @cancel="clearSelection"
                    @set-active="bulkSetActive"
                    @delete="openBulkDelete"
                />
            </div>

            <TableSkeleton v-if="showSkeleton" :rows="6" :columns="visibleColumnKeys.length" />

            <ResponsiveTable
                v-else
                :dataSource="props.regions.data"
                :columns="columns"
                :pagination="tablePagination"
                :loading="tableLoading"
                :row-selection="(can('regions.delete') || can('regions.edit')) ? rowSelection : null"
                :row-class-name="(record) => isHighlighted(record.id) ? 'row-highlight' : ''"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <RegionsEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('regions.create')"
                        @clear-filters="clearFilters"
                        @open-import="openImport"
                    />
                </template>
                <template #bodyCell="{ column, record, isMobile, text }">
                    <RegionsFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :tour-target="record === props.regions.data[0]"
                        @toggle="toggleFavorite"
                    />

                    <Tag
                        v-else-if="column.key === 'status'"
                        :color="record.is_active ? 'success' : 'error'"
                        :bordered="false"
                    >
                        {{ record.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>

                    <template v-else-if="column.key === 'created_at'">
                        {{ formatDateTime(record.created_at) }}
                    </template>

                    <RegionsActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('regions.edit')"
                        :can-create="can('regions.create')"
                        :can-delete="can('regions.delete')"
                        :duplicating-id="duplicating"
                        @edit="goToEdit"
                        @duplicate="duplicate"
                        @delete="goToDelete"
                    />

                    <template v-else>
                        {{ text ?? record[column.dataIndex] ?? '' }}
                    </template>
                </template>
            </ResponsiveTable>
        </Card>

        <RegionsDetailDrawer
            v-model:open="drawerVisible"
            :region="selectedRegion"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('regions.create')"
            :can-edit="can('regions.edit')"
            :can-delete="can('regions.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <RegionsMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('regions.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <RegionsMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
            :can-create="can('regions.create')"
            :can-edit="can('regions.edit')"
            :is-super="isSuper"
            :can-see-audit="canSeeAudit"
            @open-export="openExport"
            @open-import="openImport"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
            @go-edit-all="goToEditAll"
        />

        <RegionsBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('regions.record') : $t('regions.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('regions.export_title')"
            :endpoints="exportEndpoints"
            :limits="props.exportLimits"
            :total-rows="props.regions.total ?? 0"
            :total-unfiltered="props.regions.total_unfiltered ?? props.regions.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('system_management.regions.import')"
            :template-url="route('system_management.regions.import_template')"
            :resource-label="$t('regions.records')"
        />
    </div>
</template>

<style scoped>
.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.grid-card :deep(.ant-table-thead > tr > th) {
    background: var(--color-surface-alt);
    color: var(--color-text-strong);
    font-weight: 600;
    font-size: 0.8125rem;
}
/* Animaciones de .grid-card (row stagger fade-in, hover lift, empty breathe,
   hover-to-reveal de acciones) viven globalmente en resources/css/app.css.
   Cualquier Index con <Card class="grid-card"> las hereda. */

/* Reserva espacio para que la bulk-bar mobile-sticky no tape la última card. */
.grid-card:has(.bulk-bar--mobile-sticky) {
    padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 80px);
}

@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: stretch; }
}
</style>

<style>
/* Espacio inferior para el bottom-bar fijo (mobile). */
@media (max-width: 767.98px) {
    .below-shell .content {
        padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 150px) !important;
    }
}
</style>
