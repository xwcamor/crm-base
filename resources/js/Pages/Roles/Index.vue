<script setup>
import { ref, computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, Tag, Button, Space, Tooltip } from 'ant-design-vue';
import {
    EditOutlined, PlusOutlined, InboxOutlined,
    QuestionCircleOutlined, AuditOutlined, DownloadOutlined, UploadOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ColumnSelector from '@/Components/Common/ColumnSelector.vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import FilterChips from '@/Components/Common/FilterChips.vue';
import SavedViews from '@/Components/Common/SavedViews.vue';
import ExportDialog from '@/Components/Common/ExportDialog.vue';
import ImportDialog from '@/Components/Common/ImportDialog.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';

import RolesFavoriteCell from '@/Components/Roles/RolesFavoriteCell.vue';
import RolesDetailDrawer from '@/Components/Roles/RolesDetailDrawer.vue';
import RolesMobileBottomBar from '@/Components/Roles/RolesMobileBottomBar.vue';
import RolesMobileDrawers from '@/Components/Roles/RolesMobileDrawers.vue';
import RolesPageHeader from '@/Components/Roles/RolesPageHeader.vue';
import RolesActionsCell from '@/Components/Roles/RolesActionsCell.vue';
import RolesEmptyState from '@/Components/Roles/RolesEmptyState.vue';
import RolesBulkBar from '@/Components/Roles/RolesBulkBar.vue';
import RolesBulkDeleteModal from '@/Components/Roles/RolesBulkDeleteModal.vue';

import { useAuth } from '@/Composables/useAuth';
import { useColumnPreferences } from '@/Composables/useColumnPreferences';
import { useModuleFilters } from '@/Composables/useModuleFilters';
import { useModuleFavorites } from '@/Composables/useModuleFavorites';
import { useModuleUndoToast } from '@/Composables/useModuleUndoToast';
import { useModuleDrawer } from '@/Composables/useModuleDrawer';
import { useModuleSavedViews } from '@/Composables/useModuleSavedViews';
import { useModuleListMeta } from '@/Composables/useModuleListMeta';
import { useModuleTour } from '@/Composables/useModuleTour';
import { useKeyboardShortcuts } from '@/Composables/useKeyboardShortcuts';
import { useViewport } from '@/Composables/useViewport';
import { usePlanFeatures } from '@/Composables/usePlanFeatures';
import { useI18n } from '@/Plugins/i18n';

import {
    rolesFilterFields, rolesEmptyFilters, hydrateRolesFilters,
    rolesFiltersToQuery, rolesFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { rolesTableColumns } from './config/columns';
import { rolesExportableColumns, rolesExportEndpoints } from './config/exports';
import { rolesTourSteps } from './config/tour';

const { t } = useI18n();
const { can, isSuper: isSuperLocal, canSeeAudit } = useAuth();
const { canUse: canUsePlanFeature } = usePlanFeatures();

defineOptions({ layout: AppLayout });

const props = defineProps({
    roles:        { type: Object, required: true },
    filters:      { type: Object, required: true },
    isSuper: { type: Boolean, default: false },
});

// ─── Filtros (schema + (de)serialización en config/filters.js) ──────────────
const filterFields = computed(() =>
    rolesFilterFields(t, { isSuper: isSuperLocal.value }),
);

const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateRolesFilters,
    toQuery:       rolesFiltersToQuery,
    summary:       rolesFiltersSummary,
    empty:         rolesEmptyFilters,
    only:          ['roles', 'filters'],
    t,
});

// ─── Contador adaptativo "X perfiles" / "X de Y perfiles" ──────────────────
const { counterLabel } = useModuleListMeta({
    pagination: computed(() => props.roles),
    hasActiveFilters,
    t,
});

// ─── Selección bulk ────────────────────────────────────────────────────────
const selectedRowKeys = ref([]);
const onSelectChange = (keys) => { selectedRowKeys.value = keys; };

const bulkDeleteModalOpen = ref(false);
const bulkDeleteReason = ref('');
const submitBulkDelete = () => {
    if (bulkDeleteReason.value.trim().length < 3) return;
    router.post(route('user_management.roles.bulk_delete'), {
        ids: selectedRowKeys.value,
        deleted_description: bulkDeleteReason.value,
    }, {
        preserveScroll: true,
        onSuccess: () => {
            selectedRowKeys.value = [];
            bulkDeleteModalOpen.value = false;
            bulkDeleteReason.value = '';
        },
    });
};

const bulkSetActive = (active) => {
    router.post(route('user_management.roles.bulk_set_active'), {
        ids: selectedRowKeys.value,
        is_active: active,
    }, {
        preserveScroll: true,
        onSuccess: () => { selectedRowKeys.value = []; },
    });
};

// ─── Duplicate ─────────────────────────────────────────────────────────────
const duplicating = ref(null);
const duplicate = (role) => {
    duplicating.value = role.id;
    router.post(route('user_management.roles.duplicate', role.slug), {}, {
        preserveScroll: true,
        onFinish: () => { duplicating.value = null; },
    });
};

