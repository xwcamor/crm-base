<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Collapse, CollapsePanel, Empty, Alert,
} from 'ant-design-vue';
import {
    TeamOutlined, SafetyCertificateOutlined, UserOutlined, HistoryOutlined,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    role:         { type: Object, required: true },
    permissions:  { type: Array,  default: () => [] },
    activity:     { type: Array,  default: () => [] },
    isSuper: { type: Boolean, default: false },
});

const { canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.role.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
const lastUpdatedRel = computed(() =>
    props.role.updated_at ? dayjs(props.role.updated_at).fromNow() : null,
);

// Agrupar permissions por modulo para mostrar en Collapse.
const groupedPermissions = computed(() => {
    const map = {};
    for (const p of props.permissions) {
        const mod = p.module ?? 'other';
        if (!map[mod]) map[mod] = [];
        map[mod].push(p);
    }
    return Object.entries(map)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([module, perms]) => ({ module, perms }));
});

// Badge de tipo de rol — system / global / tenant-scoped.
const roleScopeTag = computed(() => {
    if (props.role.is_system) return { color: 'purple', label: 'roles.tag_system' };
    if (props.role.tenant_id === null) return { color: 'orange', label: 'roles.tag_global' };
    return { color: 'blue', label: null }; // muestra tenant_name directo
});
</script>

<template>
    <Head :title="role.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('user_management.roles.index')"
            :title="role.name"
            :icon-bg="iconBg"
        >
            <template #icon><TeamOutlined /></template>
            <template #subtitle>
                <Space :size="6" class="show-page__meta">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="role.is_active ? 'success' : 'default'" :bordered="false">
                        {{ role.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <Tag :color="roleScopeTag.color" :bordered="false">
                        {{ roleScopeTag.label ? $t(roleScopeTag.label) : role.tenant_name }}
                    </Tag>
                    <span class="page-header__id">ID #{{ role.id }}</span>
                    <span v-if="lastUpdatedRel" class="page-header__rel">
                        · {{ $t('global.updated_at') }} {{ lastUpdatedRel }}
                    </span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="roles"
                    :slug="role.slug"
                    :id="role.id"
                    :is-deleted="isDeleted"
                    :can-edit="!role.is_system"
                    :can-delete="!role.is_system"
                    :can-see-audit="canSeeAudit"
                    route-prefix="user_management"
                    edit-protected-key="roles.protected"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div class="deleted-info">
                    <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(role.deleted_at) }}</div>
                    <div v-if="role.deleter">
                        <strong>{{ $t('global.deleted_by') }}:</strong> {{ role.deleter.name }} ({{ role.deleter.email }})
                    </div>
                    <div v-if="role.deleted_description" class="deleted-reason">
                        <strong>{{ $t('global.delete_description') }}:</strong> {{ role.deleted_description }}
                    </div>
                </div>
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="role"
        :activity="activity"
    >
            <!-- Tab 1 — Detalles: SOLO datos del dominio. -->
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ role.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper && role.slug" label="Slug">
                            <code class="muted">{{ role.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('roles.name')">
                            <strong>{{ role.name }}</strong>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('roles.description')">
                            {{ role.description || '—' }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('roles.scope')">
                            <Tag :color="roleScopeTag.color" :bordered="false">
                                {{ roleScopeTag.label ? $t(roleScopeTag.label) : role.tenant_name }}
                            </Tag>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('roles.is_active')">
                            <Tag :color="role.is_active ? 'success' : 'default'" :bordered="false">
                                {{ role.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('roles.users_count')">
                            <Tag :color="role.users_count > 0 ? 'green' : 'default'" :bordered="false">
                                <UserOutlined /> {{ role.users_count }}
                            </Tag>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('roles.permissions_count')">
                            <Tag :color="role.permissions_count > 0 ? 'cyan' : 'default'" :bordered="false">
                                <SafetyCertificateOutlined /> {{ role.permissions_count }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>

                <!-- Permissions asignados al rol — agrupados por modulo -->
                <Card :bodyStyle="{ padding: 16 }" class="info-card">
                    <template #title>
                        <Space>
                            <SafetyCertificateOutlined />
                            <span>{{ $t('roles.permissions') }}</span>
                            <Tag :bordered="false">{{ role.permissions_count }}</Tag>
                        </Space>
                    </template>

                    <Empty v-if="permissions.length === 0" :description="$t('roles.no_permissions')" />

                    <Collapse v-else ghost>
                        <CollapsePanel v-for="g in groupedPermissions" :key="g.module">
                            <template #header>
                                <Space>
                                    <strong>{{ g.module }}</strong>
                                    <Tag color="cyan" :bordered="false">{{ g.perms.length }}</Tag>
                                </Space>
                            </template>
                            <div class="perm-grid">
                                <Tag v-for="p in g.perms" :key="p.id" color="cyan" :bordered="false">
                                    {{ p.action }}
                                </Tag>
                            </div>
                        </CollapsePanel>
                    </Collapse>
                </Card>
            </template>

            <!-- Tab 2 — Historial: metadata del registro + timeline. Gated por canSeeAudit. -->
            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(role.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="role.creator" :label="$t('global.created_by')">
                            {{ role.creator.name }}
                            <span class="muted">({{ role.creator.email }})</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(role.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(role.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="role.deleter" :label="$t('global.deleted_by')">
                                {{ role.deleter.name }}
                                <span class="muted">({{ role.deleter.email }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ role.deleted_description || '—' }}
                            </DescriptionsItem>
                        </template>
                    </Descriptions>
                </Card>

                <Card :bodyStyle="{ padding: 16 }" class="info-card">
                    <template #title>
                        <span class="activity-card__title">
                            <HistoryOutlined /> {{ $t('global.recent_activity') }}
                        </span>
                    </template>
                    <ActivityTimeline :activity="activity" />
                </Card>
            </template>
        </EntityShowTabs>
    </div>
</template>

<style scoped>
.show-page { width: 100%; max-width: none; }
.show-page__meta { margin-top: 4px; }
.page-header__id,
.page-header__rel { font-size: 0.8125rem; color: var(--color-text-muted); }
.deleted-alert { margin-bottom: 16px; }
.deleted-info { display: flex; flex-direction: column; gap: 4px; font-size: 0.875rem; }
.deleted-reason { margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(0,0,0,0.1); }
.info-card { margin-bottom: 16px; border-radius: 6px; }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
.perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(140px, 1fr)); gap: 8px; padding: 8px 0; }

@media (max-width: 767px) {
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
