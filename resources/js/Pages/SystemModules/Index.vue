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

import SystemModulesPageHeader from '@/Components/SystemModules/SystemModulesPageHeader.vue';
import ModuleToolbar from '@/Components/Common/ModuleToolbar.vue';
import SystemModulesBulkBar from '@/Components/SystemModules/SystemModulesBulkBar.vue';
import SystemModulesBulkDeleteModal from '@/Components/SystemModules/SystemModulesBulkDeleteModal.vue';
import SystemModulesDetailDrawer from '@/Components/SystemModules/SystemModulesDetailDrawer.vue';
import SystemModulesMobileBottomBar from '@/Components/SystemModules/SystemModulesMobileBottomBar.vue';
import SystemModulesMobileDrawers from '@/Components/SystemModules/SystemModulesMobileDrawers.vue';
import SystemModulesEmptyState from '@/Components/SystemModules/SystemModulesEmptyState.vue';
import SystemModulesFavoriteCell from '@/Components/SystemModules/SystemModulesFavoriteCell.vue';
import SystemModulesActionsCell from '@/Components/SystemModules/SystemModulesActionsCell.vue';

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
import { useModuleListMeta } from '@/Composables/useModuleListMeta';
import { useColumnPreferences } from '@/Composables/useColumnPreferences';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

import {
    system_modulesFilterFields, system_modulesEmptyFilters, hydrateSystemModulesFilters,
    system_modulesFiltersToQuery, system_modulesFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { system_modulesTableColumns } from './config/columns';
import { system_modulesExportableColumns, system_modulesExportEndpoints } from './config/exports';
import { system_modulesTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const props = defineProps({
    system_modules:      { type: Object, required: true },
    filters:      { type: Object, required: true },
    // Map { csv: 0, excel: 25000, pdf: 5000, word: 10000 } — del config/system_modules.php.
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
const { loading: tableLoading } = usePageLoading('/system_modules', 'system_modules');

// ─── Filters (composable + config) ───────────────────────────────────────
const filterFields = computed(() => system_modulesFilterFields(t));
const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateSystemModulesFilters,
    toQuery:       system_modulesFiltersToQuery,
    summary:       system_modulesFiltersSummary,
    empty:         system_modulesEmptyFilters,
    only:          ['system_modules', 'filters'],
    t,
});

const showSkeleton = computed(() =>
    tableLoading.value && (!props.system_modules?.data || props.system_modules.data.length === 0)
);

// ─── Cross-module composables ────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('system_modules', 'system_modules');
useModuleUndoToast('system_management.system_modules.undo_last_delete');

const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, bulkSetActive, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'system_management.system_modules.bulk_set_active',
    bulkDeleteRoute:    'system_management.system_modules.bulk_delete',
    resourceLabel:      t('system_modules.records'),
});

// ─── Counter label ───────────────────────────────────────────────────────
const { isHighlighted, counterLabel } = useModuleListMeta({
    pagination: computed(() => props.system_modules),
    hasActiveFilters,
    t,
});

// ─── Drawer detalles + track recent view ─────────────────────────────────
const { open: drawerVisible, selected: selectedSystemModule, openDetails } = useModuleDrawer({ module: 'system_modules' });

// ─── Columns ─────────────────────────────────────────────────────────────
const allColumns = computed(() => system_modulesTableColumns(t));
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

