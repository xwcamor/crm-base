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

import PaymentsBulkBar from '@/Components/Payments/PaymentsBulkBar.vue';
import PaymentsBulkDeleteModal from '@/Components/Payments/PaymentsBulkDeleteModal.vue';
import PaymentsFavoriteCell from '@/Components/Payments/PaymentsFavoriteCell.vue';
import PaymentsDetailDrawer from '@/Components/Payments/PaymentsDetailDrawer.vue';
import PaymentsMobileBottomBar from '@/Components/Payments/PaymentsMobileBottomBar.vue';
import PaymentsMobileDrawers from '@/Components/Payments/PaymentsMobileDrawers.vue';
import PaymentsPageHeader from '@/Components/Payments/PaymentsPageHeader.vue';
import PaymentsActionsCell from '@/Components/Payments/PaymentsActionsCell.vue';
import PaymentsEmptyState from '@/Components/Payments/PaymentsEmptyState.vue';

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

// Gate per plan: saved_views requiere basic+, imports/edit_all requieren pro+.
// El toolbar inline de Payments (no usa ModuleToolbar) repite manualmente
// estos gates para no mostrar botones que no funcionan al user free/basic.
const { canUse: canUsePlanFeature } = usePlanFeatures();

import {
    paymentsFilterFields, paymentsEmptyFilters, hydratePaymentsFilters,
    paymentsFiltersToQuery, paymentsFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { paymentsTableColumns } from './config/columns';
import { paymentsExportableColumns, paymentsExportEndpoints } from './config/exports';
import { paymentsTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTime } = useDateFormat();

const props = defineProps({
    payments:        { type: Object, required: true },
    filters:         { type: Object, default: () => ({}) },
    statusOptions:   { type: Array,  default: () => [] },
    typeOptions:     { type: Array,  default: () => [] },
    methodOptions:   { type: Array,  default: () => [] },
    filterSchema:    { type: Array,  default: () => [] },
});

// ─── Filtros (schema + (de)serializacion en config/filters.js) ──────────────
const filterFields = computed(() =>
    paymentsFilterFields(t, {
        statusOptions: props.statusOptions,
        typeOptions:   props.typeOptions,
        methodOptions: props.methodOptions,
    }),
);

const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydratePaymentsFilters,
    toQuery:       paymentsFiltersToQuery,
    summary:       paymentsFiltersSummary,
    empty:         paymentsEmptyFilters,
    only:          ['payments', 'filters'],
    t,
});

// ─── Filtros avanzados (drawer con query builder dinamico) ──────────────────
// Persistido via Inertia (filters.advanced_where) para sobrevivir paginate/sort
// sin perder el filtro aplicado.
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
        only: ['payments', 'filters'],
        preserveScroll: true,
        preserveState: true,
    });
};

const clearAdvancedFilters = () => {
    advancedWhere.value = [];
    applyAdvancedFilters([]);
};

// ─── Contador adaptativo "X registros" / "X de Y registros" ────────────────
const { counterLabel } = useModuleListMeta({
    pagination: computed(() => props.payments),
    hasActiveFilters,
    t,
});

// ─── Columnas (schema en config/columns.js) ─────────────────────────────────
const allColumns = computed(() =>
    paymentsTableColumns(t, { isSuper: isSuper.value }),
);
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

