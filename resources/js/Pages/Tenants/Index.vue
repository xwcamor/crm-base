<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Card, Tag, Space } from 'ant-design-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import FilterChips from '@/Components/Common/FilterChips.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ExportDialog from '@/Components/Common/ExportDialog.vue';
import ImportDialog from '@/Components/Common/ImportDialog.vue';
import TableSkeleton from '@/Components/Common/TableSkeleton.vue';

import TenantsPageHeader from '@/Components/Tenants/TenantsPageHeader.vue';
import ModuleToolbar from '@/Components/Common/ModuleToolbar.vue';
import TenantsBulkBar from '@/Components/Tenants/TenantsBulkBar.vue';
import TenantsBulkDeleteModal from '@/Components/Tenants/TenantsBulkDeleteModal.vue';
import TenantsDetailDrawer from '@/Components/Tenants/TenantsDetailDrawer.vue';
import TenantsMobileBottomBar from '@/Components/Tenants/TenantsMobileBottomBar.vue';
import TenantsMobileDrawers from '@/Components/Tenants/TenantsMobileDrawers.vue';
import TenantsEmptyState from '@/Components/Tenants/TenantsEmptyState.vue';
import TenantsFavoriteCell from '@/Components/Tenants/TenantsFavoriteCell.vue';
import TenantsActionsCell from '@/Components/Tenants/TenantsActionsCell.vue';

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
    tenantsFilterFields, tenantsEmptyFilters, hydrateTenantsFilters,
    tenantsFiltersToQuery, tenantsFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { tenantsTableColumns } from './config/columns';
import { tenantsExportableColumns, tenantsExportEndpoints } from './config/exports';
import { tenantsTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const props = defineProps({
    tenants:      { type: Object, required: true },
    filters:      { type: Object, required: true },
    exportLimits: { type: Object, default: () => ({}) },
    planOptions:  { type: Array, default: () => [] },
});

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTime } = useDateFormat();

// ─── Viewport + loading ──────────────────────────────────────────────────
const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { loading: tableLoading } = usePageLoading('/tenants', 'tenants');

// ─── Filters (composable + config) ───────────────────────────────────────
const filterFields = computed(() => tenantsFilterFields(t, {
    planOptions: props.planOptions,
}));
const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateTenantsFilters,
    toQuery:       tenantsFiltersToQuery,
    summary:       tenantsFiltersSummary,
    empty:         tenantsEmptyFilters,
    only:          ['tenants', 'filters'],
    t,
});

const showSkeleton = computed(() =>
    tableLoading.value && (!props.tenants?.data || props.tenants.data.length === 0)
);

// ─── Cross-module composables ────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('tenants', 'tenants');
useModuleUndoToast('system_management.tenants.undo_last_delete');

const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, bulkSetActive, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'system_management.tenants.bulk_set_active',
    bulkDeleteRoute:    'system_management.tenants.bulk_delete',
    resourceLabel:      t('tenants.records'),
});

// ─── Counter label ───────────────────────────────────────────────────────
const { isHighlighted, counterLabel } = useModuleListMeta({
    pagination: computed(() => props.tenants),
    hasActiveFilters,
    t,
});

// ─── Drawer detalles + track recent view ─────────────────────────────────
const { open: drawerVisible, selected: selectedTenant, openDetails } = useModuleDrawer({ module: 'tenants' });

// ─── Columns ─────────────────────────────────────────────────────────────
const allColumns = computed(() => tenantsTableColumns(t));
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

