<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, Tag, Button, Tooltip, Space } from 'ant-design-vue';
import {
    PlusOutlined, EditOutlined, InboxOutlined,
    DownloadOutlined, UploadOutlined, QuestionCircleOutlined,
    AuditOutlined, FilterOutlined, CloseCircleFilled,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ColumnSelector from '@/Components/Common/ColumnSelector.vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import AdvancedFilterDrawer from '@/Components/Common/AdvancedFilterDrawer.vue';
import FilterChips from '@/Components/Common/FilterChips.vue';
import ExportDialog from '@/Components/Common/ExportDialog.vue';
import ImportDialog from '@/Components/Common/ImportDialog.vue';
import SavedViews from '@/Components/Common/SavedViews.vue';

import InvoicesBulkBar from '@/Components/Invoices/InvoicesBulkBar.vue';
import InvoicesBulkDeleteModal from '@/Components/Invoices/InvoicesBulkDeleteModal.vue';
import InvoicesFavoriteCell from '@/Components/Invoices/InvoicesFavoriteCell.vue';
import InvoicesDetailDrawer from '@/Components/Invoices/InvoicesDetailDrawer.vue';
import InvoicesMobileBottomBar from '@/Components/Invoices/InvoicesMobileBottomBar.vue';
import InvoicesMobileDrawers from '@/Components/Invoices/InvoicesMobileDrawers.vue';
import InvoicesPageHeader from '@/Components/Invoices/InvoicesPageHeader.vue';
import InvoicesActionsCell from '@/Components/Invoices/InvoicesActionsCell.vue';
import InvoicesEmptyState from '@/Components/Invoices/InvoicesEmptyState.vue';

import { useAuth } from '@/Composables/useAuth';
import { useColumnPreferences } from '@/Composables/useColumnPreferences';
import { useModuleFilters } from '@/Composables/useModuleFilters';
import { useModuleBulkActions } from '@/Composables/useModuleBulkActions';
import { useModuleUndoToast } from '@/Composables/useModuleUndoToast';
import { useModuleFavorites } from '@/Composables/useModuleFavorites';
import { useModuleDrawer } from '@/Composables/useModuleDrawer';
import { useModuleSavedViews } from '@/Composables/useModuleSavedViews';
import { useModuleListMeta } from '@/Composables/useModuleListMeta';
import { useModuleTour } from '@/Composables/useModuleTour';
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts';
import { useViewport } from '@/Composables/useViewport';
import { useDateFormat } from '@/Composables/useDateFormat';
import { usePlanFeatures } from '@/Composables/usePlanFeatures';
import { useI18n } from '@/Plugins/i18n';

const { canUse: canUsePlanFeature } = usePlanFeatures();

import {
    invoicesFilterFields, invoicesEmptyFilters, hydrateInvoicesFilters,
    invoicesFiltersToQuery, invoicesFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { invoicesTableColumns } from './config/columns';
import { invoicesExportableColumns, invoicesExportEndpoints } from './config/exports';
import { invoicesTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDate, formatDateTime } = useDateFormat();

const props = defineProps({
    invoices:       { type: Object, required: true },
    filters:        { type: Object, default: () => ({}) },
    companyOptions: { type: Array,  default: () => [] },
    statusOptions:  { type: Array,  default: () => [] },
    filterSchema:   { type: Array,  default: () => [] },
});

const filterFields = computed(() =>
    invoicesFilterFields(t, {
        companyOptions: props.companyOptions,
        statusOptions:  props.statusOptions,
    }),
);

const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateInvoicesFilters,
    toQuery:       invoicesFiltersToQuery,
    summary:       invoicesFiltersSummary,
    empty:         invoicesEmptyFilters,
    only:          ['invoices', 'filters'],
    t,
});

const advancedFilterOpen = ref(false);
const advancedWhere = ref(Array.isArray(props.filters?.advanced_where) ? props.filters.advanced_where : []);
const advancedCount = computed(() => advancedWhere.value.length);

const applyAdvancedFilters = (clauses) => {
    advancedWhere.value = clauses;
    router.reload({
        data: {
            ...buildQueryData(filters.value),
            advanced_where: clauses.length > 0 ? JSON.stringify(clauses) : null,
        },
        only: ['invoices', 'filters'],
        preserveScroll: true,
        preserveState: true,
    });
};

const clearAdvancedFilters = () => {
    advancedWhere.value = [];
    applyAdvancedFilters([]);
};

