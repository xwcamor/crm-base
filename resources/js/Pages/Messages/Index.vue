<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, Tag, Button, Tooltip, Space } from 'ant-design-vue';
import {
    PlusOutlined, EditOutlined, InboxOutlined,
    DownloadOutlined, UploadOutlined, QuestionCircleOutlined,
    AuditOutlined, FilterOutlined, CloseCircleFilled,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';

import AppLayout from '@/Layouts/AppLayout.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import ColumnSelector from '@/Components/Common/ColumnSelector.vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import AdvancedFilterDrawer from '@/Components/Common/AdvancedFilterDrawer.vue';
import FilterChips from '@/Components/Common/FilterChips.vue';
import ExportDialog from '@/Components/Common/ExportDialog.vue';
import ImportDialog from '@/Components/Common/ImportDialog.vue';
import SavedViews from '@/Components/Common/SavedViews.vue';

import MessagesBulkBar from '@/Components/Messages/MessagesBulkBar.vue';
import MessagesBulkDeleteModal from '@/Components/Messages/MessagesBulkDeleteModal.vue';
import MessagesFavoriteCell from '@/Components/Messages/MessagesFavoriteCell.vue';
import MessagesDetailDrawer from '@/Components/Messages/MessagesDetailDrawer.vue';
import MessagesMobileBottomBar from '@/Components/Messages/MessagesMobileBottomBar.vue';
import MessagesMobileDrawers from '@/Components/Messages/MessagesMobileDrawers.vue';
import MessagesPageHeader from '@/Components/Messages/MessagesPageHeader.vue';
import MessagesActionsCell from '@/Components/Messages/MessagesActionsCell.vue';
import MessagesEmptyState from '@/Components/Messages/MessagesEmptyState.vue';

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

// Gate por plan: saved_views requiere basic+, imports/edit_all requieren pro+.
const { canUse: canUsePlanFeature } = usePlanFeatures();

import {
    messagesFilterFields, messagesEmptyFilters, hydrateMessagesFilters,
    messagesFiltersToQuery, messagesFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { messagesTableColumns } from './config/columns';
import { messagesExportableColumns, messagesExportEndpoints } from './config/exports';
import { messagesTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTime } = useDateFormat();

const props = defineProps({
    messages:        { type: Object, required: true },
    filters:         { type: Object, default: () => ({}) },
    audienceOptions: { type: Array,  default: () => [] },
    filterSchema:    { type: Array,  default: () => [] },
});

// ─── Filtros (schema + (de)serialización en config/filters.js) ──────────────
const filterFields = computed(() =>
    messagesFilterFields(t, { audienceOptions: props.audienceOptions }),
);

const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateMessagesFilters,
    toQuery:       messagesFiltersToQuery,
    summary:       messagesFiltersSummary,
    empty:         messagesEmptyFilters,
    only:          ['messages', 'filters'],
    t,
});

// ─── Filtros avanzados (drawer con query builder dinámico) ──────────────────
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
        only: ['messages', 'filters'],
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
    pagination: computed(() => props.messages),
    hasActiveFilters,
    t,
});

// ─── Columnas (schema en config/columns.js) ─────────────────────────────────
const allColumns = computed(() => messagesTableColumns(t));
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

