<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import {
    Button, Card, Space, Tag, Tooltip,
} from 'ant-design-vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import FilterChips from '@/Components/Common/FilterChips.vue';
import ColumnSelector from '@/Components/Common/ColumnSelector.vue';
import ResponsiveTable from '@/Components/Common/ResponsiveTable.vue';
import UsersBulkBar from '@/Components/Users/UsersBulkBar.vue';
import UsersMobileBottomBar from '@/Components/Users/UsersMobileBottomBar.vue';
import UsersMobileDrawers from '@/Components/Users/UsersMobileDrawers.vue';
import UsersPageHeader from '@/Components/Users/UsersPageHeader.vue';
import UsersActionsCell from '@/Components/Users/UsersActionsCell.vue';
import UsersEmptyState from '@/Components/Users/UsersEmptyState.vue';
import UsersDetailDrawer from '@/Components/Users/UsersDetailDrawer.vue';
import UsersBulkDeleteModal from '@/Components/Users/UsersBulkDeleteModal.vue';
import {
    PlusOutlined,
    EditOutlined,
    InboxOutlined,
    AuditOutlined,
    DownloadOutlined,
    UploadOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import ExportDialog from '@/Components/Common/ExportDialog.vue';
import ImportDialog from '@/Components/Common/ImportDialog.vue';
import SavedViews from '@/Components/Common/SavedViews.vue';
import UsersFavoriteCell from '@/Components/Users/UsersFavoriteCell.vue';
import { usePageLoading } from '@/Composables/usePageLoading';
import { useColumnPreferences } from '@/Composables/useColumnPreferences';
import { usePlanFeatures } from '@/Composables/usePlanFeatures';
import { useModuleFilters } from '@/Composables/useModuleFilters';
import { useModuleTour } from '@/Composables/useModuleTour';
import { useModuleListMeta } from '@/Composables/useModuleListMeta';
import { useModuleSavedViews } from '@/Composables/useModuleSavedViews';
import { useModuleFavorites } from '@/Composables/useModuleFavorites';
import { useModuleUndoToast } from '@/Composables/useModuleUndoToast';
import { useDateFormat } from '@/Composables/useDateFormat';
const { canUse: canUsePlanFeature } = usePlanFeatures();
const { formatDateTime } = useDateFormat();
import { useI18n } from '@/Plugins/i18n';
import { QuestionCircleOutlined } from '@ant-design/icons-vue';

import {
    usersFilterFields, usersEmptyFilters, hydrateUsersFilters,
    usersFiltersToQuery, usersFiltersSummary,
    serializeSavedFilters, deserializeSavedFilters,
} from './config/filters';
import { usersTableColumns } from './config/columns';
import { usersExportableColumns, usersExportEndpoints } from './config/exports';
import { usersTourSteps } from './config/tour';

const { t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    users:         { type: Object, required: true },
    filters:       { type: Object, required: true },
    roleOptions:   { type: Array,  default: () => [] },
    tenantOptions: { type: Array,  default: () => [] },
});

const page = usePage();
const can = (perm) => {
    const u = page.props.auth?.user;
    if (!u) return false;
    if (u.roles?.includes('super')) return true;
    return u.permissions?.includes(perm) ?? false;
};
const isSuper = computed(() => page.props.auth?.user?.roles?.includes('super') ?? false);
const canSeeAudit = computed(() => {
    const r = page.props.auth?.user?.roles ?? [];
    return r.includes('super') || r.includes('admin');
});

// ─── Filtros (schema + (de)serialización en config/filters.js) ──────────────
const filterFields = computed(() => usersFilterFields(t, {
    isSuper:  isSuper.value,
    roleOptions:   props.roleOptions,
    tenantOptions: props.tenantOptions,
}));

const {
    filters, reload, hasActiveFilters, clearFilters, filtersSummary, buildQueryData,
} = useModuleFilters({
    serverFilters: props.filters,
    hydrate:       hydrateUsersFilters,
    toQuery:       usersFiltersToQuery,
    summary:       usersFiltersSummary,
    empty:         usersEmptyFilters,
    only:          ['users', 'filters'],
    t,
});

// ─── Favoritos polimorficos ────────────────────────────────────────────────
const { submitting: favoriteSubmitting, toggle: toggleFavorite } = useModuleFavorites('users', 'users');

// Toast de UNDO (60s) — aparece al eliminar; el admin lo puede usar.
useModuleUndoToast('user_management.users.undo_last_delete');

