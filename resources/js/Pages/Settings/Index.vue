<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Card, Tag, Tooltip } from 'ant-design-vue';
import { LockOutlined, WarningOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import FilterChips from '@/Components/Common/FilterChips.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ExportDialog from '@/Components/Common/ExportDialog.vue';
import ImportDialog from '@/Components/Common/ImportDialog.vue';
import TableSkeleton from '@/Components/Common/TableSkeleton.vue';

import SettingsPageHeader from '@/Components/Settings/SettingsPageHeader.vue';
import ModuleToolbar from '@/Components/Common/ModuleToolbar.vue';
import SettingsBulkBar from '@/Components/Settings/SettingsBulkBar.vue';
import SettingsBulkDeleteModal from '@/Components/Settings/SettingsBulkDeleteModal.vue';
import SettingsDetailDrawer from '@/Components/Settings/SettingsDetailDrawer.vue';
import SettingsMobileBottomBar from '@/Components/Settings/SettingsMobileBottomBar.vue';
import SettingsMobileDrawers from '@/Components/Settings/SettingsMobileDrawers.vue';
import SettingsEmptyState from '@/Components/Settings/SettingsEmptyState.vue';
import SettingsFavoriteCell from '@/Components/Settings/SettingsFavoriteCell.vue';
import SettingsActionsCell from '@/Components/Settings/SettingsActionsCell.vue';
import SettingsBrandingCard from '@/Components/Settings/SettingsBrandingCard.vue';

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
    settingsFilterFields, settingsEmptyFilters, hydrateSettingsFilters,
    settingsFiltersToQuery, settingsFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { settingsTableColumns } from './config/columns';
import { settingsExportableColumns, settingsExportEndpoints } from './config/exports';
import { settingsTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const props = defineProps({
    settings:      { type: Object, required: true },
    filters:      { type: Object, required: true },
    // Map { csv: 0, excel: 25000, pdf: 5000, word: 10000 } — del config/settings.php.
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
const { loading: tableLoading } = usePageLoading('/settings', 'settings');

// ─── Filters (composable + config) ───────────────────────────────────────
const filterFields = computed(() => settingsFilterFields(t));
const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateSettingsFilters,
    toQuery:       settingsFiltersToQuery,
    summary:       settingsFiltersSummary,
    empty:         settingsEmptyFilters,
    only:          ['settings', 'filters'],
    t,
});

const showSkeleton = computed(() =>
    tableLoading.value && (!props.settings?.data || props.settings.data.length === 0)
);

// ─── Cross-module composables ────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('settings', 'settings');
useModuleUndoToast('system_management.settings.undo_last_delete');

const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, bulkSetActive, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'system_management.settings.bulk_set_active',
    bulkDeleteRoute:    'system_management.settings.bulk_delete',
    resourceLabel:      t('settings.records'),
});

// ─── Counter label ───────────────────────────────────────────────────────
const { isHighlighted, counterLabel } = useModuleListMeta({
    pagination: computed(() => props.settings),
    hasActiveFilters,
    t,
});

// ─── Drawer detalles + track recent view ─────────────────────────────────
const { open: drawerVisible, selected: selectedSetting, openDetails } = useModuleDrawer({ module: 'settings' });

// ─── Render helpers para columna `value` (varía según `type`) ────────────
const typeTagColor = (type) => ({
    string: 'blue', int: 'gold', bool: 'green', json: 'purple',
}[type] ?? 'default');

const valuePreview = (record) => {
    if (record.is_secret)              return { kind: 'secret' };
    if (record.value === null || record.value === '') return { kind: 'empty' };
    if (record.type === 'bool')        return { kind: 'bool', value: record.value === 'true' || record.value === '1' };
    if (record.type === 'json') {
        try {
            const obj = JSON.parse(record.value);
            const len = Array.isArray(obj) ? obj.length : Object.keys(obj).length;
            return { kind: 'json', value: `{ ${len} ${len === 1 ? t('settings.json_key') : t('settings.json_keys')} }` };
        } catch { return { kind: 'json_invalid' }; }
    }
    return { kind: 'plain', value: record.value };
};

// ─── Columns ─────────────────────────────────────────────────────────────
const allColumns = computed(() => settingsTableColumns(t));
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