// ─── Export / Import (columnas + endpoints en config/exports.js) ────────────
const exportOpen = ref(false);
const importOpen = ref(false);
const exportableColumns = computed(() => rolesExportableColumns(t));
const exportEndpoints   = computed(() => rolesExportEndpoints());

// ─── Favoritos + Drawer + Viewport ────────────────────────────────────────
const { isMobile: isMobileScreen } = useViewport(768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('roles', 'roles');

// Toast de UNDO (60s) — aparece al eliminar; el admin lo puede usar.
useModuleUndoToast('user_management.roles.undo_last_delete');
const { open: drawerVisible, selected: selectedRole, openDetails } = useModuleDrawer({ module: 'roles' });

// ─── Paginación / sorting ──────────────────────────────────────────────────
const tablePagination = computed(() => ({
    current:  props.roles.current_page,
    pageSize: props.roles.per_page,
    total:    props.roles.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100', '200'],
    showTotal: (total, range) => `${range[0]}-${range[1]} / ${total}`,
}));

const onTableChange = (pag, _filters, sorter) => {
    const extra = { page: pag.current, per_page: pag.pageSize };
    if (sorter?.field) {
        extra.sort      = sorter.field;
        extra.direction = sorter.order === 'descend' ? 'desc' : 'asc';
    }
    reload(extra);
};

// ─── Columnas (schema en config/columns.js) ─────────────────────────────────
const allColumns = computed(() =>
    rolesTableColumns(t, { isSuper: isSuperLocal.value }),
);
const { visibleColumnKeys, columns } = useColumnPreferences(allColumns);

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
const tour = useModuleTour({ module: 'roles', steps: () => rolesTourSteps(t) });

// ─── Mobile: drawers + navegación (patrón Regions: app-like en mobile) ──────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('user_management.roles.create'));
const goToTrash   = () => router.visit(route('user_management.roles.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'roles' }));
const goToEditAll = () => router.visit(route('user_management.roles.edit_all'));

// ─── Keyboard shortcuts ───────────────────────────────────────────────────
useKeyboardShortcuts({
    'ctrl+n': () => canUsePlanFeature('team_management') && router.visit(route('user_management.roles.create')),
    'esc': () => {
        if (drawerVisible.value)            drawerVisible.value = false;
        else if (bulkDeleteModalOpen.value) bulkDeleteModalOpen.value = false;
        else if (filtersDrawerOpen.value)   filtersDrawerOpen.value = false;
        else if (otrosDrawerOpen.value)     otrosDrawerOpen.value = false;
    },
    'ctrl+f': () => {
        if (isMobileScreen.value) filtersDrawerOpen.value = true;
        else document.querySelector('.filter-bar input, .filter-bar .ant-select-selector')?.focus();
    },
});
</script>

<template>
    <Head :title="$t('roles.plural')" />

    <div>
        <div class="page-header">
            <RolesPageHeader
                :title="$t('roles.plural')"
                :counter-label="counterLabel"
            />

            <!-- Toolbar desktop. En mobile se oculta — las acciones viven
                 en el bottom bar + drawers (patron app real, como Regions). -->
            <Space wrap class="hide-on-mobile">
                <span v-if="canUsePlanFeature('saved_views')" data-tour="saved-views">
                    <SavedViews
                        module="roles"
                        :current-state="currentViewState"
                        @apply="applySavedState"
                        @default-loaded="applySavedState"
                    />
                </span>
                <span data-tour="columns">
                    <ColumnSelector
                        :columns="allColumns"
                        v-model="visibleColumnKeys"
                        storage-key="roles"
                    />
                </span>
                <span data-tour="export-import">
                    <Tooltip :title="$t('global.export_hint')">
                        <Button @click="exportOpen = true">
                            <DownloadOutlined /> {{ $t('global.export') }}
                        </Button>
                    </Tooltip>
                    <Tooltip v-if="canUsePlanFeature('imports')" :title="$t('global.import_hint')">
                        <Button style="margin-left: 8px;" @click="importOpen = true">
                            <UploadOutlined /> {{ $t('global.import') }}
                        </Button>
                    </Tooltip>
                </span>
                <Tooltip v-if="canUsePlanFeature('edit_all')" :title="$t('global.edit_all_hint')">
                    <Link :href="route('user_management.roles.edit_all')" data-tour="edit-all">
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
                <Tooltip v-if="isSuperLocal" :title="$t('global.view_deleted_hint')">
                    <Link :href="route('user_management.roles.trash')" data-tour="trash">
                        <Button>
                            <InboxOutlined /> {{ $t('global.view_deleted') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="canSeeAudit" :title="$t('global.audit_hint')">
                    <Link :href="route('system_management.audit_logs.index', { module: 'roles' })" data-tour="audit">
                        <Button>
                            <AuditOutlined /> {{ $t('sidebar.group_audit') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="canUsePlanFeature('team_management')" :title="$t('global.create_record_hint')">
                    <Link :href="route('user_management.roles.create')" data-tour="new-record">
                        <Button type="primary"><PlusOutlined /> {{ $t('roles.new') }}</Button>
                    </Link>
                </Tooltip>
            </Space>
        </div>

        <!-- Filtros: desktop inline. En mobile vive en el drawer de Filtros. -->
        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="roles"
            data-tour="filters"
        />

        <div v-auto-animate>
            <FilterChips :fields="filterFields" v-model="filters" />
        </div>

        <!-- Bulk action bar -->
        <div v-auto-animate>
            <RolesBulkBar
                v-if="selectedRowKeys.length > 0"
                :count="selectedRowKeys.length"
                :is-mobile="isMobileScreen"
                :can-edit="canUsePlanFeature('team_management')"
                :can-delete="canUsePlanFeature('team_management')"
                @cancel="selectedRowKeys = []"
                @set-active="bulkSetActive"
                @delete="bulkDeleteModalOpen = true"
            />
        </div>

        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <ResponsiveTable
                :dataSource="roles.data"
                :columns="columns"
                rowKey="id"
                :pagination="tablePagination"
                :row-selection="{ selectedRowKeys, onChange: onSelectChange, getCheckboxProps: r => ({ disabled: r.is_system }) }"
                data-tour="bulk"
                @change="onTableChange"
                @row-click="openDetails"
            >
                <template #empty>
                    <RolesEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="canUsePlanFeature('team_management')"
                        @clear-filters="clearFilters"
                    />
                </template>
                <template #bodyCell="{ column, record, text, isMobile }">
                    <RolesFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :data-tour="record === roles.data[0] ? 'favorites' : null"
                        @toggle="toggleFavorite"
                    />

                    <template v-else-if="column.key === 'name'">
                        <strong>{{ record.name }}</strong>
                    </template>

                    <template v-else-if="column.key === 'workspace'">
                        <Tag v-if="record.tenant_id" color="blue" :bordered="false">{{ record.tenant_name ?? '—' }}</Tag>
                        <Tag v-else color="purple" :bordered="false">{{ $t('global.platform') }}</Tag>
                    </template>

                    <template v-else-if="column.key === 'is_active'">
                        <Tag :color="record.is_active ? 'success' : 'default'" :bordered="false">
                            {{ record.is_active ? $t('global.active') : $t('global.inactive') }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'permissions_count'">
                        <Tag :color="record.permissions_count > 0 ? 'cyan' : 'default'">{{ record.permissions_count }}</Tag>
                    </template>

                    <template v-else-if="column.key === 'users_count'">
                        <Tag :color="record.users_count > 0 ? 'green' : 'default'">{{ record.users_count }}</Tag>
                    </template>

                    <RolesActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="canUsePlanFeature('team_management')"
                        :can-create="canUsePlanFeature('team_management')"
                        :can-delete="canUsePlanFeature('team_management')"
                        :duplicating-id="duplicating"
                        @duplicate="duplicate"
                    />

                    <template v-else>
                        {{ text ?? record[column.dataIndex] ?? '' }}
                    </template>
                </template>
            </ResponsiveTable>
        </Card>

        <RolesDetailDrawer
            v-model:open="drawerVisible"
            :role="selectedRole"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-create="canUsePlanFeature('team_management')"
            :can-edit="canUsePlanFeature('team_management')"
            :can-delete="canUsePlanFeature('team_management')"
            @duplicate="duplicate"
        />

        <RolesBulkDeleteModal
            v-model:open="bulkDeleteModalOpen"
            v-model:reason="bulkDeleteReason"
            :count="selectedRowKeys.length"
            :resource-label="$t('roles.plural')"
            @confirm="submitBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :filters-summary="filtersSummary"
            :current-filters="buildQueryData()"
            :default-title="$t('roles.export_title')"
            :endpoints="exportEndpoints"
            :total-rows="roles.total ?? 0"
            :total-unfiltered="roles.total_unfiltered ?? roles.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('user_management.roles.import')"
            :template-url="route('user_management.roles.import_template')"
            :resource-label="$t('roles.plural')"
        />

        <!-- ── Mobile: bottom bar fijo + drawers (patrón app real, como Regions) ── -->
        <RolesMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="canUsePlanFeature('team_management')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <RolesMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
            :can-create="canUsePlanFeature('team_management')"
            :can-edit="canUsePlanFeature('team_management')"
            :is-super="isSuperLocal"
            :can-see-audit="canSeeAudit"
            @open-export="exportOpen = true"
            @open-import="importOpen = true"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
            @go-edit-all="goToEditAll"
        />
    </div>
</template>

<style scoped>
.muted { color: var(--color-text-muted); font-size: 0.78rem; }

.page-header {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 16px;
    margin-bottom: 16px;
    flex-wrap: wrap;
}

.grid-card { border-radius: 6px; }

.grid-card :deep(.ant-table-thead > tr > th) {
    background: var(--color-surface-alt);
    color: var(--color-text-strong);
    font-weight: 600;
    font-size: 0.8125rem;
}

/* Animaciones (row stagger, hover lift, empty breathe, hover-to-reveal acciones)
   viven globalmente en resources/css/app.css. */

/* Mobile: el toolbar desktop se oculta — sus acciones viven en el bottom bar. */
@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: stretch; }
    .hide-on-mobile { display: none !important; }
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
