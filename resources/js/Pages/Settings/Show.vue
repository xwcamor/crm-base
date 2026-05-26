<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import {
    SettingOutlined, HistoryOutlined, EyeInvisibleOutlined,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

import AppLayout from '@/Layouts/AppLayout.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    setting:  { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.setting.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
const lastUpdatedRel = computed(() => props.setting.updated_at ? dayjs(props.setting.updated_at).fromNow() : null);

// Format del value según el type — secret se enmascara siempre.
const displayValue = computed(() => {
    if (props.setting.is_secret) return '••••••••';
    const t = props.setting.type;
    const v = props.setting.value;
    if (v === null || v === undefined || v === '') return '—';
    if (t === 'bool' || t === 'boolean') return v ? 'true' : 'false';
    if (t === 'json' && typeof v !== 'string') return JSON.stringify(v, null, 2);
    return String(v);
});

const typeColor = computed(() => {
    switch (props.setting.type) {
        case 'int':
        case 'integer': return 'geekblue';
        case 'bool':
        case 'boolean': return 'purple';
        case 'json':    return 'orange';
        default:        return 'default';
    }
});
</script>

<template>
    <Head :title="setting.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('system_management.settings.index')"
            :title="setting.name"
            :icon-bg="iconBg"
        >
            <template #icon><SettingOutlined /></template>
            <template #subtitle>
                <Space :size="6" class="show-page__meta">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="setting.is_active ? 'success' : 'default'" :bordered="false">
                        {{ setting.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="page-header__id">ID #{{ setting.id }}</span>
                    <span v-if="lastUpdatedRel" class="page-header__rel">
                        · {{ $t('global.updated_at') }} {{ lastUpdatedRel }}
                    </span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="settings"
                    :slug="setting.slug"
                    :id="setting.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('settings.edit')"
                    :can-delete="can('settings.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert
            v-if="isDeleted"
            type="error"
            show-icon
            class="deleted-alert"
        >
            <template #message>
                <span v-html="$t('global.record_is_deleted')" />
            </template>
            <template #description>
                <div class="deleted-info">
                    <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(setting.deleted_at) }}</div>
                    <div v-if="setting.deleter">
                        <strong>{{ $t('global.deleted_by') }}:</strong> {{ setting.deleter.name }} ({{ setting.deleter.email }})
                    </div>
                    <div v-if="setting.deleted_description" class="deleted-reason">
                        <strong>{{ $t('global.delete_description') }}:</strong> {{ setting.deleted_description }}
                    </div>
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="settings" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="setting"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ setting.id }}</DescriptionsItem>
                        <DescriptionsItem label="Slug"><code>{{ setting.slug }}</code></DescriptionsItem>
                        <DescriptionsItem :label="$t('settings.name')">{{ setting.name }}</DescriptionsItem>
                        <DescriptionsItem v-if="setting.key" label="Key"><code>{{ setting.key }}</code></DescriptionsItem>
                        <DescriptionsItem v-if="setting.group" label="Group">
                            <Tag :bordered="false">{{ setting.group }}</Tag>
                        </DescriptionsItem>
                        <DescriptionsItem v-if="setting.type" label="Type">
                            <Tag :color="typeColor" :bordered="false">{{ setting.type }}</Tag>
                        </DescriptionsItem>
                        <DescriptionsItem label="Value">
                            <Space :size="6">
                                <EyeInvisibleOutlined v-if="setting.is_secret" />
                                <code class="value-pre">{{ displayValue }}</code>
                            </Space>
                        </DescriptionsItem>
                        <DescriptionsItem v-if="setting.description" :label="$t('global.description') || 'Descripción'">
                            {{ setting.description }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('settings.is_active')">
                            <Tag :color="setting.is_active ? 'success' : 'default'" :bordered="false">
                                {{ setting.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(setting.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="setting.creator" :label="$t('global.created_by')">
                            {{ setting.creator.name }}
                            <span class="muted">({{ setting.creator.email }})</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(setting.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(setting.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="setting.deleter" :label="$t('global.deleted_by')">
                                {{ setting.deleter.name }}
                                <span class="muted">({{ setting.deleter.email }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ setting.deleted_description || '—' }}
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
.show-page__meta { margin-top: 4px; }
.page-header__id,
.page-header__rel {
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}
.deleted-alert { margin-bottom: 16px; }
.deleted-info { display: flex; flex-direction: column; gap: 4px; font-size: 0.875rem; }
.deleted-reason { margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(0,0,0,0.1); }
.info-card { margin-bottom: 16px; border-radius: 6px; }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
.activity-card__title { display: inline-flex; align-items: center; gap: 6px; }
.value-pre {
    display: inline-block;
    max-width: 100%;
    white-space: pre-wrap;
    word-break: break-word;
    line-height: 1.4;
}
</style>