const tablePagination = computed(() => ({
    current:  props.settings.current_page,
    pageSize: props.settings.per_page,
    total:    props.settings.total,
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
const exportableColumns = computed(() => settingsExportableColumns(t));
const exportEndpoints   = computed(() => settingsExportEndpoints());

// ─── Mobile drawers + navigation ────────────────────────────────────────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate = () => router.visit(route('system_management.settings.create'));
const goToTrash  = () => router.visit(route('system_management.settings.trash'));
const goToAudit  = () => router.visit(route('system_management.audit_logs.index', { module: 'settings' }));
const goToEdit   = (record) => router.visit(route('system_management.settings.edit',   record.slug));
const goToDelete = (record) => router.visit(route('system_management.settings.delete', record.slug));

// ─── Duplicate ───────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('system_management.settings.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Keyboard shortcuts ──────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => can('settings.create') && router.visit(route('system_management.settings.create')),
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
const tour = useModuleTour({ module: 'settings', steps: () => settingsTourSteps(t) });
</script>

<template>
    <Head :title="$t('sidebar.settings')" />

    <div>
        <div class="page-header">
            <SettingsPageHeader
                :title="$t('sidebar.settings')"
                :counter-label="counterLabel"
            />

            <ModuleToolbar
                module="settings"
                :all-columns="allColumns"
                v-model:visible-columns="visibleColumnKeys"
                :can-create="can('settings.create')"
                :can-edit="can('settings.edit')"
                :is-super="isSuper"
                :can-see-audit="canSeeAudit"
                @open-export="openExport"
                @open-import="openImport"
                @restart-tour="tour.restart()"
                :view-state="currentViewState"
                @apply-view="applySavedState"
            />
        </div>

        <SettingsBrandingCard />

        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="settings"
            data-tour="filters"
        />

        <FilterChips :fields="filterFields" v-model="filters" />

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <SettingsBulkBar
                v-if="selectedRowKeys.length > 0"
                :count="selectedRowKeys.length"
                :is-mobile="isMobileScreen"
                :bulk-activating="bulkActivating"
                :can-edit="can('settings.edit')"
                :can-delete="can('settings.delete')"
                @cancel="clearSelection"
                @set-active="bulkSetActive"
                @delete="openBulkDelete"
            />

            <TableSkeleton v-if="showSkeleton" :rows="6" :columns="visibleColumnKeys.length" />

            <ResponsiveTable
                v-else
                :dataSource="props.settings.data"
                :columns="columns"
                :pagination="tablePagination"
                :loading="tableLoading"
                :row-selection="(can('settings.delete') || can('settings.edit')) ? rowSelection : null"
                :row-class-name="(record) => isHighlighted(record.id) ? 'row-highlight' : ''"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <SettingsEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('settings.create')"
                        @clear-filters="clearFilters"
                        @open-import="openImport"
                    />
                </template>
                <template #bodyCell="{ column, record, isMobile, text }">
                    <SettingsFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :tour-target="record === props.settings.data[0]"
                        @toggle="toggleFavorite"
                    />

                    <code v-else-if="column.key === 'key'" class="setting-key">{{ record.key }}</code>

                    <Tag
                        v-else-if="column.key === 'type'"
                        :color="typeTagColor(record.type)"
                        :bordered="false"
                    >
                        {{ record.type }}
                    </Tag>

                    <template v-else-if="column.key === 'value'">
                        <Tooltip
                            v-if="valuePreview(record).kind === 'secret'"
                            :title="$t('settings.is_secret_hint')"
                        >
                            <span class="value-secret">
                                <LockOutlined /> {{ $t('settings.secret_masked') }}
                            </span>
                        </Tooltip>
                        <span v-else-if="valuePreview(record).kind === 'empty'" class="value-empty">—</span>
                        <Tag
                            v-else-if="valuePreview(record).kind === 'bool'"
                            :color="valuePreview(record).value ? 'success' : 'default'"
                            :bordered="false"
                        >
                            {{ valuePreview(record).value ? 'true' : 'false' }}
                        </Tag>
                        <Tooltip
                            v-else-if="valuePreview(record).kind === 'json'"
                            :title="record.value"
                        >
                            <code class="value-json">{{ valuePreview(record).value }}</code>
                        </Tooltip>
                        <span v-else-if="valuePreview(record).kind === 'json_invalid'" class="value-invalid">
                            <WarningOutlined /> {{ $t('settings.value_help_json') }}
                        </span>
                        <code v-else class="value-plain">{{ valuePreview(record).value }}</code>
                    </template>

                    <Tag
                        v-else-if="column.key === 'group' && record.group"
                        :bordered="false"
                    >
                        {{ record.group }}
                    </Tag>

                    <template v-else-if="column.key === 'is_secret'">
                        <Tooltip v-if="record.is_secret" :title="$t('settings.is_secret_hint')">
                            <LockOutlined class="secret-icon" />
                        </Tooltip>
                        <span v-else class="value-empty">—</span>
                    </template>

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

                    <SettingsActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('settings.edit')"
                        :can-create="can('settings.create')"
                        :can-delete="can('settings.delete')"
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

        <SettingsDetailDrawer
            v-model:open="drawerVisible"
            :setting="selectedSetting"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('settings.create')"
            :can-edit="can('settings.edit')"
            :can-delete="can('settings.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <SettingsMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('settings.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <SettingsMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            :filter-fields="filterFields"
            :can-create="can('settings.create')"
            :is-super="isSuper"
            :can-see-audit="canSeeAudit"
            @open-export="openExport"
            @open-import="openImport"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
        />

        <SettingsBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('settings.record') : $t('settings.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('settings.export_title')"
            :endpoints="exportEndpoints"
            :limits="props.exportLimits"
            :total-rows="props.settings.total ?? 0"
            :total-unfiltered="props.settings.total_unfiltered ?? props.settings.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('system_management.settings.import')"
            :template-url="route('system_management.settings.import_template')"
            :resource-label="$t('settings.records')"
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

/* Render tokens para columnas key/value/secret. */
.setting-key, .value-plain, .value-json {
    font-family: ui-monospace, 'SF Mono', Consolas, 'Liberation Mono', monospace;
    font-size: 0.8125rem;
    color: var(--color-text);
    background: var(--color-surface-alt);
    padding: 2px 6px;
    border-radius: 4px;
}
.value-secret {
    color: var(--color-text-muted);
    font-size: 0.8125rem;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.value-empty   { color: var(--color-text-muted); font-size: 0.875rem; }
.value-invalid { color: var(--color-danger); font-size: 0.8125rem; }
.secret-icon   { color: var(--color-warning); }

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
