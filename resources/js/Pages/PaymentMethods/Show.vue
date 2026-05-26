<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import { HistoryOutlined, CreditCardOutlined } from '@ant-design/icons-vue';

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
    method:   { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.method.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="method.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('business_management.payment_methods.index')"
            :title="method.name"
            :icon-bg="iconBg"
        >
            <template #icon><CreditCardOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="method.is_active ? 'success' : 'default'" :bordered="false">
                        {{ method.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="muted">ID #{{ method.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="payment_methods"
                    route-prefix="business_management"
                    :slug="method.slug"
                    :id="method.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('payment_methods.edit')"
                    :can-delete="can('payment_methods.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(method.deleted_at) }}</div>
                <div v-if="method.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ method.deleter.name }}
                </div>
                <div v-if="method.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ method.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="payment_methods" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="method"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '200px' }">
                        <DescriptionsItem label="ID">{{ method.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ method.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('payment_methods.name')">{{ method.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('payment_methods.code')">{{ method.code ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('payment_methods.description')">{{ method.description ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('payment_methods.integration_provider')">
                            {{ method.integration_provider ?? '—' }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('payment_methods.requires_reference')">
                            <Tag :color="method.requires_reference ? 'blue' : 'default'" :bordered="false">
                                {{ method.requires_reference ? $t('global.yes') : $t('global.no') }}
                            </Tag>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('payment_methods.sort_order')">{{ method.sort_order }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('payment_methods.is_active')">
                            <Tag :color="method.is_active ? 'success' : 'default'" :bordered="false">
                                {{ method.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(method.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="method.creator" :label="$t('global.created_by')">
                            {{ method.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(method.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(method.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="method.deleter" :label="$t('global.deleted_by')">
                                {{ method.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ method.deleted_description || '—' }}
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
