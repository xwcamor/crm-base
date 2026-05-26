<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, Tag, Button, Tooltip, Space, Badge } from 'ant-design-vue';
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

import PipelinesBulkBar from '@/Components/Pipelines/PipelinesBulkBar.vue';
import PipelinesBulkDeleteModal from '@/Components/Pipelines/PipelinesBulkDeleteModal.vue';
import PipelinesFavoriteCell from '@/Components/Pipelines/PipelinesFavoriteCell.vue';
import PipelinesDetailDrawer from '@/Components/Pipelines/PipelinesDetailDrawer.vue';
import PipelinesMobileBottomBar from '@/Components/Pipelines/PipelinesMobileBottomBar.vue';
import PipelinesMobileDrawers from '@/Components/Pipelines/PipelinesMobileDrawers.vue';
import PipelinesPageHeader from '@/Components/Pipelines/PipelinesPageHeader.vue';
import PipelinesActionsCell from '@/Components/Pipelines/PipelinesActionsCell.vue';
import PipelinesEmptyState from '@/Components/Pipelines/PipelinesEmptyState.vue';

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
// El toolbar inline de Pipelines (no usa ModuleToolbar) repite manualmente
// estos gates para no mostrar botones que no funcionan al user free/basic.
const { canUse: canUsePlanFeature } = usePlanFeatures();

import {
    pipelinesFilterFields, pipelinesEmptyFilters, hydratePipelinesFilters,
    pipelinesFiltersToQuery, pipelinesFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { pipelinesTableColumns } from './config/columns';
import { pipelinesExportableColumns, pipelinesExportEndpoints } from './config/exports';
import { pipelinesTourSteps } from './config/tour';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTime } = useDateFormat();

const props = defineProps({
    pipelines:      { type: Object, required: true },
    filters:        { type: Object, default: () => ({}) },
    filterSchema:   { type: Array,  default: () => [] },
});

// ─── Filtros (schema + (de)serialización en config/filters.js) ──────────────
const filterFields = computed(() =>
    pipelinesFilterFields(t, {
    }),
);

const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydratePipelinesFilters,
    toQuery:       pipelinesFiltersToQuery,
    summary:       pipelinesFiltersSummary,
    empty:         pipelinesEmptyFilters,
    only:          ['pipelines', 'filters'],
    t,
});

// ─── Filtros avanzados (drawer con query builder dinámico) ──────────────────
// Estos NO viven en useModuleFilters porque son un array de clausulas
// estructuradas {field, op, value}, distinto al shape plano del FilterBar.
// Se persisten via Inertia (filters.advanced_where) para que sobreviva al
// paginate/sort sin perder el filtro aplicado.
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
        only: ['pipelines', 'filters'],
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
    pagination: computed(() => props.pipelines),
    hasActiveFilters,
    t,
});

// ─── Columnas (schema en config/columns.js) ─────────────────────────────────
const allColumns = computed(() =>
    pipelinesTableColumns(t, { isSuper: isSuper.value }),
);
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

