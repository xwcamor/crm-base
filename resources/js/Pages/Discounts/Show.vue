<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import { HistoryOutlined, PercentageOutlined } from '@ant-design/icons-vue';

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
    discount: { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.discount.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

const fmt = (d) => formatDateTimeFull(d);

const formatValue = (d) => {
    if (d.type === 'percentage') return `${d.value}%`;
    if (d.type === 'fixed_amount') return `${d.currency_code || ''} ${Number(d.value).toFixed(2)}`;
    return '—';
};
</script>

<template>
    <Head :title="discount.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('business_management.discounts.index')"
            :title="discount.name"
            :icon-bg="iconBg"
        >
            <template #icon><PercentageOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="discount.is_active ? 'success' : 'default'" :bordered="false">
                        {{ discount.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="muted">ID #{{ discount.id }}</span>
                    <code class="muted">{{ discount.code }}</code>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="discounts"
                    route-prefix="business_management"
                    :slug="discount.slug"
                    :id="discount.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('discounts.edit')"
                    :can-delete="can('discounts.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(discount.deleted_at) }}</div>
                <div v-if="discount.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ discount.deleter.name }}
                </div>
                <div v-if="discount.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ discount.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="discounts" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="discount"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '200px' }">
                        <DescriptionsItem label="ID">{{ discount.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ discount.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.code')"><code>{{ discount.code }}</code></DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.name')">{{ discount.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.description')">{{ discount.description ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.type')">{{ $t('discounts.type_options.' + discount.type) }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.value')"><strong>{{ formatValue(discount) }}</strong></DescriptionsItem>
                        <DescriptionsItem v-if="discount.min_purchase_amount" :label="$t('discounts.min_purchase_amount')">
                            {{ discount.min_purchase_amount }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.usage_count')">
                            {{ discount.usage_count }}{{ discount.usage_limit ? ' / ' + discount.usage_limit : '' }}
                        </DescriptionsItem>
                        <DescriptionsItem v-if="discount.usage_per_customer" :label="$t('discounts.usage_per_customer')">
                            {{ discount.usage_per_customer }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.valid_from')">{{ discount.valid_from ? fmt(discount.valid_from) : '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.valid_until')">{{ discount.valid_until ? fmt(discount.valid_until) : '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('discounts.is_active')">
                            <Tag :color="discount.is_active ? 'success' : 'default'" :bordered="false">
                                {{ discount.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(discount.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="discount.creator" :label="$t('global.created_by')">
                            {{ discount.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(discount.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(discount.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="discount.deleter" :label="$t('global.deleted_by')">
                                {{ discount.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ discount.deleted_description || '—' }}
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