// ─── Paginacion + sort ──────────────────────────────────────────────────────
const tablePagination = computed(() => ({
    current:  props.messages.current_page,
    pageSize: props.messages.per_page,
    total:    props.messages.total,
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
useModuleUndoToast('communication.messages.undo_last_delete');

// ─── Favoritos polimorficos ────────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('messages', 'messages');

// ─── Detail drawer + recent views tracking ─────────────────────────────────
const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { open: drawerVisible, selected: selectedMessage, openDetails } = useModuleDrawer({ module: 'messages' });

// ─── Bulk ───────────────────────────────────────────────────────────────────
const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, bulkSetActive, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'communication.messages.bulk_set_active',
    bulkDeleteRoute:    'communication.messages.bulk_delete',
    resourceLabel:      t('messages.records'),
});

// ─── Duplicate ──────────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('communication.messages.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Export / Import (columnas + endpoints en config/exports.js) ────────────
const exportOpen = ref(false);
const importOpen = ref(false);
const exportableColumns = computed(() => messagesExportableColumns(t));
const exportEndpoints   = computed(() => messagesExportEndpoints());

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
const tour = useModuleTour({ module: 'messages', steps: () => messagesTourSteps(t) });

// ─── Mobile: drawers + navegación ───────────────────────────────────────────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('communication.messages.create'));
const goToTrash   = () => router.visit(route('communication.messages.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'messages' }));
const goToEditAll = () => router.visit(route('communication.messages.edit_all'));

// ─── Keyboard shortcuts ────────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => can('messages.view') && router.visit(route('communication.messages.create')),
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
const goEdit   = (record) => router.visit(route('communication.messages.edit',   record.slug));
const goDelete = (record) => router.visit(route('communication.messages.delete', record.slug));

// ─── Helpers de presentacion para celdas custom ────────────────────────────
const audienceColor = (type) => {
    if (type === 'global') return 'blue';
    if (type === 'tenant') return 'cyan';
    if (type === 'user')   return 'purple';
    return 'default';
};

const audienceLabel = (type) => {
    if (!type) return '—';
    return t('messages.audience_' + type);
};

const statusLabel = (row) => {
    if (!row.published_at) return t('messages.status_draft');
    if (row.expires_at && dayjs(row.expires_at).isBefore(dayjs())) return t('messages.status_expired');
    return row.is_active ? t('global.active') : t('global.inactive');
};

const statusColor = (row) => {
    if (!row.published_at) return 'default';
    if (row.expires_at && dayjs(row.expires_at).isBefore(dayjs())) return 'red';
    return row.is_active ? 'success' : 'warning';
};
</script>

<template>
    <Head :title="$t('messages.plural')" />

    <div>
        <div class="page-header">
            <MessagesPageHeader
                :title="$t('messages.plural')"
                :counter-label="counterLabel"
            />

            <!-- Toolbar desktop. En mobile se oculta — las acciones viven
                 en el bottom bar + drawers. -->
            <Space wrap class="hide-on-mobile">
                <span v-if="canUsePlanFeature('saved_views')" data-tour="saved-views">
                    <SavedViews
                        module="messages"
                        :current-state="currentViewState"
                        @apply="applySavedState"
                        @default-loaded="applySavedState"
                    />
                </span>
                <span data-tour="columns">
                    <ColumnSelector
                        :columns="allColumns"
                        v-model="visibleColumnKeys"
                        storage-key="messages"
                    />
                </span>
                <span data-tour="export-import">
                    <Tooltip :title="$t('global.export_hint')">
                        <Button @click="exportOpen = true">
                            <DownloadOutlined /> {{ $t('global.export') }}
                        </Button>
                    </Tooltip>
                    <Tooltip v-if="can('messages.view') && canUsePlanFeature('imports')" :title="$t('global.import_hint')">
                        <Button style="margin-left: 8px;" @click="importOpen = true">
                            <UploadOutlined /> {{ $t('global.import') }}
                        </Button>
                    </Tooltip>
                </span>
                <Tooltip v-if="can('messages.view') && canUsePlanFeature('edit_all')" :title="$t('global.edit_all_hint')">
                    <Link :href="route('communication.messages.edit_all')" data-tour="edit-all">
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
                    <Link :href="route('communication.messages.trash')" data-tour="trash">
                        <Button>
                            <InboxOutlined /> {{ $t('global.view_deleted') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="canSeeAudit" :title="$t('global.audit_hint')">
                    <Link :href="route('system_management.audit_logs.index', { module: 'messages' })" data-tour="audit">
                        <Button>
                            <AuditOutlined /> {{ $t('sidebar.group_audit') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="can('messages.view')" :title="$t('global.create_record_hint')">
                    <Link :href="route('communication.messages.create')">
                        <Button type="primary">
                            <PlusOutlined /> {{ $t('messages.new') }}
                        </Button>
                    </Link>
                </Tooltip>
            </Space>
        </div>

        <!-- FilterBar: desktop inline. En mobile vive en el drawer de Filtros. -->
        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="messages"
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
                <MessagesBulkBar
                    v-if="selectedRowKeys.length > 0"
                    :count="selectedRowKeys.length"
                    :is-mobile="isMobileScreen"
                    :bulk-activating="bulkActivating"
                    @cancel="clearSelection"
                    @set-active="bulkSetActive"
                    @delete="openBulkDelete"
                />
            </div>

            <ResponsiveTable
                :dataSource="messages.data"
                :columns="columns"
                :pagination="tablePagination"
                :row-selection="can('messages.view') ? rowSelection : null"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <MessagesEmptyState
                        :has-filters="hasActiveFilters"
                        @clear-filters="clearFilters"
                        @open-import="importOpen = true"
                    />
                </template>
                <template #bodyCell="{ column, record, text, isMobile }">
                    <MessagesFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :data-tour="record === messages.data[0] ? 'favorites' : null"
                        @toggle="toggleFavorite"
                    />

                    <template v-else-if="column.key === 'subject'">
                        <strong class="subject-cell">{{ record.subject }}</strong>
                    </template>

                    <template v-else-if="column.key === 'audience'">
                        <Tag :color="audienceColor(record.audience_type)" :bordered="false">
                            {{ audienceLabel(record.audience_type) }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'recipients'">
                        {{ record.recipients_count ?? 0 }}
                    </template>

                    <template v-else-if="column.key === 'read'">
                        {{ record.read_count ?? 0 }} / {{ record.recipients_count ?? 0 }}
                    </template>

                    <template v-else-if="column.key === 'replies'">
                        {{ record.replies_count ?? 0 }}
                    </template>

                    <template v-else-if="column.key === 'published_at'">
                        <span v-if="record.published_at">{{ formatDateTime(record.published_at) }}</span>
                        <span v-else class="muted">{{ $t('messages.status_draft') }}</span>
                    </template>

                    <template v-else-if="column.key === 'expires_at'">
                        <span v-if="record.expires_at">{{ formatDateTime(record.expires_at) }}</span>
                        <span v-else class="muted">{{ $t('messages.no_expiration') }}</span>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Tag :color="statusColor(record)" :bordered="false">
                            {{ statusLabel(record) }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'created_at'">
                        {{ formatDateTime(record.created_at) }}
                    </template>

                    <MessagesActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :duplicating-id="duplicating"
                        @edit="goEdit"
                        @duplicate="duplicate"
                        @delete="goDelete"
                    />

                    <template v-else>{{ text ?? record[column.dataIndex] ?? '' }}</template>
                </template>
            </ResponsiveTable>
        </Card>

        <MessagesDetailDrawer
            v-model:open="drawerVisible"
            :message="selectedMessage"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <MessagesBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('messages.record') : $t('messages.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('messages.export_title')"
            :endpoints="exportEndpoints"
            :total-rows="messages.total ?? 0"
            :total-unfiltered="messages.total_unfiltered ?? messages.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('communication.messages.import')"
            :template-url="route('communication.messages.import_template')"
            :resource-label="$t('messages.records')"
        />

        <!-- ── Mobile: bottom bar fijo + drawers ── -->
        <MessagesMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <MessagesMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
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

.subject-cell { color: var(--color-text-strong); font-weight: 600; }
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

/* Reserva espacio para que la bulk-bar mobile-sticky no tape la ultima card. */
.grid-card:has(.bulk-bar--mobile-sticky) {
    padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 80px);
}

/* Mobile: el toolbar desktop se oculta — sus acciones viven en el bottom bar. */
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: stretch; }
    .hide-on-mobile { display: none !important; }
}

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
/* Espacio inferior para el bottom-bar fijo (mobile). No-scoped: aplica al layout shell. */
@media (max-width: 767.98px) {
    .below-shell .content {
        padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 150px) !important;
    }
}
</style>
