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

import SalesOrdersBulkBar from '@/Components/SalesOrders/SalesOrdersBulkBar.vue';
import SalesOrdersBulkDeleteModal from '@/Components/SalesOrders/SalesOrdersBulkDeleteModal.vue';
import SalesOrdersFavoriteCell from '@/Components/SalesOrders/SalesOrdersFavoriteCell.vue';
import SalesOrdersDetailDrawer from '@/Components/SalesOrders/SalesOrdersDetailDrawer.vue';
import SalesOrdersMobileBottomBar from '@/Components/SalesOrders/SalesOrdersMobileBottomBar.vue';
import SalesOrdersMobileDrawers from '@/Components/SalesOrders/SalesOrdersMobileDrawers.vue';
import SalesOrdersPageHeader from '@/Components/SalesOrders/SalesOrdersPageHeader.vue';
import SalesOrdersActionsCell from '@/Components/SalesOrders/SalesOrdersActionsCell.vue';
import SalesOrdersEmptyState from '@/Components/SalesOrders/SalesOrdersEmptyState.vue';

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
    salesOrdersFilterFields, salesOrdersEmptyFilters, hydrateSalesOrdersFilters,
    salesOrdersFiltersToQuery, salesOrdersFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { salesOrdersTableColumns } from './config/columns';
import { salesOrdersExportableColumns, salesOrdersExportEndpoints } from './config/exports';
import { salesOrdersTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDate, formatDateTime } = useDateFormat();

const props = defineProps({
    orders:               { type: Object, required: true },
    filters:              { type: Object, default: () => ({}) },
    companyOptions:       { type: Array,  default: () => [] },
    warehouseOptions:     { type: Array,  default: () => [] },
    statusOptions:        { type: Array,  default: () => [] },
    paymentStatusOptions: { type: Array,  default: () => [] },
    filterSchema:         { type: Array,  default: () => [] },
});

const filterFields = computed(() =>
    salesOrdersFilterFields(t, {
        companyOptions:       props.companyOptions,
        warehouseOptions:     props.warehouseOptions,
        statusOptions:        props.statusOptions,
        paymentStatusOptions: props.paymentStatusOptions,
    }),
);

const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateSalesOrdersFilters,
    toQuery:       salesOrdersFiltersToQuery,
    summary:       salesOrdersFiltersSummary,
    empty:         salesOrdersEmptyFilters,
    only:          ['orders', 'filters'],
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
        only: ['orders', 'filters'],
        preserveScroll: true,
        preserveState: true,
    });
};

const clearAdvancedFilters = () => {
    advancedWhere.value = [];
    applyAdvancedFilters([]);
};

const { counterLabel } = useModuleListMeta({
    pagination: computed(() => props.orders),
    hasActiveFilters,
    t,
});

const allColumns = computed(() =>
    salesOrdersTableColumns(t, { isSuper: isSuper.value }),
);
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

