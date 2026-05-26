<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Card, Tag, Tooltip, Switch } from 'ant-design-vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

import AppLayout from '@/Layouts/AppLayout.vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import FilterChips from '@/Components/Common/FilterChips.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import TableSkeleton from '@/Components/Common/TableSkeleton.vue';
import ModuleToolbar from '@/Components/Common/ModuleToolbar.vue';

import AutomationsPageHeader from '@/Components/Automations/AutomationsPageHeader.vue';
import AutomationsBulkBar from '@/Components/Automations/AutomationsBulkBar.vue';
import AutomationsBulkDeleteModal from '@/Components/Automations/AutomationsBulkDeleteModal.vue';
import AutomationsDetailDrawer from '@/Components/Automations/AutomationsDetailDrawer.vue';
import AutomationsMobileBottomBar from '@/Components/Automations/AutomationsMobileBottomBar.vue';
import AutomationsMobileDrawers from '@/Components/Automations/AutomationsMobileDrawers.vue';
import AutomationsEmptyState from '@/Components/Automations/AutomationsEmptyState.vue';
import AutomationsFavoriteCell from '@/Components/Automations/AutomationsFavoriteCell.vue';
import AutomationsActionsCell from '@/Components/Automations/AutomationsActionsCell.vue';
import ExportDialog from '@/Components/Common/ExportDialog.vue';
import ImportDialog from '@/Components/Common/ImportDialog.vue';

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
    automationsFilterFields, automationsEmptyFilters, hydrateAutomationsFilters,
    automationsFiltersToQuery, automationsFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { automationsTableColumns } from './config/columns';
import { automationsTourSteps } from './config/tour';
import { automationsExportableColumns, automationsExportEndpoints } from './config/exports';

defineOptions({ layout: AppLayout });

const props = defineProps({
    automations:  { type: Object, required: true },
    catalog:      { type: Object, default: () => ({ data_sources: [], actions: [] }) },
    filters:      { type: Object, required: true },
    exportLimits: { type: Object, default: () => ({}) },
});

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTime } = useDateFormat();

// ─── Viewport + loading ──────────────────────────────────────────────────
const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { loading: tableLoading } = usePageLoading('/automation_management/automations', 'automations');

// ─── Filters (composable + config) ───────────────────────────────────────
const filterFields = computed(() => automationsFilterFields(t, props.catalog));
const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateAutomationsFilters,
    toQuery:       automationsFiltersToQuery,
    summary:       automationsFiltersSummary,
    empty:         automationsEmptyFilters,
    only:          ['automations', 'filters'],
    t,
});

const showSkeleton = computed(() =>
    tableLoading.value && (!props.automations?.data || props.automations.data.length === 0)
);

// ─── Cross-module composables ────────────────────────────────────────────
// Permisos: automations no usa Spatie permissions explícitos (gating es por
// plan_feature). Asumimos que si el usuario llegó acá puede crear/editar/borrar
// salvo trash (super). Esa convención sigue las rutas.
const canManage = computed(() => true);
const canDestroy = computed(() => true);

const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('automations', 'automations');
useModuleUndoToast('automation_management.automations.undo_last_delete');

const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, bulkSetActive, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'automation_management.automations.bulk_set_active',
    bulkDeleteRoute:    'automation_management.automations.bulk_delete',
    resourceLabel:      t('automations.records'),
});

const { isHighlighted, counterLabel } = useModuleListMeta({
    pagination: computed(() => props.automations),
    hasActiveFilters,
    t,
});

// ─── Drawer detalles + track recent view ─────────────────────────────────
const { open: drawerVisible, selected: selectedAutomation, openDetails } = useModuleDrawer({ module: 'automations' });

// ─── Export / Import dialogs ─────────────────────────────────────────────
const exportOpen = ref(false);
const importOpen = ref(false);
const exportableColumns = computed(() => automationsExportableColumns(t));
const exportEndpoints   = computed(() => automationsExportEndpoints());

// ─── Columns ─────────────────────────────────────────────────────────────
// Pasamos `isSuper` para inyectar la columna "workspace" solo al super,
// que necesita distinguir automatizaciones de distintos tenants.
const allColumns = computed(() => automationsTableColumns(t, { isSuper: isSuper.value }));
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