const tablePagination = computed(() => ({
    current:  props.tenants.current_page,
    pageSize: props.tenants.per_page,
    total:    props.tenants.total,
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
const exportableColumns = computed(() => tenantsExportableColumns(t));
const exportEndpoints   = computed(() => tenantsExportEndpoints());

// ─── Mobile drawers + navigation ────────────────────────────────────────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate = () => router.visit(route('system_management.tenants.create'));
const goToTrash  = () => router.visit(route('system_management.tenants.trash'));
const goToAudit  = () => router.visit(route('system_management.audit_logs.index', { module: 'tenants' }));
const goToEdit   = (record) => router.visit(route('system_management.tenants.edit',   record.slug));
const goToDelete = (record) => router.visit(route('system_management.tenants.delete', record.slug));

// ─── Duplicate ───────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('system_management.tenants.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Keyboard shortcuts ──────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => can('tenants.create') && router.visit(route('system_management.tenants.create')),
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
const tour = useModuleTour({ module: 'tenants', steps: () => tenantsTourSteps(t) });
</script>

<template>
    <Head :title="$t('sidebar.tenants')" />

    <div>
        <div class="page-header">
            <TenantsPageHeader
                :title="$t('sidebar.tenants')"
                :counter-label="counterLabel"
            />

            <ModuleToolbar
                module="tenants"
                :all-columns="allColumns"
                v-model:visible-columns="visibleColumnKeys"
                :can-create="can('tenants.create')"
                :can-edit="can('tenants.edit')"
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
            storage-key="tenants"
            data-tour="filters"
        />

        <FilterChips :fields="filterFields" v-model="filters" />

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <TenantsBulkBar
                v-if="selectedRowKeys.length > 0"
                :count="selectedRowKeys.length"
                :is-mobile="isMobileScreen"
                :bulk-activating="bulkActivating"
                :can-edit="can('tenants.edit')"
                :can-delete="can('tenants.delete')"
                @cancel="clearSelection"
                @set-active="bulkSetActive"
                @delete="openBulkDelete"
            />

            <TableSkeleton v-if="showSkeleton" :rows="6" :columns="visibleColumnKeys.length" />

            <ResponsiveTable
                v-else
                :dataSource="props.tenants.data"
                :columns="columns"
                :pagination="tablePagination"
                :loading="tableLoading"
                :row-selection="(can('tenants.delete') || can('tenants.edit')) ? rowSelection : null"
                :row-class-name="(record) => isHighlighted(record.id) ? 'row-highlight' : ''"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <TenantsEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('tenants.create')"
                        @clear-filters="clearFilters"
                        @open-import="openImport"
                    />
                </template>
                <template #bodyCell="{ column, record, isMobile, text }">
                    <TenantsFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :tour-target="record === props.tenants.data[0]"
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

                    <template v-else-if="column.key === 'plan'">
                        <Tag :color="record.plan === 'enterprise' ? 'gold' : (record.plan === 'pro' ? 'blue' : 'default')" :bordered="false">
                            {{ (record.plan ?? 'free').toUpperCase() }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'users_count'">
                        {{ record.users_count ?? 0 }}
                    </template>

                    <TenantsActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('tenants.edit')"
                        :can-create="can('tenants.create')"
                        :can-delete="can('tenants.delete')"
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

        <TenantsDetailDrawer
            v-model:open="drawerVisible"
            :tenant="selectedTenant"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('tenants.create')"
            :can-edit="can('tenants.edit')"
            :can-delete="can('tenants.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <TenantsMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('tenants.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <TenantsMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            :filter-fields="filterFields"
            :can-create="can('tenants.create')"
            :is-super="isSuper"
            :can-see-audit="canSeeAudit"
            @open-export="openExport"
            @open-import="openImport"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
        />

        <TenantsBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('tenants.record') : $t('tenants.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('tenants.export_title')"
            :endpoints="exportEndpoints"
            :limits="props.exportLimits"
            :total-rows="props.tenants.total ?? 0"
            :total-unfiltered="props.tenants.total_unfiltered ?? props.tenants.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('system_management.tenants.import')"
            :template-url="route('system_management.tenants.import_template')"
            :resource-label="$t('tenants.records')"
            :extra-preview-columns="[
                { title: $t('tenants.plan'), dataIndex: 'plan', key: 'plan', width: 100 },
            ]"
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
