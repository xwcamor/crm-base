<script setup>
import { computed } from 'vue';
import { Head, usePage } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import { HistoryOutlined, UserOutlined } from '@ant-design/icons-vue';
import UserAvatar from '@/Components/Common/UserAvatar.vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const { formatDateTimeFull } = useDateFormat();

const props = defineProps({
    user:     { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
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

const isDeleted = computed(() => !!props.user.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
const lastUpdatedRel = computed(() => props.user.updated_at ? dayjs(props.user.updated_at).fromNow() : null);
</script>

<template>
    <Head :title="user.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('user_management.users.index')"
            :title="user.name"
            :icon-bg="iconBg"
        >
            <template #icon><UserOutlined /></template>
            <template #subtitle>
                <Space :size="6" class="show-page__meta">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="user.is_active ? 'success' : 'default'" :bordered="false">
                        {{ user.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="page-header__id">ID #{{ user.id }}</span>
                    <span v-if="lastUpdatedRel" class="page-header__rel">
                        · {{ $t('global.updated_at') }} {{ lastUpdatedRel }}
                    </span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="users"
                    :slug="user.slug"
                    :id="user.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('users.edit')"
                    :can-delete="can('users.delete')"
                    :can-see-audit="canSeeAudit"
                    route-prefix="user_management"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div class="deleted-info">
                    <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(user.deleted_at) }}</div>
                    <div v-if="user.deleter">
                        <strong>{{ $t('global.deleted_by') }}:</strong> {{ user.deleter.name }} ({{ user.deleter.email }})
                    </div>
                    <div v-if="user.deleted_description" class="deleted-reason">
                        <strong>{{ $t('global.delete_description') }}:</strong> {{ user.deleted_description }}
                    </div>
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="users" route-prefix="user_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="user"
        :activity="activity"
    >
            <!-- Tab 1 — Detalles: solo datos del dominio -->
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <div class="user-hero">
                        <UserAvatar
                            :photo="user.photo_url"
                            :name="user.name"
                            :size="56"
                            class="user-hero__avatar"
                            :class="{ 'user-hero__avatar--deleted': isDeleted }"
                        />
                        <div>
                            <h2>{{ user.name }}</h2>
                            <p class="user-hero__email">{{ user.email }}</p>
                        </div>
                    </div>
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ user.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper && user.slug" label="Slug">
                            <code class="muted">{{ user.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('users.name')">{{ user.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('users.email')">{{ user.email }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('users.is_active')">
                            <Tag :color="user.is_active ? 'success' : 'default'" :bordered="false">
                                {{ user.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <!-- Tab 2 — Historial: metadata del registro + timeline -->
            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(user.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="user.creator" :label="$t('global.created_by')">
                            {{ user.creator.name }}
                            <span class="muted">({{ user.creator.email }})</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(user.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(user.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="user.deleter" :label="$t('global.deleted_by')">
                                {{ user.deleter.name }}
                                <span class="muted">({{ user.deleter.email }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ user.deleted_description || '—' }}
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

.user-hero {
    display: flex;
    align-items: center;
    gap: 14px;
    padding: 16px 20px;
    border-bottom: 1px solid var(--color-border-soft);
    min-width: 0;
}
.user-hero > div {
    min-width: 0;
    flex: 1 1 auto;
}
.user-hero__avatar { flex-shrink: 0; border: 2px solid var(--color-primary); }
.user-hero__avatar--deleted { border-color: var(--color-danger); }
.user-hero h2 {
    font-size: 1.1rem; font-weight: 600; margin: 0; color: var(--color-text);
    word-break: break-word; overflow-wrap: anywhere;
}
.user-hero__email {
    font-size: 0.8125rem; color: var(--color-text-muted); margin: 2px 0 0 0;
    word-break: break-word; overflow-wrap: anywhere;
}

@media (max-width: 767px) {
    .user-hero { padding: 12px 14px; gap: 10px; }
    .user-hero h2 { font-size: 1rem; }
    .user-hero__email { word-break: break-word; }
    /* Override del labelStyle inline (width: 180px) del Descriptions.
       En mobile <400px deja muy poco espacio al valor. Auto-width + wrap. */
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