const tablePagination = computed(() => ({
    current:  props.orders.current_page,
    pageSize: props.orders.per_page,
    total:    props.orders.total,
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

useModuleUndoToast('business_management.sales_orders.undo_last_delete');

const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('sales_orders', 'sales_orders');

const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { open: drawerVisible, selected: selectedOrder, openDetails } = useModuleDrawer({ module: 'sales_orders' });

const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'business_management.sales_orders.bulk_set_active',
    bulkDeleteRoute:    'business_management.sales_orders.bulk_delete',
    resourceLabel:      t('sales_orders.records'),
});

// Bulk set status: el endpoint sales_orders.bulk_set_active recibe status (enum)
// en lugar de is_active (boolean). Llamamos directamente al endpoint.
const bulkSetStatus = (status) => {
    if (selectedRowKeys.value.length === 0) return;
    router.post(
        route('business_management.sales_orders.bulk_set_active'),
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
    router.post(route('business_management.sales_orders.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

const exportOpen = ref(false);
const importOpen = ref(false);
const exportableColumns = computed(() => salesOrdersExportableColumns(t));
const exportEndpoints   = computed(() => salesOrdersExportEndpoints());

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

const tour = useModuleTour({ module: 'sales_orders', steps: () => salesOrdersTourSteps(t) });

const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('business_management.sales_orders.create'));
const goToTrash   = () => router.visit(route('business_management.sales_orders.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'sales_orders' }));
const goToEditAll = () => router.visit(route('business_management.sales_orders.edit_all'));

useKeyboardShortcuts({
    'ctrl+n': () => can('sales_orders.create') && router.visit(route('business_management.sales_orders.create')),
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

const goEdit   = (record) => router.visit(route('business_management.sales_orders.edit',   record.slug));
const goDelete = (record) => router.visit(route('business_management.sales_orders.delete', record.slug));

const statusColor = (s) => ({
    pending: 'default', processing: 'blue', partially_shipped: 'cyan',
    shipped: 'green', delivered: 'green', cancelled: 'red', closed: 'default',
}[s] || 'default');

const paymentStatusColor = (s) => ({
    unpaid: 'default', partial: 'orange', paid: 'green', overdue: 'red',
}[s] || 'default');

const fmtAmount = (n, code) => {
    if (n == null) return '—';
    const v = Number(n);
    if (Number.isNaN(v)) return '—';
    return (code || '') + ' ' + new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
};
</script>

<template>
    <Head :title="$t('sales_orders.plural')" />

    <div>
        <div class="page-header">
            <SalesOrdersPageHeader
                :title="$t('sales_orders.plural')"
                :counter-label="counterLabel"
            />

            <Space wrap class="hide-on-mobile">
                <span v-if="canUsePlanFeature('saved_views')" data-tour="saved-views">
                    <SavedViews
                        module="sales_orders"
                        :current-state="currentViewState"
                        @apply="applySavedState"
                        @default-loaded="applySavedState"
                    />
                </span>
                <span data-tour="columns">
                    <ColumnSelector
                        :columns="allColumns"
                        v-model="visibleColumnKeys"
                        storage-key="sales_orders"
                    />
                </span>
                <span data-tour="export-import">
                    <Tooltip :title="$t('global.export_hint')">
                        <Button @click="exportOpen = true">
                            <DownloadOutlined /> {{ $t('global.export') }}
                        </Button>
                    </Tooltip>
                    <Tooltip v-if="can('sales_orders.create') && canUsePlanFeature('imports')" :title="$t('global.import_hint')">
                        <Button style="margin-left: 8px;" @click="importOpen = true">
                            <UploadOutlined /> {{ $t('global.import') }}
                        </Button>
                    </Tooltip>
                </span>
                <Tooltip v-if="can('sales_orders.edit') && canUsePlanFeature('edit_all')" :title="$t('global.edit_all_hint')">
                    <Link :href="route('business_management.sales_orders.edit_all')" data-tour="edit-all">
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
                    <Link :href="route('business_management.sales_orders.trash')" data-tour="trash">
                        <Button>
                            <InboxOutlined /> {{ $t('global.view_deleted') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="canSeeAudit" :title="$t('global.audit_hint')">
                    <Link :href="route('system_management.audit_logs.index', { module: 'sales_orders' })" data-tour="audit">
                        <Button>
                            <AuditOutlined /> {{ $t('sidebar.group_audit') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="can('sales_orders.create')" :title="$t('global.create_record_hint')">
                    <Link :href="route('business_management.sales_orders.create')">
                        <Button type="primary">
                            <PlusOutlined /> {{ $t('sales_orders.new') }}
                        </Button>
                    </Link>
                </Tooltip>
            </Space>
        </div>

        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="sales_orders"
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
                <SalesOrdersBulkBar
                    v-if="selectedRowKeys.length > 0"
                    :count="selectedRowKeys.length"
                    :is-mobile="isMobileScreen"
                    :bulk-activating="bulkActivating"
                    :can-edit="can('sales_orders.edit')"
                    :can-delete="can('sales_orders.delete')"
                    :status-options="props.statusOptions"
                    @cancel="clearSelection"
                    @set-status="bulkSetStatus"
                    @delete="openBulkDelete"
                />
            </div>

            <ResponsiveTable
                :dataSource="orders.data"
                :columns="columns"
                :pagination="tablePagination"
                :row-selection="(can('sales_orders.delete') || can('sales_orders.edit')) ? rowSelection : null"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <SalesOrdersEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('sales_orders.create')"
                        @clear-filters="clearFilters"
                        @open-import="importOpen = true"
                    />
                </template>
                <template #bodyCell="{ column, record, text, isMobile }">
                    <SalesOrdersFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :data-tour="record === orders.data[0] ? 'favorites' : null"
                        @toggle="toggleFavorite"
                    />

                    <template v-else-if="column.key === 'reference'">
                        <strong class="mono">{{ record.reference }}</strong>
                    </template>

                    <template v-else-if="column.key === 'company'">
                        <Link v-if="record.company" :href="route('crm.companies.show', record.company.slug ?? record.company.id)">{{ record.company.name }}</Link>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'warehouse'">
                        <Tag v-if="record.warehouse" :bordered="false">
                            {{ record.warehouse.code }} · {{ record.warehouse.name }}
                        </Tag>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Tag :color="statusColor(record.status)" :bordered="false">
                            {{ $t('sales_orders.status_options.' + record.status) }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'payment_status'">
                        <Tag :color="paymentStatusColor(record.payment_status)" :bordered="false">
                            {{ $t('sales_orders.payment_status_options.' + record.payment_status) }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'order_date'">
                        <span v-if="record.order_date">{{ formatDate(record.order_date) }}</span>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'grand_total'">
                        <strong>{{ fmtAmount(record.grand_total, record.currency_code) }}</strong>
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

                    <SalesOrdersActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('sales_orders.edit')"
                        :can-create="can('sales_orders.create')"
                        :can-delete="can('sales_orders.delete')"
                        :duplicating-id="duplicating"
                        @edit="goEdit"
                        @duplicate="duplicate"
                        @delete="goDelete"
                    />

                    <template v-else>{{ text ?? record[column.dataIndex] ?? '' }}</template>
                </template>
            </ResponsiveTable>
        </Card>

        <SalesOrdersDetailDrawer
            v-model:open="drawerVisible"
            :order="selectedOrder"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('sales_orders.create')"
            :can-edit="can('sales_orders.edit')"
            :can-delete="can('sales_orders.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <SalesOrdersBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('sales_orders.record') : $t('sales_orders.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('sales_orders.export_title')"
            :endpoints="exportEndpoints"
            :total-rows="orders.total ?? 0"
            :total-unfiltered="orders.total_unfiltered ?? orders.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('business_management.sales_orders.import')"
            :template-url="route('business_management.sales_orders.import_template')"
            :resource-label="$t('sales_orders.records')"
        />

        <SalesOrdersMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('sales_orders.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <SalesOrdersMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
            :can-create="can('sales_orders.create')"
            :can-edit="can('sales_orders.edit')"
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

.grid-card { border-radius: 6px; }
.grid-card :deep(.ant-table-thead > tr > th) {
    background: var(--color-surface-alt);
    color: var(--color-text-strong);
    font-weight: 600;
    font-size: 0.8125rem;
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
