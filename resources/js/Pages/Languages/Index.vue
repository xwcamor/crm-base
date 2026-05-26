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

import LanguagesPageHeader from '@/Components/Languages/LanguagesPageHeader.vue';
import ModuleToolbar from '@/Components/Common/ModuleToolbar.vue';
import LanguagesBulkBar from '@/Components/Languages/LanguagesBulkBar.vue';
import LanguagesBulkDeleteModal from '@/Components/Languages/LanguagesBulkDeleteModal.vue';
import LanguagesDetailDrawer from '@/Components/Languages/LanguagesDetailDrawer.vue';
import LanguagesMobileBottomBar from '@/Components/Languages/LanguagesMobileBottomBar.vue';
import LanguagesMobileDrawers from '@/Components/Languages/LanguagesMobileDrawers.vue';
import LanguagesEmptyState from '@/Components/Languages/LanguagesEmptyState.vue';
import LanguagesFavoriteCell from '@/Components/Languages/LanguagesFavoriteCell.vue';
import LanguagesActionsCell from '@/Components/Languages/LanguagesActionsCell.vue';

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
    languagesFilterFields, languagesEmptyFilters, hydrateLanguagesFilters,
    languagesFiltersToQuery, languagesFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { languagesTableColumns } from './config/columns';
import { languagesExportableColumns, languagesExportEndpoints } from './config/exports';
import { languagesTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const props = defineProps({
    languages: { type: Object, required: true },
    filters:      { type: Object, required: true },
    // Map { csv: 0, excel: 25000, pdf: 5000, word: 10000 } — del config/languages.php.
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
const { loading: tableLoading } = usePageLoading('/languages', 'languages');

// ─── Filters (composable + config) ───────────────────────────────────────
const filterFields = computed(() => languagesFilterFields(t));
const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateLanguagesFilters,
    toQuery:       languagesFiltersToQuery,
    summary:       languagesFiltersSummary,
    empty:         languagesEmptyFilters,
    only: ['languages', 'filters'],
    t,
});

const showSkeleton = computed(() =>
    tableLoading.value && (!props.languages?.data || props.languages.data.length === 0)
);

// ─── Cross-module composables ────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('languages', 'languages');
useModuleUndoToast('system_management.languages.undo_last_delete');

const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, bulkSetActive, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'system_management.languages.bulk_set_active',
    bulkDeleteRoute:    'system_management.languages.bulk_delete',
    resourceLabel:      t('languages.records'),
});

const { isHighlighted, counterLabel } = useModuleListMeta({
    pagination: computed(() => props.languages),
    hasActiveFilters,
    t,
});

// ─── Drawer detalles + track recent view ─────────────────────────────────
const { open: drawerVisible, selected: selectedLanguage, openDetails } = useModuleDrawer({ module: 'languages' });

// ─── Columns ─────────────────────────────────────────────────────────────
const allColumns = computed(() => languagesTableColumns(t));
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

const tablePagination = computed(() => ({
    current:  props.languages.current_page,
    pageSize: props.languages.per_page,
    total:    props.languages.total,
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
const exportableColumns = computed(() => languagesExportableColumns(t));
const exportEndpoints   = computed(() => languagesExportEndpoints());

// ─── Mobile drawers + navigation ────────────────────────────────────────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate = () => router.visit(route('system_management.languages.create'));
const goToTrash  = () => router.visit(route('system_management.languages.trash'));
const goToAudit  = () => router.visit(route('system_management.audit_logs.index', { module: 'languages' }));
const goToEdit   = (record) => router.visit(route('system_management.languages.edit',   record.slug));
const goToDelete = (record) => router.visit(route('system_management.languages.delete', record.slug));

// ─── Duplicate ───────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('system_management.languages.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Keyboard shortcuts ──────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => can('languages.create') && router.visit(route('system_management.languages.create')),
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
const tour = useModuleTour({ module: 'languages', steps: () => languagesTourSteps(t) });
</script>

<template>
    <Head :title="$t('sidebar.languages')" />

    <div>
        <div class="page-header">
            <LanguagesPageHeader
                :title="$t('sidebar.languages')"
                :counter-label="counterLabel"
            />

            <ModuleToolbar
                module="languages"
                :all-columns="allColumns"
                v-model:visible-columns="visibleColumnKeys"
                :can-create="can('languages.create')"
                :can-edit="can('languages.edit')"
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
            storage-key="languages"
            data-tour="filters"
        />

        <FilterChips :fields="filterFields" v-model="filters" />

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <LanguagesBulkBar
                v-if="selectedRowKeys.length > 0"
                :count="selectedRowKeys.length"
                :is-mobile="isMobileScreen"
                :bulk-activating="bulkActivating"
                :can-edit="can('languages.edit')"
                :can-delete="can('languages.delete')"
                @cancel="clearSelection"
                @set-active="bulkSetActive"
                @delete="openBulkDelete"
            />

            <TableSkeleton v-if="showSkeleton" :rows="6" :columns="visibleColumnKeys.length" />

            <ResponsiveTable
                v-else
                :dataSource="props.languages.data"
                :columns="columns"
                :pagination="tablePagination"
                :loading="tableLoading"
                :row-selection="(can('languages.delete') || can('languages.edit')) ? rowSelection : null"
                :row-class-name="(record) => isHighlighted(record.id) ? 'row-highlight' : ''"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <LanguagesEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('languages.create')"
                        @clear-filters="clearFilters"
                        @open-import="openImport"
                    />
                </template>
                <template #bodyCell="{ column, record, isMobile, text }">
                    <LanguagesFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :tour-target="record === props.languages.data[0]"
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

                    <LanguagesActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('languages.edit')"
                        :can-create="can('languages.create')"
                        :can-delete="can('languages.delete')"
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

        <LanguagesDetailDrawer
            v-model:open="drawerVisible"
            :language="selectedLanguage"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('languages.create')"
            :can-edit="can('languages.edit')"
            :can-delete="can('languages.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <LanguagesMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('languages.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <LanguagesMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            :filter-fields="filterFields"
            :can-create="can('languages.create')"
            :is-super="isSuper"
            :can-see-audit="canSeeAudit"
            @open-export="openExport"
            @open-import="openImport"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
        />

        <LanguagesBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('languages.record') : $t('languages.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('languages.export_title')"
            :endpoints="exportEndpoints"
            :limits="props.exportLimits"
            :total-rows="props.languages.total ?? 0"
            :total-unfiltered="props.languages.total_unfiltered ?? props.languages.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('system_management.languages.import')"
            :template-url="route('system_management.languages.import_template')"
            :resource-label="$t('languages.records')"
            :extra-preview-columns="[
                { title: $t('languages.iso_code'), dataIndex: 'iso_code', key: 'iso_code', width: 100 },
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