// ─── Paginacion + sort ──────────────────────────────────────────────────────
const tablePagination = computed(() => ({
    current:  props.payments.current_page,
    pageSize: props.payments.per_page,
    total:    props.payments.total,
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

// ─── Undo toast (60s window) ────────────────────────────────────────────────
useModuleUndoToast('business_management.payments.undo_last_delete');

// ─── Favoritos polimorficos ────────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('payments', 'payments');

// ─── Detail drawer + recent views tracking ─────────────────────────────────
const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { open: drawerVisible, selected: selectedPayment, openDetails } = useModuleDrawer({ module: 'payments' });

// ─── Bulk ───────────────────────────────────────────────────────────────────
// Payments no tiene `is_active` (status es enum), asi que solo usamos
// bulk_delete. `bulkSetActive` queda disponible en el composable pero
// el bulk bar no lo expone.
const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError,
    openBulkDelete, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'business_management.payments.bulk_set_active',
    bulkDeleteRoute:    'business_management.payments.bulk_delete',
    resourceLabel:      t('payments.records'),
});

// ─── Duplicate ──────────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('business_management.payments.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Export / Import (columnas + endpoints en config/exports.js) ────────────
const exportOpen = ref(false);
const importOpen = ref(false);
const exportableColumns = computed(() => paymentsExportableColumns(t));
const exportEndpoints   = computed(() => paymentsExportEndpoints());

// ─── Saved Views (filtros + columnas + sort persistidos por usuario) ──────
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

// ─── Onboarding tour (pasos en config/tour.js) ──────────────────────────────
const tour = useModuleTour({ module: 'payments', steps: () => paymentsTourSteps(t) });

// ─── Mobile: drawers + navegacion (patron Regions: app-like en mobile) ──────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('business_management.payments.create'));
const goToTrash   = () => router.visit(route('business_management.payments.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'payments' }));
const goToEditAll = () => router.visit(route('business_management.payments.edit_all'));

// ─── Keyboard shortcuts ────────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => can('payments.create') && router.visit(route('business_management.payments.create')),
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

// ─── Acciones ───────────────────────────────────────────────────────────────
const goEdit   = (record) => router.visit(route('business_management.payments.edit',   record.slug));
const goDelete = (record) => router.visit(route('business_management.payments.delete', record.slug));

// ─── Helpers de display por celda ──────────────────────────────────────────
// Colores del Tag por estado del pago.
const statusColor = {
    pending:   'gold',
    completed: 'green',
    failed:    'red',
    refunded:  'purple',
    disputed:  'orange',
};

const statusLabelFor = (value) => {
    if (!value) return '';
    const opt = props.statusOptions.find(o => o.value === value);
    return opt?.label ?? t('payments.status_options.' + value);
};

const typeLabelFor = (value) => {
    if (!value) return '';
    const opt = props.typeOptions.find(o => o.value === value);
    return opt?.label ?? t('payments.type_options.' + value);
};

// Formato monetario: "USD 1,234.56" — locale 'es' para separadores,
// el codigo de moneda va antepuesto (mas claro para multi-currency).
const amountFormatter = new Intl.NumberFormat('es', {
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
});
const formatAmount = (amount, code) => {
    if (amount == null) return '—';
    const v = Number(amount);
    if (Number.isNaN(v)) return '—';
    return `${code || ''} ${amountFormatter.format(v)}`.trim();
};
</script>

<template>
    <Head :title="$t('payments.plural')" />

    <div>
        <div class="page-header">
            <PaymentsPageHeader
                :title="$t('payments.plural')"
                :counter-label="counterLabel"
            />

            <!-- Toolbar desktop. En mobile se oculta — las acciones viven
                 en el bottom bar + drawers (patron app real, como Regions). -->
            <Space wrap class="hide-on-mobile">
                <span v-if="canUsePlanFeature('saved_views')" data-tour="saved-views">
                    <SavedViews
                        module="payments"
                        :current-state="currentViewState"
                        @apply="applySavedState"
                        @default-loaded="applySavedState"
                    />
                </span>
                <span data-tour="columns">
                    <ColumnSelector
                        :columns="allColumns"
                        v-model="visibleColumnKeys"
                        storage-key="payments"
                    />
                </span>
                <span data-tour="export-import">
                    <Tooltip :title="$t('global.export_hint')">
                        <Button @click="exportOpen = true">
                            <DownloadOutlined /> {{ $t('global.export') }}
                        </Button>
                    </Tooltip>
                    <Tooltip v-if="can('payments.create') && canUsePlanFeature('imports')" :title="$t('global.import_hint')">
                        <Button style="margin-left: 8px;" @click="importOpen = true">
                            <UploadOutlined /> {{ $t('global.import') }}
                        </Button>
                    </Tooltip>
                </span>
                <Tooltip v-if="can('payments.edit') && canUsePlanFeature('edit_all')" :title="$t('global.edit_all_hint')">
                    <Link :href="route('business_management.payments.edit_all')" data-tour="edit-all">
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
                    <Link :href="route('business_management.payments.trash')" data-tour="trash">
                        <Button>
                            <InboxOutlined /> {{ $t('global.view_deleted') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="canSeeAudit" :title="$t('global.audit_hint')">
                    <Link :href="route('system_management.audit_logs.index', { module: 'payments' })" data-tour="audit">
                        <Button>
                            <AuditOutlined /> {{ $t('sidebar.group_audit') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="can('payments.create')" :title="$t('global.create_record_hint')">
                    <Link :href="route('business_management.payments.create')">
                        <Button type="primary">
                            <PlusOutlined /> {{ $t('payments.new') }}
                        </Button>
                    </Link>
                </Tooltip>
            </Space>
        </div>

        <!-- FilterBar: desktop inline. En mobile vive en el drawer de Filtros.
             Slot `actions` inyecta el boton "Filtros avanzados" al final
             del bar, agrupado visualmente con Configurar/Limpiar. -->
        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="payments"
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
                <PaymentsBulkBar
                    v-if="selectedRowKeys.length > 0"
                    :count="selectedRowKeys.length"
                    :is-mobile="isMobileScreen"
                    :can-delete="can('payments.delete')"
                    @cancel="clearSelection"
                    @delete="openBulkDelete"
                />
            </div>

            <ResponsiveTable
                :dataSource="payments.data"
                :columns="columns"
                :pagination="tablePagination"
                :row-selection="can('payments.delete') ? rowSelection : null"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <PaymentsEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('payments.create')"
                        @clear-filters="clearFilters"
                        @open-import="importOpen = true"
                    />
                </template>
                <template #bodyCell="{ column, record, text, isMobile }">
                    <PaymentsFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :data-tour="record === payments.data[0] ? 'favorites' : null"
                        @toggle="toggleFavorite"
                    />

                    <template v-else-if="column.key === 'reference'">
                        <code v-if="record.reference" class="mono">{{ record.reference }}</code>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'paid_at'">
                        {{ record.paid_at ? formatDateTime(record.paid_at) : '—' }}
                    </template>

                    <template v-else-if="column.key === 'company'">
                        <Link v-if="record.company" :href="route('crm.companies.show', record.company.slug ?? record.company.id)">{{ record.company.name }}</Link>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'invoice'">
                        <Link
                            v-if="record.invoice"
                            :href="route('business_management.invoices.show', record.invoice.slug ?? record.invoice.id)"
                            class="link-quiet"
                            @click.stop
                        >
                            {{ record.invoice.number }}
                        </Link>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'method'">
                        <span v-if="record.paymentMethod || record.payment_method">
                            {{ (record.paymentMethod || record.payment_method).name }}
                        </span>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'type'">
                        <Tag :bordered="false">{{ typeLabelFor(record.type) }}</Tag>
                    </template>

                    <template v-else-if="column.key === 'amount'">
                        <strong>{{ formatAmount(record.amount, record.currency_code) }}</strong>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Tag :color="statusColor[record.status] || 'default'" :bordered="false">
                            {{ statusLabelFor(record.status) }}
                        </Tag>
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

                    <PaymentsActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('payments.edit')"
                        :can-create="can('payments.create')"
                        :can-delete="can('payments.delete')"
                        :duplicating-id="duplicating"
                        @edit="goEdit"
                        @duplicate="duplicate"
                        @delete="goDelete"
                    />

                    <template v-else>{{ text ?? record[column.dataIndex] ?? '' }}</template>
                </template>
            </ResponsiveTable>
        </Card>

        <PaymentsDetailDrawer
            v-model:open="drawerVisible"
            :payment="selectedPayment"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('payments.create')"
            :can-edit="can('payments.edit')"
            :can-delete="can('payments.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <PaymentsBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('payments.record') : $t('payments.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('payments.export_title')"
            :endpoints="exportEndpoints"
            :total-rows="payments.total ?? 0"
            :total-unfiltered="payments.total_unfiltered ?? payments.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('business_management.payments.import')"
            :template-url="route('business_management.payments.import_template')"
            :resource-label="$t('payments.records')"
        />

        <!-- ── Mobile: bottom bar fijo + drawers (patron app real, como Regions) ── -->
        <PaymentsMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('payments.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <PaymentsMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
            :can-create="can('payments.create')"
            :can-edit="can('payments.edit')"
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

.mono {
    font-family: ui-monospace, Consolas, monospace;
    font-size: 0.8125rem;
    background: var(--color-surface-alt);
    padding: 2px 6px;
    border-radius: 3px;
}
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.link-quiet { color: var(--color-text); text-decoration: none; }
.link-quiet:hover { color: var(--color-primary); text-decoration: underline; }

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

/* Reserva espacio para que la bulk-bar mobile-sticky no tape la ultima card. */
.grid-card:has(.bulk-bar--mobile-sticky) {
    padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 80px);
}

/* Mobile: el toolbar desktop se oculta — sus acciones viven en el bottom bar. */
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: stretch; }
    .hide-on-mobile { display: none !important; }
}

/* "Filtros avanzados (3) X" — el badge va con fondo blanco translucido y la
   X de limpiar aparece pegada al texto. Patron estilo Gmail/Linear chips. */
.adv-filter-btn {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.adv-filter-btn__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 18px;
    height: 18px;
    padding: 0 6px;
    border-radius: 9px;
    background: rgba(255, 255, 255, 0.25);
    font-size: 0.7rem;
    font-weight: 600;
    line-height: 1;
}
.adv-filter-btn__clear {
    font-size: 14px;
    opacity: 0.7;
    cursor: pointer;
    transition: opacity 0.15s ease, transform 0.15s ease;
}
.adv-filter-btn__clear:hover {
    opacity: 1;
    transform: scale(1.12);
}
</style>

<style>
/* Espacio inferior para el bottom-bar fijo (mobile). No-scoped: aplica al
   layout shell. Igual que Regions. */
@media (max-width: 767.98px) {
    .below-shell .content {
        padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 150px) !important;
    }
}
</style>
