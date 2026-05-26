<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import { HistoryOutlined, SwapOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rate:     { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.rate.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="rate.display_name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('business_management.exchange_rates.index')"
            :title="`${rate.base_code} / ${rate.quote_code}`"
            :icon-bg="iconBg"
        >
            <template #icon><SwapOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="rate.is_active ? 'success' : 'default'" :bordered="false">
                        {{ rate.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="muted">ID #{{ rate.id }}</span>
                    <span class="muted">{{ rate.display_name }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="exchange_rates"
                    route-prefix="business_management"
                    :slug="rate.slug"
                    :id="rate.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('exchange_rates.edit')"
                    :can-delete="can('exchange_rates.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(rate.deleted_at) }}</div>
                <div v-if="rate.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ rate.deleter.name }}
                </div>
                <div v-if="rate.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ rate.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="exchange_rates" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="rate"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '200px' }">
                        <DescriptionsItem label="ID">{{ rate.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ rate.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('exchange_rates.base_code')">
                            <code>{{ rate.base_code }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('exchange_rates.quote_code')">
                            <code>{{ rate.quote_code }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('exchange_rates.rate')">
                            <strong>{{ Number(rate.rate).toFixed(6) }}</strong>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('exchange_rates.valid_at')">
                            {{ fmt(rate.valid_at) }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('exchange_rates.source')">{{ rate.source || '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('exchange_rates.is_active')">
                            <Tag :color="rate.is_active ? 'success' : 'default'" :bordered="false">
                                {{ rate.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(rate.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="rate.creator" :label="$t('global.created_by')">
                            {{ rate.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(rate.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(rate.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="rate.deleter" :label="$t('global.deleted_by')">
                                {{ rate.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ rate.deleted_description || '—' }}
                            </DescriptionsItem>
                        </template>
                    </Descriptions>
                </Card>

                <Card :bodyStyle="{ padding: 16 }" class="info-card">
                    <template #title>
                        <HistoryOutlined /> {{ $t('global.recent_activity') }}
                    </template>
                    <ActivityTimeline :activity="activity" />
                </Card>
            </template>
        </EntityShowTabs>
    </div>
</template>

<style scoped>
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 16px; border-radius: 6px; }

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
