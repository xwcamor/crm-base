<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, Table, Tag, Descriptions, DescriptionsItem, Row, Col, Empty, Space, Alert } from 'ant-design-vue';
import {
    CarOutlined, HistoryOutlined,
    ShoppingOutlined, NumberOutlined, CalendarOutlined, FileTextOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import KPITiles from '@/Components/Common/KPITiles.vue';
import DocumentFlow from '@/Components/Common/DocumentFlow.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    delivery: { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { t } = useI18n();
const { can, canSeeAudit, isSuper } = useAuth();
const { formatDate, formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.delivery.deleted_at);

const statusColor = (s) => ({
    pending: 'default', picking: 'cyan', packed: 'blue', shipped: 'geekblue', delivered: 'green', returned: 'red',
}[s] || 'default');

const fmt = (n) => n == null ? '—' : Number(n).toFixed(2);
const fmtDate = (d) => formatDateTimeFull(d);

const kpiTiles = computed(() => {
    const items = props.delivery.items ?? [];
    const totalQty = items.reduce((acc, it) => acc + Number(it.quantity || 0), 0);
    return [
        { icon: ShoppingOutlined,  label: t('deliveries.items_title'),    value: items.length.toString() },
        { icon: NumberOutlined,    label: t('deliveries.total_qty'), value: totalQty.toString() },
        { icon: CalendarOutlined,  label: t('deliveries.shipped_at'),     value: props.delivery.shipped_at ? formatDate(props.delivery.shipped_at) : '—',
          color: props.delivery.shipped_at ? 'success' : 'default' },
        { icon: FileTextOutlined,  label: t('deliveries.tracking_number'),value: props.delivery.tracking_number || '—' },
    ];
});

const flowSteps = computed(() => {
    const s = props.delivery.status;
    if (s === 'returned') {
        return [
            { value: 'pending',   label: t('deliveries.status_options.pending') },
            { value: 'shipped',   label: t('deliveries.status_options.shipped') },
            { value: 'returned',  label: t('deliveries.status_options.returned'), isError: true },
        ];
    }
    return [
        { value: 'pending',   label: t('deliveries.status_options.pending') },
        { value: 'picking',   label: t('deliveries.status_options.picking') },
        { value: 'packed',    label: t('deliveries.status_options.packed') },
        { value: 'shipped',   label: t('deliveries.status_options.shipped') },
        { value: 'delivered', label: t('deliveries.status_options.delivered'), isTerminal: true },
    ];
});

const itemCols = [
    { title: t('deliveries.items_title'), dataIndex: ['product', 'name'], key: 'product' },
    { title: 'SKU',                       dataIndex: ['product', 'sku'],  key: 'sku', width: 130 },
    { title: t('deliveries.item_qty_now'), dataIndex: 'quantity',         key: 'qty', align: 'right', width: 120 },
];
</script>

<template>
    <Head :title="delivery.reference + ' — ' + $t('deliveries.singular')" />

    <SectionHeader
        :back-href="route('business_management.deliveries.index')"
        :title="delivery.reference"
        :icon-bg="isDeleted ? 'var(--color-danger)' : 'var(--color-primary)'"
    >
        <template #icon><CarOutlined /></template>
        <template #subtitle>
            <Space :size="6">
                <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                <Tag :color="statusColor(delivery.status)" :bordered="false">
                    {{ $t('deliveries.status_options.' + delivery.status) }}
                </Tag>
                <span v-if="delivery.salesOrder?.reference" class="muted">{{ delivery.salesOrder.reference }}</span>
            </Space>
        </template>
        <template #actions>
            <EntityShowActions
                module="deliveries"
                route-prefix="business_management"
                :slug="delivery.slug"
                :id="delivery.id"
                :is-deleted="isDeleted"
                :can-edit="can('deliveries.edit')"
                :can-delete="can('deliveries.delete')"
                :can-see-audit="canSeeAudit"
            />
        </template>
    </SectionHeader>

    <KPITiles :tiles="kpiTiles" />
    <DocumentFlow :current-status="delivery.status" :steps="flowSteps" :title="$t('deliveries.singular')" />

    <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
        <template #message>{{ $t('global.record_is_deleted') }}</template>
        <template #description>
            <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmtDate(delivery.deleted_at) }}</div>
            <div v-if="delivery.deleter">
                <strong>{{ $t('global.deleted_by') }}:</strong> {{ delivery.deleter.name }}
            </div>
            <div v-if="delivery.deleted_description">
                <strong>{{ $t('global.delete_description') }}:</strong> {{ delivery.deleted_description }}
            </div>
        </template>
    </Alert>

    <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="delivery"
        :activity="activity"
    >
        <template #general>
            <Row :gutter="[16, 16]">
                <Col :xs="24" :md="16">
                    <Card :title="$t('deliveries.items_title')" :bodyStyle="{padding: 0}">
                        <Table :columns="itemCols" :data-source="delivery.items || []" :pagination="false" size="middle" row-key="id">
                            <template #bodyCell="{ column, record }">
                                <template v-if="column.key === 'qty'"><strong>{{ Number(record.quantity).toFixed(2) }}</strong></template>
                            </template>
                            <template #emptyText><Empty :description="$t('deliveries.no_items')" /></template>
                        </Table>
                    </Card>
                </Col>

                <Col :xs="24" :md="8">
                    <Card :title="$t('deliveries.show_title')">
                        <Descriptions :column="1" size="small">
                            <DescriptionsItem :label="$t('deliveries.sales_order')">
                                <Link v-if="delivery.salesOrder" :href="route('business_management.sales_orders.show', delivery.salesOrder.slug)">
                                    {{ delivery.salesOrder.reference }}
                                </Link>
                                <span v-else>—</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('deliveries.warehouse')">
                                {{ delivery.warehouse?.name }} <span v-if="delivery.warehouse?.code">({{ delivery.warehouse.code }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('deliveries.carrier')">{{ delivery.carrier ?? '—' }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('deliveries.tracking_number')">
                                <code v-if="delivery.tracking_number" class="mono">{{ delivery.tracking_number }}</code>
                                <span v-else>—</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('deliveries.shipping_method')">{{ delivery.shipping_method ?? '—' }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('deliveries.shipping_cost')">{{ fmt(delivery.shipping_cost) }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('deliveries.shipped_at')">{{ delivery.shipped_at ? fmtDate(delivery.shipped_at) : '—' }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('deliveries.delivered_at')">{{ delivery.delivered_at ? fmtDate(delivery.delivered_at) : '—' }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('deliveries.signed_by_name')">{{ delivery.signed_by_name ?? '—' }}</DescriptionsItem>
                            <DescriptionsItem v-if="delivery.notes" :label="$t('deliveries.notes')">{{ delivery.notes }}</DescriptionsItem>
                        </Descriptions>
                    </Card>

                    <Card style="margin-top:16px" :title="$t('global.record_audit')">
                        <Descriptions :column="1" size="small">
                            <DescriptionsItem v-if="isSuper" label="Slug">
                                <code class="muted">{{ delivery.slug }}</code>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.created_at')">{{ fmtDate(delivery.created_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="delivery.creator" :label="$t('global.created_by')">{{ delivery.creator.name }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('global.updated_at')">{{ fmtDate(delivery.updated_at) }}</DescriptionsItem>
                        </Descriptions>
                    </Card>
                </Col>
            </Row>
        </template>

        <template #history>
            <Card :bodyStyle="{padding:16}">
                <template #title>
                    <HistoryOutlined /> {{ $t('global.recent_activity') }}
                </template>
                <ActivityTimeline :activity="activity" />
            </Card>
        </template>
    </EntityShowTabs>
</template>

<style scoped>
.mono { font-family: ui-monospace, Consolas, monospace; font-size: 0.8125rem; }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
</style>