const { counterLabel } = useModuleListMeta({
    pagination: computed(() => props.invoices),
    hasActiveFilters,
    t,
});

const allColumns = computed(() =>
    invoicesTableColumns(t, { isSuper: isSuper.value }),
);
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

const tablePagination = computed(() => ({
    current:  props.invoices.current_page,
    pageSize: props.invoices.per_page,
    total:    props.invoices.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100'],
}));

const onTableChange = (pag, _f, sorter) => {
    const sort = sorter?.field || props.filters.sort;
    const direction = sorter?.order === 'ascend' ? 'asc'
                    : sorter?.order === 'descend' ? 'desc'
                    : props.filters.direction;
    reload({ page: pag.current, per_page: pag.pageSize, sort, direction });
};

useModuleUndoToast('business_management.invoices.undo_last_delete');

const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('invoices', 'invoices');

const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { open: drawerVisible, selected: selectedInvoice, openDetails } = useModuleDrawer({ module: 'invoices' });

const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'business_management.invoices.bulk_set_active',
    bulkDeleteRoute:    'business_management.invoices.bulk_delete',
    resourceLabel:      t('invoices.records'),
});

// Bulk set status: el endpoint invoices.bulk_set_active recibe `status` (enum)
// en lugar de `is_active` (boolean). Llamamos directamente al endpoint.
const bulkSetStatus = (status) => {
    if (selectedRowKeys.value.length === 0) return;
    router.post(
        route('business_management.invoices.bulk_set_active'),
        { ids: selectedRowKeys.value, status },
        {
            preserveScroll: true,
            onSuccess: () => clearSelection(),
        },
    );
};