// ─── Paginacion + sort ──────────────────────────────────────────────────────
const tablePagination = computed(() => ({
    current:  props.pipelines.current_page,
    pageSize: props.pipelines.per_page,
    total:    props.pipelines.total,
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
useModuleUndoToast('crm.pipelines.undo_last_delete');

// ─── Favoritos polimorficos ────────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('pipelines', 'pipelines');

// ─── Detail drawer + recent views tracking ─────────────────────────────────
const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { open: drawerVisible, selected: selectedPipeline, openDetails } = useModuleDrawer({ module: 'pipelines' });

// ─── Bulk ───────────────────────────────────────────────────────────────────
const {
    selectedRowKeys, rowSelection, clearSelection,
    bulkOpen, bulkReason, bulkSubmitting, bulkError, bulkActivating,
    openBulkDelete, bulkSetActive, confirmBulkDelete,
} = useModuleBulkActions({
    bulkSetActiveRoute: 'crm.pipelines.bulk_set_active',
    bulkDeleteRoute:    'crm.pipelines.bulk_delete',
    resourceLabel:      t('pipelines.records'),
});

// ─── Duplicate ──────────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (record) => {
    duplicating.value = record.id;
    router.post(route('crm.pipelines.duplicate', record.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Export / Import (columnas + endpoints en config/exports.js) ────────────
const exportOpen = ref(false);
const importOpen = ref(false);
const exportableColumns = computed(() => pipelinesExportableColumns(t));
const exportEndpoints   = computed(() => pipelinesExportEndpoints());

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
const tour = useModuleTour({ module: 'pipelines', steps: () => pipelinesTourSteps(t) });

// ─── Mobile: drawers + navegación (patrón Regions: app-like en mobile) ──────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('crm.pipelines.create'));
const goToTrash   = () => router.visit(route('crm.pipelines.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'pipelines' }));
const goToEditAll = () => router.visit(route('crm.pipelines.edit_all'));

// ─── Keyboard shortcuts ────────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => can('pipelines.create') && router.visit(route('crm.pipelines.create')),
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
const goEdit   = (record) => router.visit(route('crm.pipelines.edit',   record.slug));
const goDelete = (record) => router.visit(route('crm.pipelines.delete', record.slug));
</script>

<template>
    <Head :title="$t('pipelines.plural')" />

    <div>
        <div class="page-header">
            <PipelinesPageHeader
                :title="$t('pipelines.plural')"
                :counter-label="counterLabel"
            />

            <!-- Toolbar desktop. En mobile se oculta — las acciones viven
                 en el bottom bar + drawers (patron app real, como Regions). -->
            <Space wrap class="hide-on-mobile">
                <span v-if="canUsePlanFeature('saved_views')" data-tour="saved-views">
                    <SavedViews
                        module="pipelines"
                        :current-state="currentViewState"
                        @apply="applySavedState"
                        @default-loaded="applySavedState"
                    />
                </span>
                <span data-tour="columns">
                    <ColumnSelector
                        :columns="allColumns"
                        v-model="visibleColumnKeys"
                        storage-key="pipelines"
                    />
                </span>
                <span data-tour="export-import">
                    <Tooltip :title="$t('global.export_hint')">
                        <Button @click="exportOpen = true">
                            <DownloadOutlined /> {{ $t('global.export') }}
                        </Button>
                    </Tooltip>
                    <Tooltip v-if="can('pipelines.create') && canUsePlanFeature('imports')" :title="$t('global.import_hint')">
                        <Button style="margin-left: 8px;" @click="importOpen = true">
                            <UploadOutlined /> {{ $t('global.import') }}
                        </Button>
                    </Tooltip>
                </span>
                <Tooltip v-if="can('pipelines.edit') && canUsePlanFeature('edit_all')" :title="$t('global.edit_all_hint')">
                    <Link :href="route('crm.pipelines.edit_all')" data-tour="edit-all">
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
                    <Link :href="route('crm.pipelines.trash')" data-tour="trash">
                        <Button>
                            <InboxOutlined /> {{ $t('global.view_deleted') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="canSeeAudit" :title="$t('global.audit_hint')">
                    <Link :href="route('system_management.audit_logs.index', { module: 'pipelines' })" data-tour="audit">
                        <Button>
                            <AuditOutlined /> {{ $t('sidebar.group_audit') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="can('pipelines.create')" :title="$t('global.create_record_hint')">
                    <Link :href="route('crm.pipelines.create')">
                        <Button type="primary">
                            <PlusOutlined /> {{ $t('pipelines.new') }}
                        </Button>
                    </Link>
                </Tooltip>
            </Space>
        </div>

        <!-- FilterBar: desktop inline. En mobile vive en el drawer de Filtros.
             Slot `actions` inyecta el botón "Filtros avanzados" al final
             del bar, agrupado visualmente con Configurar/Limpiar. -->
        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="pipelines"
            data-tour="filters"
        >
            <template #actions>
                <!-- Botón Filtros avanzados con X integrada (estilo Gmail/Linear):
                     cuando hay filtros activos, la X aparece adentro del mismo
                     botón para limpiarlos sin tener que distinguir 2 controles. -->
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
                <PipelinesBulkBar
                    v-if="selectedRowKeys.length > 0"
                    :count="selectedRowKeys.length"
                    :is-mobile="isMobileScreen"
                    :bulk-activating="bulkActivating"
                    :can-edit="can('pipelines.edit')"
                    :can-delete="can('pipelines.delete')"
                    @cancel="clearSelection"
                    @set-active="bulkSetActive"
                    @delete="openBulkDelete"
                />
            </div>

            <ResponsiveTable
                :dataSource="pipelines.data"
                :columns="columns"
                :pagination="tablePagination"
                :row-selection="(can('pipelines.delete') || can('pipelines.edit')) ? rowSelection : null"
                rowKey="id"
                @change="onTableChange"
                @row-click="openDetails"
                data-tour="bulk"
            >
                <template #empty>
                    <PipelinesEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('pipelines.create')"
                        @clear-filters="clearFilters"
                        @open-import="importOpen = true"
                    />
                </template>
                <template #bodyCell="{ column, record, text, isMobile }">
                    <PipelinesFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :data-tour="record === pipelines.data[0] ? 'favorites' : null"
                        @toggle="toggleFavorite"
                    />

                    <template v-else-if="column.key === 'name'">
                        <span class="pipeline-name-cell">
                            <span class="pipeline-color-dot" :style="{ background: record.color || '#888' }"></span>
                            <span>{{ record.name }}</span>
                            <Tag v-if="record.is_default" color="gold" :bordered="false" style="margin-left: 4px">
                                {{ $t('global.default') }}
                            </Tag>
                        </span>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Tag :color="record.is_active ? 'success' : 'default'" :bordered="false">
                            {{ record.is_active ? $t('global.active') : $t('global.inactive') }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'color'">
                        <span v-if="record.color"
                            :title="record.color"
                            :style="{ display: 'inline-block', width: '20px', height: '20px', borderRadius: '4px', background: record.color, border: '1px solid rgba(0,0,0,0.1)' }"
                        />
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'stages_count'">
                        <Tag :bordered="false">{{ record.stages_count ?? 0 }}</Tag>
                    </template>

                    <template v-else-if="column.key === 'open_deals_count'">
                        <Tag v-if="record.open_deals_count > 0" color="blue" :bordered="false">{{ record.open_deals_count }}</Tag>
                        <span v-else class="muted">0</span>
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

                    <PipelinesActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('pipelines.edit')"
                        :can-create="can('pipelines.create')"
                        :can-delete="can('pipelines.delete')"
                        :duplicating-id="duplicating"
                        @edit="goEdit"
                        @duplicate="duplicate"
                        @delete="goDelete"
                    />

                    <template v-else>{{ text ?? record[column.dataIndex] ?? '' }}</template>
                </template>
            </ResponsiveTable>
        </Card>

        <PipelinesDetailDrawer
            v-model:open="drawerVisible"
            :pipeline="selectedPipeline"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="can('pipelines.create')"
            :can-edit="can('pipelines.edit')"
            :can-delete="can('pipelines.delete')"
            :duplicating-id="duplicating"
            @duplicate="duplicate"
        />

        <PipelinesBulkDeleteModal
            v-model:open="bulkOpen"
            v-model:reason="bulkReason"
            :count="selectedRowKeys.length"
            :submitting="bulkSubmitting"
            :error-msg="bulkError"
            :resource-label="selectedRowKeys.length === 1 ? $t('pipelines.record') : $t('pipelines.records')"
            @confirm="confirmBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('pipelines.export_title')"
            :endpoints="exportEndpoints"
            :total-rows="pipelines.total ?? 0"
            :total-unfiltered="pipelines.total_unfiltered ?? pipelines.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('crm.pipelines.import')"
            :template-url="route('crm.pipelines.import_template')"
            :resource-label="$t('pipelines.records')"
        />

        <!-- ── Mobile: bottom bar fijo + drawers (patrón app real, como Regions) ── -->
        <PipelinesMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('pipelines.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <PipelinesMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
            :can-create="can('pipelines.create')"
            :can-edit="can('pipelines.edit')"
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

.pipeline-name-cell { display: inline-flex; align-items: center; gap: 8px; }
.pipeline-color-dot {
    display: inline-block;
    width: 12px; height: 12px;
    border-radius: 50%;
    border: 1px solid rgba(0, 0, 0, 0.12);
    flex-shrink: 0;
}

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

/* Reserva espacio para que la bulk-bar mobile-sticky no tape la última card. */
.grid-card:has(.bulk-bar--mobile-sticky) {
    padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 80px);
}

/* Mobile: el toolbar desktop se oculta — sus acciones viven en el bottom bar. */
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: stretch; }
    .hide-on-mobile { display: none !important; }
}

/* "Filtros avanzados (3) ⊗" — el badge va con fondo blanco translucido y la
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
