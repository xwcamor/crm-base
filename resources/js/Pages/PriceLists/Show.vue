<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import { HistoryOutlined, TagsOutlined } from '@ant-design/icons-vue';

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
    priceList: { type: Object, required: true },
    activity:  { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.priceList.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="priceList.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('business_management.price_lists.index')"
            :title="priceList.name"
            :icon-bg="iconBg"
        >
            <template #icon><TagsOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="priceList.is_active ? 'success' : 'default'" :bordered="false">
                        {{ priceList.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <Tag v-if="priceList.is_default" color="gold" :bordered="false">{{ $t('price_lists.is_default') }}</Tag>
                    <span class="muted">ID #{{ priceList.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="price_lists"
                    route-prefix="business_management"
                    :slug="priceList.slug"
                    :id="priceList.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('price_lists.edit')"
                    :can-delete="can('price_lists.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(priceList.deleted_at) }}</div>
                <div v-if="priceList.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ priceList.deleter.name }}
                </div>
                <div v-if="priceList.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ priceList.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="price_lists" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="priceList"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '220px' }">
                        <DescriptionsItem label="ID">{{ priceList.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ priceList.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.name')">{{ priceList.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.description')">{{ priceList.description ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.currency')"><code>{{ priceList.currency_code ?? '—' }}</code></DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.global_discount_pct')">
                            <strong>{{ Number(priceList.global_discount_pct).toFixed(2) }}%</strong>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.priority')">{{ priceList.priority }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.valid_from')">{{ priceList.valid_from ? fmt(priceList.valid_from) : '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.valid_until')">{{ priceList.valid_until ? fmt(priceList.valid_until) : '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.is_default')">
                            <Tag :color="priceList.is_default ? 'gold' : 'default'" :bordered="false">
                                {{ priceList.is_default ? $t('global.yes') : $t('global.no') }}
                            </Tag>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('price_lists.is_active')">
                            <Tag :color="priceList.is_active ? 'success' : 'default'" :bordered="false">
                                {{ priceList.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(priceList.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="priceList.creator" :label="$t('global.created_by')">
                            {{ priceList.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(priceList.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(priceList.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="priceList.deleter" :label="$t('global.deleted_by')">
                                {{ priceList.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ priceList.deleted_description || '—' }}
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