const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('business_management.invoices.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

const exportOpen = ref(false);
const importOpen = ref(false);
const exportableColumns = computed(() => invoicesExportableColumns(t));
const exportEndpoints   = computed(() => invoicesExportEndpoints());

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

const tour = useModuleTour({ module: 'invoices', steps: () => invoicesTourSteps(t) });

const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('business_management.invoices.create'));
const goToTrash   = () => router.visit(route('business_management.invoices.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'invoices' }));
const goToEditAll = () => router.visit(route('business_management.invoices.edit_all'));

useKeyboardShortcuts({
    'ctrl+n': () => can('invoices.create') && router.visit(route('business_management.invoices.create')),
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

const goEdit   = (record) => router.visit(route('business_management.invoices.edit',   record.slug));
const goDelete = (record) => router.visit(route('business_management.invoices.delete', record.slug));

const statusColor = (s) => ({
    draft: 'default', sent: 'blue', partial: 'orange', paid: 'green',
    overdue: 'red', cancelled: 'default', refunded: 'purple',
}[s] || 'default');

const fmtAmount = (n, code) => {
    if (n == null) return '—';
    const v = Number(n);
    if (Number.isNaN(v)) return '—';
    return (code || '') + ' ' + new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
};

const isOverdue = (record) => {
    if (!record?.due_date || !record.balance_due) return false;
    if (record.status === 'paid' || record.status === 'cancelled' || record.status === 'refunded') return false;
    return Number(record.balance_due) > 0 && new Date(record.due_date) < new Date();
};
</script>

<template>
    <Head :title="$t('invoices.plural')" />

    <div>
        <div class="page-header">
            <InvoicesPageHeader
                :title="$t('invoices.plural')"
                :counter-label="counterLabel"
            />

            <Space wrap class="hide-on-mobile">
                <span v-if="canUsePlanFeature('saved_views')" data-tour="saved-views">
                    <SavedViews
                        module="invoices"
                        :current-state="currentViewState"
                        @apply="applySavedState"
                        @default-loaded="applySavedState"
                    />
                </span>
                <span data-tour="columns">
                    <ColumnSelector
                        :columns="allColumns"
                        v-model="visibleColumnKeys"
                        storage-key="invoices"
                    />
                </span>
                <span data-tour="export-import">
                    <Tooltip :title="$t('global.export_hint')">
                        <Button @click="exportOpen = true">
                            <DownloadOutlined /> {{ $t('global.export') }}
                        </Button>
                    </Tooltip>
                    <Tooltip v-if="can('invoices.create') && canUsePlanFeature('imports')" :title="$t('global.import_hint')">
                        <Button style="margin-left: 8px;" @click="importOpen = true">
                            <UploadOutlined /> {{ $t('global.import') }}
                        </Button>
                    </Tooltip>
                </span>
                <Tooltip v-if="can('invoices.edit') && canUsePlanFeature('edit_all')" :title="$t('global.edit_all_hint')">
                    <Link :href="route('business_management.invoices.edit_all')" data-tour="edit-all">
                        <Button>
                            <EditOutlined /> {{ $t('global.edit_all') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip :title="$t('global.tour_show_again')" data-tour="tour-help">
                    <Button @click="tour.restart()">
                        <QuestionCircleOutlined />
                    </Button>
                </Tooltip>
                <Tooltip v-if="isSuper" :title="$t('global.view_deleted_hint')">
                    <Link :href="route('business_management.invoices.trash')" data-tour="trash">
                        <Button>
                            <InboxOutlined /> {{ $t('global.view_deleted') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="canSeeAudit" :title="$t('global.audit_hint')">
                    <Link :href="route('system_management.audit_logs.index', { module: 'invoices' })" data-tour="audit">
                        <Button>
                            <AuditOutlined /> {{ $t('sidebar.group_audit') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="can('invoices.create')" :title="$t('global.create_record_hint')">
                    <Link :href="route('business_management.invoices.create')">
                        <Button type="primary">
                            <PlusOutlined /> {{ $t('invoices.new') }}
                        </Button>
                    </Link>
                </Tooltip>
            </Space>
        </div>

        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="invoices"
            data-tour="filters"
        >
            <template #actions>
                <Tooltip :title="$t('global.advanced_filters_hint')">
                    <Button
                        @click="advancedFilterOpen = true"
                        :type="advancedCount > 0 ? 'primary' : 'default'"
                        class="adv-filter-btn"
                    >
                        <FilterOutlined /> {{ $t('global.advanced_filters') }}
                        <span v-if="advancedCount > 0" class="adv-filter-btn__count">{{ advancedCount }}</span>
                        <Tooltip v-if="advancedCount > 0" :title="$t('global.clear')">
                            <CloseCircleFilled
                                class="adv-filter-btn__clear"
                                @click.stop="clearAdvancedFilters"
                            />
                        </Tooltip>
                    </Button>
                </Tooltip>
            </template>
        </FilterBar>

        <div v-auto-animate>
            <FilterChips :fields="filterFields" v-model="filters" />
        </div>

        <AdvancedFilterDrawer
            v-model:open="advancedFilterOpen"
            v-model="advancedWhere"
            :schema="props.filterSchema"
            @apply="applyAdvancedFilters"
        />

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <div v-auto-animate>
                <InvoicesBulkBar
                    v-if="selectedRowKeys.length > 0"
                    :count="selectedRowKeys.length"
                    :is-mobile="isMobileScreen"
                    :bulk-activating="bulkActivating"
                    :can-edit="can('invoices.edit')"
                    :can-delete="can('invoices.delete')"
                    :status-options="props.statusOptions"
                    @cancel="clearSelection"
                    @set-status="bulkSetStatus"
                    @delete="openBulkDelete"
                />
            </div>

            <ResponsiveTable
                :dataSource="invoices.data"
                :columns="columns"
                :pagination="tablePagination"
                :row-selection="(can('invoices.delete') || can('invoices.edit')) ? rowSelection : null"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <InvoicesEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('invoices.create')"
                        @clear-filters="clearFilters"
                        @open-import="importOpen = true"
                    />
                </template>
                <template #bodyCell="{ column, record, text, isMobile }">
                    <InvoicesFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :data-tour="record === invoices.data[0] ? 'favorites' : null"
                        @toggle="toggleFavorite"
                    />

                    <template v-else-if="column.key === 'number'">
                        <strong class="mono">{{ record.number }}</strong>
                        <div v-if="record.reference" class="muted mono">{{ record.reference }}</div>
                    </template>

                    <template v-else-if="column.key === 'company'">
                        <Link v-if="record.company" :href="route('crm.companies.show', record.company.slug ?? record.company.id)">{{ record.company.name }}</Link>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Tag :color="statusColor(record.status)" :bordered="false">
                            {{ $t('invoices.status_options.' + record.status) }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'issue_date'">
                        {{ formatDate(record.issue_date) }}
                    </template>

                    <template v-else-if="column.key === 'due_date'">
                        <span :class="{ 'text-danger': isOverdue(record) }">
                            {{ formatDate(record.due_date) }}
                        </span>
                    </template>

                    <template v-else-if="column.key === 'grand_total'">
                        <strong>{{ fmtAmount(record.grand_total, record.currency_code) }}</strong>
                    </template>

                    <template v-else-if="column.key === 'amount_paid'">
                        {{ fmtAmount(record.amount_paid, record.currency_code) }}
                    </template>

                    <template v-else-if="column.key === 'balance_due'">
                        <strong :class="Number(record.balance_due) > 0 ? 'text-danger' : 'text-success'">
                            {{ fmtAmount(record.balance_due, record.currency_code) }}
                        </strong>
                    </template>

                    <template v-else-if="column.key === 'tenant'">
                        <Tag v-if="record.tenant" color="blue" :bordered="false">
                            {{ record.tenant.name }}
                        </Tag>
                        <Tag v-else color="purple" :bordered="false">{{ $t('global.platform') }}</Tag>
                    </template>

                    <template v-else-if="column.key === 'created_at'">
                        {{ formatDateTime(record.created_at) }}
                    </template>

                    <InvoicesActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('invoices.edit')"
                        :can-create="can('invoices.create')"
                        :can-delete="can('invoices.delete')"
                        :duplicating-id="duplicating"
                        @edit="goEdit"
                        @duplicate="duplicate"
                        @delete="goDelete"
                    />

                    <template v-else>{{ text ?? record[column.dataIndex] ?? '' }}</template>
                </template>
            </ResponsiveTable>
        </Card>

        <InvoicesDetailDrawer
            v-model:open="drawerVisible"
            :invoice="selectedInvoice"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('invoices.create')"
            :can-edit="can('invoices.edit')"
            :can-delete="can('invoices.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <InvoicesBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('invoices.record') : $t('invoices.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('invoices.export_title')"
            :endpoints="exportEndpoints"
            :total-rows="invoices.total ?? 0"
            :total-unfiltered="invoices.total_unfiltered ?? invoices.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('business_management.invoices.import')"
            :template-url="route('business_management.invoices.import_template')"
            :resource-label="$t('invoices.records')"
        />

        <InvoicesMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('invoices.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <InvoicesMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
            :can-create="can('invoices.create')"
            :can-edit="can('invoices.edit')"
            :is-super="isSuper"
            :can-see-audit="canSeeAudit"
            :advanced-count="advancedCount"
            @open-advanced="advancedFilterOpen = true"
            @clear-advanced="clearAdvancedFilters"
            @open-export="exportOpen = true"
            @open-import="importOpen = true"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
            @go-edit-all="goToEditAll"
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

.mono { font-family: ui-monospace, Consolas, monospace; font-size: 0.8125rem; }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }

.text-danger  { color: #d4380d; }
.text-success { color: #389e0d; }

.grid-card { border-radius: 6px; }
.grid-card :deep(.ant-table-thead > tr > th) {
    background: var(--color-surface-alt);
    color: var(--color-text-strong);
    font-weight: 600;
    font-size: 0.8125rem;
    position: sticky;
    top: 0;
    z-index: 2;
}
.grid-card :deep(.ant-table-tbody > tr:hover > td) { background: var(--color-surface-hover) !important; }

.grid-card :deep(.ant-table-tbody .row-actions-desktop) {
    opacity: 0.45;
    transition: opacity 0.15s ease;
}
.grid-card :deep(.ant-table-tbody > tr:hover .row-actions-desktop),
.grid-card :deep(.ant-table-tbody .row-actions-desktop:focus-within) {
    opacity: 1;
}

.grid-card:has(.bulk-bar--mobile-sticky) {
    padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 80px);
}

@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: stretch; }
    .hide-on-mobile { display: none !important; }
}

.adv-filter-btn { display: inline-flex; align-items: center; gap: 6px; }
.adv-filter-btn__count {
    display: inline-flex; align-items: center; justify-content: center;
    min-width: 18px; height: 18px; padding: 0 6px; border-radius: 9px;
    background: rgba(255, 255, 255, 0.25); font-size: 0.7rem; font-weight: 600; line-height: 1;
}
.adv-filter-btn__clear {
    font-size: 14px; opacity: 0.7; cursor: pointer;
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.adv-filter-btn__clear:hover { opacity: 1; transform: scale(1.12); }
</style>

<style>
@media (max-width: 767.98px) {
    .below-shell .content {
        padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 150px) !important;
    }
}
</style>