const tablePagination = computed(() => ({
    current:  props.automations.current_page,
    pageSize: props.automations.per_page,
    total:    props.automations.total,
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

// ─── Mobile drawers + navigation ────────────────────────────────────────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('automation_management.automations.create'));
const goToTrash   = () => router.visit(route('automation_management.automations.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'automations' }));
const goToEditAll = () => router.visit(route('automation_management.automations.edit_all'));
const goToEdit   = (record) => router.visit(route('automation_management.automations.edit', record.id));
const goToDelete = (record) => router.visit(route('automation_management.automations.delete', record.id));

// ─── Duplicate ───────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('automation_management.automations.duplicate', record.id), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Toggle is_active inline ─────────────────────────────────────────────
const toggle = (record) => {
    router.post(route('automation_management.automations.toggle', record.id), {}, {
        preserveScroll: true,
    });
};

// ─── Run now ─────────────────────────────────────────────────────────────
const runNow = (record) => {
    router.post(route('automation_management.automations.run_now', record.id), {}, {
        preserveScroll: true,
    });
};

// ─── Helpers para celdas ────────────────────────────────────────────────
const sourceLabel = (key) =>
    props.catalog.data_sources?.find(s => s.key === key)?.label ?? key ?? '—';
const actionLabel = (key) =>
    props.catalog.actions?.find(a => a.key === key)?.label ?? key ?? '—';

const triggerSummary = (auto) => {
    const c = auto.trigger_config ?? {};
    switch (c.kind) {
        case 'daily':   return `${t('automations.trigger_kind_daily')} · ${c.time ?? '09:00'}`;
        case 'weekly':  return `${t('automations.trigger_kind_weekly')} · ${c.time ?? '09:00'}`;
        case 'monthly': return `${t('automations.trigger_kind_monthly')} · ${c.time ?? '09:00'}`;
        case 'cron':    return `cron: ${c.expression}`;
        default:        return '—';
    }
};

const fmt = (d) => d ? dayjs(d).format('YYYY-MM-DD HH:mm') : '—';
const fmtRel = (d) => d ? dayjs(d).fromNow() : '—';

// ─── Keyboard shortcuts ──────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => canManage.value && router.visit(route('automation_management.automations.create')),
    'esc': () => {
        if (drawerVisible.value)          drawerVisible.value = false;
        else if (bulkOpen.value)          bulkOpen.value = false;
        else if (exportOpen.value)        exportOpen.value = false;
        else if (importOpen.value)        importOpen.value = false;
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
const tour = useModuleTour({ module: 'automations', steps: () => automationsTourSteps(t) });
</script>

<template>
    <Head :title="$t('automations.index_title')" />

    <div>
        <div class="page-header">
            <AutomationsPageHeader
                :title="$t('automations.index_title')"
                :counter-label="counterLabel"
            />

            <ModuleToolbar
                module="automations"
                route-prefix="automation_management"
                :show-export-import="true"
                :all-columns="allColumns"
                v-model:visible-columns="visibleColumnKeys"
                :can-create="canManage"
                :can-edit="canManage"
                :is-super="isSuper"
                :can-see-audit="canSeeAudit"
                @open-export="exportOpen = true"
                @open-import="importOpen = true"
                @restart-tour="tour.restart()"
                :view-state="currentViewState"
                @apply-view="applySavedState"
            />
        </div>

        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="automations"
            data-tour="filters"
        />

        <div v-auto-animate>
            <FilterChips :fields="filterFields" v-model="filters" />
        </div>

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <div v-auto-animate>
                <AutomationsBulkBar
                    v-if="selectedRowKeys.length > 0"
                    :count="selectedRowKeys.length"
                    :is-mobile="isMobileScreen"
                    :bulk-activating="bulkActivating"
                    :can-edit="canManage"
                    :can-delete="canDestroy"
                    @cancel="clearSelection"
                    @set-active="bulkSetActive"
                    @delete="openBulkDelete"
                />
            </div>

            <TableSkeleton v-if="showSkeleton" :rows="6" :columns="visibleColumnKeys.length" />

            <ResponsiveTable
                v-else
                :dataSource="props.automations.data"
                :columns="columns"
                :pagination="tablePagination"
                :loading="tableLoading"
                :row-selection="(canDestroy || canManage) ? rowSelection : null"
                :row-class-name="(record) => isHighlighted(record.id) ? 'row-highlight' : ''"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <AutomationsEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="canManage"
                        @clear-filters="clearFilters"
                    />
                </template>
                <template #bodyCell="{ column, record, isMobile, text }">
                    <AutomationsFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :tour-target="record === props.automations.data[0]"
                        @toggle="toggleFavorite"
                    />

                    <template v-else-if="column.key === 'name'">
                        <strong>{{ record.name }}</strong>
                        <div v-if="record.description" class="muted">{{ record.description }}</div>
                    </template>

                    <template v-else-if="column.key === 'workspace'">
                        <Tag v-if="record.tenant" color="blue" :bordered="false">
                            {{ record.tenant.name }}
                        </Tag>
                        <Tag v-else color="purple" :bordered="false">
                            {{ $t('global.platform') }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'trigger'">
                        {{ triggerSummary(record) }}
                    </template>

                    <template v-else-if="column.key === 'action'">
                        <Tag :bordered="false">{{ actionLabel(record.action_type) }}</Tag>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Switch
                            :checked="record.is_active"
                            size="small"
                            @click.stop
                            @change="toggle(record)"
                        />
                    </template>

                    <template v-else-if="column.key === 'next_run'">
                        <Tooltip v-if="record.next_run_at" :title="fmt(record.next_run_at)">
                            {{ fmtRel(record.next_run_at) }}
                        </Tooltip>
                        <span v-else class="muted">{{ $t('automations.next_run_none') }}</span>
                    </template>

                    <template v-else-if="column.key === 'runs'">
                        <Tag :color="record.runs_count > 0 ? 'cyan' : 'default'" :bordered="false">
                            {{ record.runs_count }}
                        </Tag>
                        <Tag v-if="record.failures_count > 0" color="error" :bordered="false">
                            {{ record.failures_count }} fail
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'created_at'">
                        {{ formatDateTime(record.created_at) }}
                    </template>

                    <AutomationsActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="canManage"
                        :can-create="canManage"
                        :can-delete="canDestroy"
                        :duplicating-id="duplicating"
                        @edit="goToEdit"
                        @duplicate="duplicate"
                        @delete="goToDelete"
                        @run-now="runNow"
                    />

                    <template v-else>
                        {{ text ?? record[column.dataIndex] ?? '' }}
                    </template>
                </template>
            </ResponsiveTable>
        </Card>

        <AutomationsDetailDrawer
            v-model:open="drawerVisible"
            :automation="selectedAutomation"
            :catalog="catalog"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="canManage"
            :can-edit="canManage"
            :can-delete="canDestroy"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
            @run-now="runNow"
            @toggle="toggle"
        />

        <AutomationsMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :is-super="isSuper"
            @create="goToCreate"
            @go-trash="goToTrash"
        />

        <AutomationsMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
            :can-create="canManage"
            :can-edit="canManage"
            :is-super="isSuper"
            :can-see-audit="canSeeAudit"
            @open-export="exportOpen = true"
            @open-import="importOpen = true"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
            @go-edit-all="goToEditAll"
        />

        <AutomationsBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('automations.record') : $t('automations.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('automations.export_title')"
            :endpoints="exportEndpoints"
            :total-rows="props.automations.total ?? 0"
            :total-unfiltered="props.automations.total_unfiltered ?? props.automations.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('automation_management.automations.import')"
            :template-url="route('automation_management.automations.import_template')"
            :resource-label="$t('automations.records')"
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

.muted { color: var(--color-text-muted); font-size: 0.8125rem; }

.grid-card :deep(.ant-table-thead > tr > th) {
    background: var(--color-surface-alt);
    color: var(--color-text-strong);
    font-weight: 600;
    font-size: 0.8125rem;
}
/* Animaciones de .grid-card (row stagger fade-in, hover lift, empty breathe,
   hover-to-reveal de acciones) viven globalmente en resources/css/app.css. */

.grid-card:has(.bulk-bar--mobile-sticky) {
    padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 80px);
}

@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: stretch; }
}
</style>

<style>
@media (max-width: 767.98px) {
    .below-shell .content {
        padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 150px) !important;
    }
}
</style>