// ─── Onboarding tour (pasos en config/tour.js) ──────────────────────────────
const tour = useModuleTour({ module: 'users', steps: () => usersTourSteps(t) });

// ─── Export / Import (columnas + endpoints en config/exports.js) ────────────
const exportOpen = ref(false);
const importOpen = ref(false);
const exportableColumns = computed(() => usersExportableColumns(t));
const exportEndpoints   = computed(() => usersExportEndpoints());

// ─── Bulk selection ─────────────────────────────────────────────────────────
const selectedRowKeys = ref([]);
const onSelectChange = (keys) => { selectedRowKeys.value = keys; };

const bulkDeleteModalOpen = ref(false);
const bulkDeleteReason = ref('');
const submitBulkDelete = () => {
    if (bulkDeleteReason.value.trim().length < 3) return;
    router.post(route('user_management.users.bulk_delete'), {
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
    router.post(route('user_management.users.bulk_set_active'), {
        ids: selectedRowKeys.value,
        is_active: active,
    }, {
        preserveScroll: true,
        onSuccess: () => { selectedRowKeys.value = []; },
    });
};

const roleTagColor = (roleName) => {
    if (roleName === 'admin') return 'purple';
    if (roleName === 'super') return 'red';
    return 'cyan';
};

// Contador adaptativo "X registros" / "X de Y registros" — mismo composable
// que Customers/Roles/Regions (numero animado + formato consistente).
const { counterLabel } = useModuleListMeta({
    pagination: computed(() => props.users),
    hasActiveFilters,
    t,
});

// ─── Drawer de detalles ────────────────────────────────────────────────────
const drawerVisible = ref(false);
const selectedUser  = ref(null);
const openDetails = (user) => {
    selectedUser.value = user;
    drawerVisible.value = true;
};

const screenWidth = ref(typeof window !== 'undefined' ? window.innerWidth : 1024);
const onResize = () => { screenWidth.value = window.innerWidth; };
onMounted(() => window.addEventListener('resize', onResize));
onBeforeUnmount(() => window.removeEventListener('resize', onResize));
const isMobileScreen = computed(() => screenWidth.value < 768);
const drawerWidth = computed(() => isMobileScreen.value ? '100%' : 480);

// ─── Loading state during Inertia partial reloads ──────────────────────────
// propKey='users' filtra polling cross-módulo del inbox (AppLayout cada 4s).
const { loading: tableLoading } = usePageLoading('/users', 'users');

// ─── Columnas (schema en config/columns.js) ─────────────────────────────────
const allColumns = computed(() =>
    usersTableColumns(t, { isSuper: isSuper.value }),
);

const visibleColumnKeys = ref([]);
// Initialize / sync visible keys whenever the columns list changes (e.g. on
// initial mount o si cambia el rol). Respeta `defaultHidden`: las columnas
// marcadas asi arrancan ocultas (ej. created_at) — el usuario las habilita
// desde el ColumnSelector.
watch(allColumns, (cols) => {
    visibleColumnKeys.value = cols.filter(c => !c.defaultHidden).map(c => c.key);
}, { immediate: true });

const columns = computed(() =>
    visibleColumnKeys.value
        .map(key => allColumns.value.find(c => c.key === key))
        .filter(Boolean)
);

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

const tablePagination = computed(() => ({
    current:  props.users.current_page,
    pageSize: props.users.per_page,
    total:    props.users.total,
    showSizeChanger: true,
    pageSizeOptions: ['10', '25', '50', '100', '200'],
    showTotal: (total, range) => `${range[0]}-${range[1]} de ${total}`,
}));

const onTableChange = (pag, _filters, sorter) => {
    const direction = sorter?.order === 'ascend' ? 'asc'
                    : sorter?.order === 'descend' ? 'desc'
                    : props.filters.direction;
    const sort = sorter?.field || props.filters.sort;
    reload({ page: pag.current, per_page: pag.pageSize, sort, direction });
};

// ─── Mobile: drawers + navegación (patrón Regions: app-like en mobile) ──────
const filtersDrawerOpen = ref(false);
const otrosDrawerOpen   = ref(false);
const goToCreate  = () => router.visit(route('user_management.users.create'));
const goToTrash   = () => router.visit(route('user_management.users.trash'));
const goToAudit   = () => router.visit(route('system_management.audit_logs.index', { module: 'users' }));
const goToEditAll = () => router.visit(route('user_management.users.edit_all'));
</script>

<template>
    <Head :title="$t('users.index_title')" />

    <div>
        <div class="page-header">
            <UsersPageHeader
                :title="$t('users.index_title')"
                :counter-label="counterLabel"
            />

            <!-- Toolbar desktop. En mobile se oculta — las acciones viven
                 en el bottom bar + drawers (patron app real, como Regions). -->
            <Space wrap class="hide-on-mobile">
                <span v-if="canUsePlanFeature('saved_views')" data-tour="saved-views">
                    <SavedViews
                        module="users"
                        :current-state="currentViewState"
                        @apply="applySavedState"
                        @default-loaded="applySavedState"
                    />
                </span>
                <span data-tour="columns">
                    <ColumnSelector
                        :columns="allColumns"
                        v-model="visibleColumnKeys"
                        storage-key="users"
                    />
                </span>
                <span data-tour="export-import">
                    <Tooltip :title="$t('global.export_hint')">
                        <Button @click="exportOpen = true">
                            <DownloadOutlined /> {{ $t('global.export') }}
                        </Button>
                    </Tooltip>
                    <Tooltip v-if="can('users.create') && canUsePlanFeature('imports')" :title="$t('global.import_hint')">
                        <Button style="margin-left: 8px;" @click="importOpen = true">
                            <UploadOutlined /> {{ $t('global.import') }}
                        </Button>
                    </Tooltip>
                </span>
                <Tooltip v-if="can('users.edit') && canUsePlanFeature('edit_all')" :title="$t('global.edit_all_hint')">
                    <Link :href="route('user_management.users.edit_all')" data-tour="edit-all">
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
                    <Link :href="route('user_management.users.trash')" data-tour="trash">
                        <Button>
                            <InboxOutlined /> {{ $t('global.view_deleted') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="canSeeAudit && canUsePlanFeature('audit_log_view')" :title="$t('global.audit_hint')">
                    <Link :href="route('system_management.audit_logs.index', { module: 'users' })" data-tour="audit">
                        <Button>
                            <AuditOutlined /> {{ $t('sidebar.audit_logs') }}
                        </Button>
                    </Link>
                </Tooltip>
                <Tooltip v-if="can('users.create')" :title="$t('global.create_record_hint')">
                    <Link :href="route('user_management.users.create')" class="hide-on-mobile">
                        <Button type="primary">
                            <PlusOutlined /> {{ $t('users.new') }}
                        </Button>
                    </Link>
                </Tooltip>
            </Space>
        </div>

        <!-- Filtros: desktop inline. En mobile vive en el drawer de Filtros. -->
        <FilterBar
            v-if="!isMobileScreen"
            :fields="filterFields"
            v-model="filters"
            storage-key="users"
            data-tour="filters"
        />

        <!-- Chips de filtros activos: visibles tambien en mobile (como Regions). -->
        <div v-auto-animate>
            <FilterChips :fields="filterFields" v-model="filters" />
        </div>

        <!-- Tabla -->
        <Card :bodyStyle="{ padding: 0 }" class="grid-card">
            <!-- Bulk bar: dentro del card. Mobile: sticky al pie (patrón Regions). -->
            <div v-auto-animate>
                <UsersBulkBar
                    v-if="selectedRowKeys.length > 0"
                    :count="selectedRowKeys.length"
                    :is-mobile="isMobileScreen"
                    :can-edit="can('users.edit')"
                    :can-delete="can('users.delete')"
                    @cancel="selectedRowKeys = []"
                    @set-active="bulkSetActive"
                    @delete="bulkDeleteModalOpen = true"
                />
            </div>

            <ResponsiveTable
                :dataSource="props.users.data"
                :columns="columns"
                :pagination="tablePagination"
                :loading="tableLoading"
                rowKey="id"
                :row-selection="{ selectedRowKeys, onChange: onSelectChange }"
                data-tour="bulk"
                @change="onTableChange"
                @row-click="openDetails"
            >
                <template #bodyCell="{ column, record, isMobile }">
                    <UsersFavoriteCell
                        v-if="column.key === 'favorite'"
                        :record="record"
                        :submitting="favoriteSubmitting"
                        :data-tour="record === props.users.data[0] ? 'favorites' : null"
                        @toggle="toggleFavorite"
                    />

                    <template v-else-if="column.key === 'photo'">
                        <UserAvatar :photo="record.photo" :name="record.name" :size="36" :updated-at="record.updated_at" />
                    </template>

                    <template v-else-if="column.key === 'role'">
                        <Tag v-if="record.role" :color="roleTagColor(record.role)" :bordered="false">
                            {{ record.role }}
                        </Tag>
                        <span v-else class="muted">—</span>
                    </template>

                    <template v-else-if="column.key === 'tenant'">
                        <Tag v-if="record.tenant" color="blue" :bordered="false">
                            {{ record.tenant.name }}
                        </Tag>
                        <Tag v-else color="purple" :bordered="false">{{ $t('global.platform') }}</Tag>
                    </template>

                    <template v-else-if="column.key === 'status'">
                        <Tag :color="record.is_active ? 'success' : 'error'" :bordered="false">
                            {{ record.is_active ? $t('global.active') : $t('global.inactive') }}
                        </Tag>
                    </template>

                    <template v-else-if="column.key === 'created_at'">
                        {{ formatDateTime(record.created_at) }}
                    </template>

                    <UsersActionsCell
                        v-else-if="column.key === 'actions'"
                        :record="record"
                        :is-mobile="isMobile"
                        :can-edit="can('users.edit')"
                        :can-create="can('users.create')"
                        :can-delete="can('users.delete')"
                    />
                </template>

                <template #empty>
                    <UsersEmptyState
                        :has-filters="hasActiveFilters"
                        :can-create="can('users.create')"
                        @clear-filters="clearFilters"
                    />
                </template>
            </ResponsiveTable>
        </Card>

        <UsersDetailDrawer
            v-model:open="drawerVisible"
            :user="selectedUser"
            :width="drawerWidth"
            :is-mobile="isMobileScreen"
            :can-edit="can('users.edit')"
            :can-delete="can('users.delete')"
            :is-super="isSuper"
        />

        <!-- ── Mobile: bottom bar fijo + drawers (patrón app real, como Regions) ── -->
        <UsersMobileBottomBar
            v-if="isMobileScreen && selectedRowKeys.length === 0"
            :can-create="can('users.create')"
            :has-active-filters="hasActiveFilters"
            @open-filters="filtersDrawerOpen = true"
            @create="goToCreate"
            @open-more="otrosDrawerOpen = true"
        />

        <UsersMobileDrawers
            v-model:filters-open="filtersDrawerOpen"
            v-model:otros-open="otrosDrawerOpen"
            v-model:filters="filters"
            v-model:visible-columns="visibleColumnKeys"
            :filter-fields="filterFields"
            :all-columns="allColumns"
            :can-create="can('users.create')"
            :can-edit="can('users.edit')"
            :is-super="isSuper"
            :can-see-audit="canSeeAudit && canUsePlanFeature('audit_log_view')"
            @open-export="exportOpen = true"
            @open-import="importOpen = true"
            @go-trash="goToTrash"
            @go-audit="goToAudit"
            @go-edit-all="goToEditAll"
        />

        <UsersBulkDeleteModal
            v-model:open="bulkDeleteModalOpen"
            v-model:reason="bulkDeleteReason"
            :count="selectedRowKeys.length"
            :resource-label="$t('users.records')"
            @confirm="submitBulkDelete"
        />

        <ExportDialog
            v-model:open="exportOpen"
            :columns="exportableColumns"
            :selected-ids="selectedRowKeys"
            :has-filters="hasActiveFilters"
            :current-filters="buildQueryData()"
            :default-title="$t('users.index_title')"
            :endpoints="exportEndpoints"
            :total-rows="users.total ?? 0"
            :total-unfiltered="users.total_unfiltered ?? users.total ?? 0"
        />

        <ImportDialog
            v-model:open="importOpen"
            :endpoint="route('user_management.users.import')"
            :template-url="route('user_management.users.import_template')"
            :resource-label="$t('users.records')"
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
.grid-card { border-radius: 6px; transition: box-shadow 0.18s ease; }
.grid-card:hover { box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); }

.grid-card :deep(.ant-table-thead > tr > th) {
    background: var(--color-surface-alt);
    color: var(--color-text-strong);
    font-weight: 600;
    font-size: 0.8125rem;
}
/* Animaciones de .grid-card (row stagger fade-in, hover lift, empty breathe,
   hover-to-reveal de acciones) viven globalmente en resources/css/app.css. */

@media (max-width: 768px) {
    .page-header { flex-direction: column; align-items: stretch; }
    .hide-on-mobile { display: none !important; }
}
</style>

<style>
html[data-theme="dark"] .grid-card .ant-table-thead > tr > th {
    background: #2c3034 !important; color: #e5e6e7 !important;
}

/* Espacio inferior para el bottom-bar fijo (mobile). Igual que Regions. */
@media (max-width: 767.98px) {
    .below-shell .content {
        padding-bottom: calc(env(safe-area-inset-bottom, 0px) + 150px) !important;
    }
}
</style>
