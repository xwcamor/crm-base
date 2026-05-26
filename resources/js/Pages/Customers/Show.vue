<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import { HistoryOutlined, TeamOutlined } from '@ant-design/icons-vue';

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
    customer: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.customer.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="customer.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('business_management.customers.index')"
            :title="customer.name"
            :icon-bg="iconBg"
        >
            <template #icon><TeamOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="customer.is_active ? 'success' : 'default'" :bordered="false">
                        {{ customer.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="muted">ID #{{ customer.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="customers"
                    route-prefix="business_management"
                    :slug="customer.slug"
                    :id="customer.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('customers.edit')"
                    :can-delete="can('customers.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(customer.deleted_at) }}</div>
                <div v-if="customer.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ customer.deleter.name }}
                </div>
                <div v-if="customer.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ customer.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="customers" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="customer"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ customer.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ customer.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('customers.name')">{{ customer.name }}</DescriptionsItem>
                        <!-- @scaffold:anchor description-show -->
                        <!-- @scaffold:remove-begin commercial-show -->
                        <DescriptionsItem :label="$t('customers.cod')">
                            <code v-if="customer.cod">{{ customer.cod }}</code>
                            <span v-else class="muted">—</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('customers.country')">
                            <Tag v-if="customer.country" :bordered="false">
                                {{ customer.country.iso_code }} · {{ customer.country.name }}
                            </Tag>
                            <span v-else class="muted">—</span>
                        </DescriptionsItem>
                        <!-- @scaffold:remove-end -->
                        <DescriptionsItem :label="$t('customers.is_active')">
                            <Tag :color="customer.is_active ? 'success' : 'default'" :bordered="false">
                                {{ customer.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(customer.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="customer.creator" :label="$t('global.created_by')">
                            {{ customer.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(customer.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(customer.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="customer.deleter" :label="$t('global.deleted_by')">
                                {{ customer.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ customer.deleted_description || '—' }}
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
.show-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }
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
