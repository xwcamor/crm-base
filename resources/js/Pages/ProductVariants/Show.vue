<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import { HistoryOutlined, AppstoreAddOutlined } from '@ant-design/icons-vue';

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
    variant:  { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.variant.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

const attributePairs = computed(() => {
    if (!props.variant.attributes || typeof props.variant.attributes !== 'object') return [];
    return Object.entries(props.variant.attributes).map(([k, v]) => ({ key: k, value: v }));
});

const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="variant.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('business_management.product_variants.index')"
            :title="variant.name"
            :icon-bg="iconBg"
        >
            <template #icon><AppstoreAddOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="variant.is_active ? 'success' : 'default'" :bordered="false">
                        {{ variant.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <code class="sku-code">{{ variant.sku }}</code>
                    <span class="muted">ID #{{ variant.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="product_variants"
                    route-prefix="business_management"
                    :slug="variant.slug"
                    :id="variant.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('product_variants.edit')"
                    :can-delete="can('product_variants.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(variant.deleted_at) }}</div>
                <div v-if="variant.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ variant.deleter.name }}
                </div>
                <div v-if="variant.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ variant.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="product_variants" route-prefix="business_management" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="variant"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '200px' }">
                        <DescriptionsItem label="ID">{{ variant.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ variant.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.sku')">
                            <code class="sku-code">{{ variant.sku }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.name')">{{ variant.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.product')">
                            {{ variant.product?.name ?? '—' }}
                            <span v-if="variant.product?.sku" class="muted">({{ variant.product.sku }})</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.barcode')">{{ variant.barcode ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem v-if="attributePairs.length" :label="$t('product_variants.attributes')">
                            <div class="attr-chips">
                                <Tag v-for="pair in attributePairs" :key="pair.key" color="blue" :bordered="false">
                                    <strong>{{ pair.key }}:</strong> {{ pair.value }}
                                </Tag>
                            </div>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.cost')">{{ variant.cost ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.price')">{{ variant.price ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.low_stock_threshold')">{{ variant.low_stock_threshold }}</DescriptionsItem>
                        <DescriptionsItem v-if="variant.image_url" :label="$t('product_variants.image_url')">
                            <a :href="variant.image_url" target="_blank" rel="noopener">{{ variant.image_url }}</a>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.sort_order')">{{ variant.sort_order }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('product_variants.is_active')">
                            <Tag :color="variant.is_active ? 'success' : 'default'" :bordered="false">
                                {{ variant.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(variant.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="variant.creator" :label="$t('global.created_by')">
                            {{ variant.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(variant.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(variant.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="variant.deleter" :label="$t('global.deleted_by')">
                                {{ variant.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ variant.deleted_description || '—' }}
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
.attr-chips { display: flex; flex-wrap: wrap; gap: 4px; }
.sku-code {
    font-family: var(--font-mono, monospace);
    font-size: 0.8125rem;
    color: var(--color-text-strong);
    background: var(--color-surface-alt);
    padding: 2px 6px;
    border-radius: 3px;
}

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
