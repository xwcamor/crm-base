<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import {
    GlobalOutlined, HistoryOutlined,
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
    locale:   { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.locale.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
const lastUpdatedRel = computed(() => props.locale.updated_at ? dayjs(props.locale.updated_at).fromNow() : null);
</script>

<template>
    <Head :title="locale.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('system_management.locales.index')"
            :title="locale.name"
            :icon-bg="iconBg"
        >
            <template #icon><GlobalOutlined /></template>
            <template #subtitle>
                <Space :size="6" class="show-page__meta">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="locale.is_active ? 'success' : 'default'" :bordered="false">
                        {{ locale.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="page-header__id">ID #{{ locale.id }}</span>
                    <span v-if="lastUpdatedRel" class="page-header__rel">
                        · {{ $t('global.updated_at') }} {{ lastUpdatedRel }}
                    </span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="locales"
                    :slug="locale.slug"
                    :id="locale.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('locales.edit')"
                    :can-delete="can('locales.delete')"
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
                    <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(locale.deleted_at) }}</div>
                    <div v-if="locale.deleter">
                        <strong>{{ $t('global.deleted_by') }}:</strong> {{ locale.deleter.name }} ({{ locale.deleter.email }})
                    </div>
                    <div v-if="locale.deleted_description" class="deleted-reason">
                        <strong>{{ $t('global.delete_description') }}:</strong> {{ locale.deleted_description }}
                    </div>
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="locales" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="locale"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ locale.id }}</DescriptionsItem>
                        <DescriptionsItem label="Slug"><code>{{ locale.slug }}</code></DescriptionsItem>
                        <DescriptionsItem :label="$t('locales.name')">{{ locale.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('locales.code')">
                            <code>{{ locale.code }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('locales.language')">
                            <template v-if="locale.language">
                                {{ locale.language.name }}
                                <code class="ml-1">({{ locale.language.iso_code }})</code>
                            </template>
                            <template v-else>—</template>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('locales.is_active')">
                            <Tag :color="locale.is_active ? 'success' : 'default'" :bordered="false">
                                {{ locale.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(locale.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="locale.creator" :label="$t('global.created_by')">
                            {{ locale.creator.name }}
                            <span class="muted">({{ locale.creator.email }})</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(locale.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(locale.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="locale.deleter" :label="$t('global.deleted_by')">
                                {{ locale.deleter.name }}
                                <span class="muted">({{ locale.deleter.email }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ locale.deleted_description || '—' }}
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
.ml-1 { margin-left: 4px; }
</style>