const tablePagination = computed(() => ({
    current:  props.system_modules.current_page,
    pageSize: props.system_modules.per_page,
    total:    props.system_modules.total,
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
const exportableColumns = computed(() => system_modulesExportableColumns(t));
const exportEndpoints   = computed(() => system_modulesExportEndpoints());

// ─── Mobile drawers + navigation ────────────────────────────────────────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate = () => router.visit(route('system_management.system_modules.create'));
const goToTrash  = () => router.visit(route('system_management.system_modules.trash'));
const goToAudit  = () => router.visit(route('system_management.audit_logs.index', { module: 'system_modules' }));
const goToEdit   = (record) => router.visit(route('system_management.system_modules.edit',   record.slug));
const goToDelete = (record) => router.visit(route('system_management.system_modules.delete', record.slug));

// ─── Duplicate ───────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('system_management.system_modules.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Keyboard shortcuts ──────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => can('system_modules.create') && router.visit(route('system_management.system_modules.create')),
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
const tour = useModuleTour({ module: 'system_modules', steps: () => system_modulesTourSteps(t) });
</script>

<template>
    <Head :title="$t('sidebar.system_modules')" />

    <div>
        <div class="page-header">
            <SystemModulesPageHeader
                :title="$t('sidebar.system_modules')"
                :counter-label="counterLabel"
            />

            <ModuleToolbar
                module="system_modules"
                :all-columns="allColumns"
                v-model:visible-columns="visibleColumnKeys"
                :can-create="can('system_modules.create')"
                :can-edit="can('system_modules.edit')"
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
            storage-key="system_modules"
            data-tour="filters"
        />

        <FilterChips :fields="filterFields" v-model="filters" />

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <SystemModulesBulkBar
                v-if="selectedRowKeys.length > 0"
                :count="selectedRowKeys.length"
                :is-mobile="isMobileScreen"
                :bulk-activating="bulkActivating"
                :can-edit="can('system_modules.edit')"
                :can-delete="can('system_modules.delete')"
                @cancel="clearSelection"
                @set-active="bulkSetActive"
                @delete="openBulkDelete"
            />

            <TableSkeleton v-if="showSkeleton" :rows="6" :columns="visibleColumnKeys.length" />

            <ResponsiveTable
                v-else
                :dataSource="props.system_modules.data"
                :columns="columns"
                :pagination="tablePagination"
                :loading="tableLoading"
                :row-selection="(can('system_modules.delete') || can('system_modules.edit')) ? rowSelection : null"
                :row-class-name="(record) => isHighlighted(record.id) ? 'row-highlight' : ''"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <SystemModulesEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('system_modules.create')"
                        @clear-filters="clearFilters"
                        @open-import="openImport"
                    />
                </template>
                <template #bodyCell="{ column, record, isMobile, text }">
                    <SystemModulesFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :tour-target="record === props.system_modules.data[0]"
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

                    <SystemModulesActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('system_modules.edit')"
                        :can-create="can('system_modules.create')"
                        :can-delete="can('system_modules.delete')"
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

        <SystemModulesDetailDrawer
            v-model:open="drawerVisible"
            :system_module="selectedSystemModule"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('system_modules.create')"
            :can-edit="can('system_modules.edit')"
            :can-delete="can('system_modules.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <SystemModulesMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('system_modules.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <SystemModulesMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            :filter-fields="filterFields"
            :can-create="can('system_modules.create')"
            :is-super="isSuper"
            :can-see-audit="canSeeAudit"
            @open-export="openExport"
            @open-import="openImport"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
        />

        <SystemModulesBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('system_modules.record') : $t('system_modules.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('system_modules.export_title')"
            :endpoints="exportEndpoints"
            :limits="props.exportLimits"
            :total-rows="props.system_modules.total ?? 0"
            :total-unfiltered="props.system_modules.total_unfiltered ?? props.system_modules.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('system_management.system_modules.import')"
            :template-url="route('system_management.system_modules.import_template')"
            :resource-label="$t('system_modules.records')"
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
.grid-card :deep(.ant-table-tbody > tr) { cursor: pointer; }
.grid-card :deep(.ant-table-tbody > tr:hover > td) { background: var(--color-surface-hover) !important; }

/* Hover-to-reveal de acciones (patrón Notion/Linear). */
.grid-card :deep(.ant-table-tbody .row-actions-desktop) {
    opacity: 0.45;
    transition: opacity 0.15s ease;
}
.grid-card :deep(.ant-table-tbody > tr:hover .row-actions-desktop),
.grid-card :deep(.ant-table-tbody .row-actions-desktop:focus-within) {
    opacity: 1;
}

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
