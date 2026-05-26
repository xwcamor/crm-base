<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, Tag, Table, Descriptions, DescriptionsItem, Row, Col, Empty, Button, Space, Alert } from 'ant-design-vue';
import {
    ShoppingCartOutlined, CarOutlined, HistoryOutlined,
    DollarOutlined, ShoppingOutlined, CreditCardOutlined, CalendarOutlined,
    FilePdfOutlined,
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
    order:           { type: Object, required: true },
    activity:        { type: Array,  default: () => [] },
    relatedInvoice:  { type: Object, default: null },
    deliveries:      { type: Array,  default: () => [] },
});

const { can, canSeeAudit, isSuper } = useAuth();
const { formatDate, formatDateTime, formatDateTimeFull } = useDateFormat();
const { t: $t } = useI18n();

const isDeleted = computed(() => !!props.order.deleted_at);

const statusColor = { pending: 'gold', processing: 'blue', partially_shipped: 'cyan', shipped: 'geekblue', delivered: 'green', cancelled: 'default', closed: 'purple' };
const paymentStatusColor = (s) => ({ unpaid: 'default', partial: 'orange', paid: 'green', overdue: 'red' }[s] || 'default');
const fmt = (n) => n == null ? '—' : new Intl.NumberFormat('es',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(n));
const cur = computed(() => props.order.currency_code || '');
const fmtDate = (d) => formatDateTimeFull(d);

const kpiTiles = computed(() => {
    const code = props.order.currency_code || '';
    const fmtMoney = (n) => code + ' ' + fmt(n);
    return [
        { icon: DollarOutlined,     label: $t('sales_orders.grand_total'),  value: fmtMoney(props.order.grand_total),                           color: 'primary' },
        { icon: ShoppingOutlined,   label: $t('sales_orders.items_title'),  value: (props.order.items?.length ?? 0).toString() },
        { icon: CreditCardOutlined, label: $t('sales_orders.payment_status'),value: $t(`sales_orders.payment_status_options.${props.order.payment_status}`),
          color: props.order.payment_status === 'paid' ? 'success' : (props.order.payment_status === 'overdue' ? 'danger' : 'default') },
        { icon: CalendarOutlined,   label: $t('sales_orders.order_date'),   value: props.order.order_date ? formatDate(props.order.order_date) : '—' },
    ];
});

const flowSteps = computed(() => {
    const s = props.order.status;
    if (s === 'cancelled') {
        return [
            { value: 'pending',           label: $t('sales_orders.status_options.pending') },
            { value: 'cancelled',         label: $t('sales_orders.status_options.cancelled'), isError: true },
        ];
    }
    return [
        { value: 'pending',           label: $t('sales_orders.status_options.pending') },
        { value: 'processing',        label: $t('sales_orders.status_options.processing') },
        { value: 'partially_shipped', label: $t('sales_orders.status_options.partially_shipped') },
        { value: 'shipped',           label: $t('sales_orders.status_options.shipped') },
        { value: 'delivered',         label: $t('sales_orders.status_options.delivered'), isTerminal: s === 'delivered' || s === 'closed' },
        ...(s === 'closed' ? [{ value: 'closed', label: $t('sales_orders.status_options.closed'), isTerminal: true }] : []),
    ];
});

const itemCols = [
    { title:'#', dataIndex:'sort_order', key:'idx', width:50, customRender: ({ index }) => index+1 },
    { title:'Producto', dataIndex:'name', key:'name' },
    { title:'SKU', dataIndex:'sku', key:'sku', width:120 },
    { title:'Pedido', dataIndex:'quantity_ordered', key:'ord', align:'right', width:90 },
    { title:'Entregado', dataIndex:'quantity_fulfilled', key:'ful', align:'right', width:100 },
    { title:'Precio', dataIndex:'unit_price', key:'unit', align:'right', width:120 },
    { title:'Total', dataIndex:'line_total', key:'total', align:'right', width:130 },
];
</script>

<template>
    <Head :title="order.reference + ' — OV'" />

    <SectionHeader
        :back-href="route('business_management.sales_orders.index')"
        :title="order.reference"
        :icon-bg="isDeleted ? 'var(--color-danger)' : 'var(--color-primary)'"
    >
        <template #icon><ShoppingCartOutlined /></template>
        <template #subtitle>
            <Space :size="6">
                <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                <Tag :color="statusColor[order.status]||'default'" :bordered="false">
                    {{ $t('sales_orders.status_options.' + order.status) }}
                </Tag>
                <span v-if="order.company?.name" class="muted">{{ order.company.name }}</span>
            </Space>
        </template>
        <template #actions>
            <Space>
                <a :href="route('business_management.sales_orders.show_pdf', order.slug)" target="_blank" rel="noopener">
                    <Button><FilePdfOutlined /> PDF</Button>
                </a>
                <Link
                    v-if="!isDeleted && !['delivered','cancelled','closed'].includes(order.status)"
                    :href="route('business_management.deliveries.create', { sales_order_id: order.id })"
                >
                    <Button type="primary"><CarOutlined /> {{ $t('sales_orders.create_delivery') }}</Button>
                </Link>
                <EntityShowActions
                    module="sales_orders"
                    route-prefix="business_management"
                    :slug="order.slug"
                    :id="order.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('sales_orders.edit')"
                    :can-delete="can('sales_orders.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </Space>
        </template>
    </SectionHeader>

    <KPITiles :tiles="kpiTiles" />
    <DocumentFlow :current-status="order.status" :steps="flowSteps" :title="$t('sales_orders.singular')" />

    <!-- Flujo del documento: Quote (origen) -> SO (actual) -> Invoice / Deliveries. -->
    <Card v-if="order.quote || relatedInvoice || deliveries.length > 0" style="margin-bottom: 16px;" :bodyStyle="{ padding: '12px 16px' }">
        <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
            <strong>{{ $t('global.document_flow') ?? 'Flujo del documento' }}:</strong>
            <Link v-if="order.quote" :href="route('business_management.quotes.show', order.quote.slug)">
                <Tag color="blue" :bordered="false">{{ $t('quotes.singular') }}: {{ order.quote.reference ?? '#' + order.quote.id }}</Tag>
            </Link>
            <span v-if="order.quote">→</span>
            <Tag color="cyan" :bordered="false">{{ $t('sales_orders.singular') }}: {{ order.reference ?? '#' + order.id }} ({{ $t('global.current') ?? 'actual' }})</Tag>
            <template v-if="relatedInvoice">
                <span>→</span>
                <Link :href="route('business_management.invoices.show', relatedInvoice.slug)">
                    <Tag color="green" :bordered="false">{{ $t('invoices.singular') }}: {{ relatedInvoice.reference ?? '#' + relatedInvoice.id }}</Tag>
                </Link>
            </template>
            <template v-if="deliveries.length > 0">
                <span>→</span>
                <Link
                    v-for="d in deliveries"
                    :key="d.id"
                    :href="route('business_management.deliveries.show', d.slug)"
                >
                    <Tag color="purple" :bordered="false">{{ $t('deliveries.singular') }}: {{ d.reference ?? '#' + d.id }}</Tag>
                </Link>
            </template>
        </div>
    </Card>

    <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
        <template #message>{{ $t('global.record_is_deleted') }}</template>
        <template #description>
            <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmtDate(order.deleted_at) }}</div>
            <div v-if="order.deleter">
                <strong>{{ $t('global.deleted_by') }}:</strong> {{ order.deleter.name }}
            </div>
            <div v-if="order.deleted_description">
                <strong>{{ $t('global.delete_description') }}:</strong> {{ order.deleted_description }}
            </div>
        </template>
    </Alert>

    <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="order"
        :activity="activity"
    >
        <template #general>
            <Row :gutter="[16,16]">
                <Col :xs="24" :md="16">
                    <Card :title="$t('sales_orders.items_title')" :bodyStyle="{padding:0}">
                        <Table :columns="itemCols" :data-source="order.items || []" :pagination="false" size="middle" row-key="id">
                            <template #bodyCell="{ column, record }">
                                <template v-if="column.key === 'ord'">{{ fmt(record.quantity_ordered) }}</template>
                                <template v-else-if="column.key === 'ful'">
                                    <strong :class="Number(record.quantity_fulfilled) >= Number(record.quantity_ordered) ? 'text-success' : 'text-warn'">
                                        {{ fmt(record.quantity_fulfilled) }}
                                    </strong>
                                </template>
                                <template v-else-if="column.key === 'unit'">{{ cur }} {{ fmt(record.unit_price) }}</template>
                                <template v-else-if="column.key === 'total'"><strong>{{ cur }} {{ fmt(record.line_total) }}</strong></template>
                            </template>
                            <template #emptyText><Empty :description="$t('sales_orders.no_items')" /></template>
                        </Table>
                    </Card>

                    <Card style="margin-top:16px" :bodyStyle="{padding:'16px 24px'}">
                        <div class="totals">
                            <div class="row"><span>{{ $t('sales_orders.subtotal') }}</span><span>{{ cur }} {{ fmt(order.subtotal) }}</span></div>
                            <div class="row"><span>{{ $t('sales_orders.tax_total') }}</span><span>{{ cur }} {{ fmt(order.tax_total) }}</span></div>
                            <div class="row" v-if="Number(order.shipping_cost) > 0">
                                <span>{{ $t('sales_orders.shipping_cost') }}</span><span>{{ cur }} {{ fmt(order.shipping_cost) }}</span>
                            </div>
                            <div class="row grand"><span>{{ $t('sales_orders.grand_total') }}</span><span>{{ cur }} {{ fmt(order.grand_total) }}</span></div>
                        </div>
                    </Card>
                </Col>

                <Col :xs="24" :md="8">
                    <Card :title="$t('sales_orders.company')">
                        <Descriptions :column="1" size="small">
                            <DescriptionsItem :label="$t('sales_orders.company')">
                                <Link v-if="order.company" :href="route('crm.companies.show', order.company.slug || order.company_id)">
                                    {{ order.company.name }}
                                </Link>
                            </DescriptionsItem>
                            <DescriptionsItem v-if="order.contact" :label="$t('sales_orders.contact')">
                                {{ order.contact.name }}
                            </DescriptionsItem>
                            <DescriptionsItem v-if="order.quote" :label="$t('sales_orders.from_quote')">
                                <Link :href="route('business_management.quotes.show', order.quote.slug)">
                                    {{ order.quote.reference }}
                                </Link>
                            </DescriptionsItem>
                        </Descriptions>
                    </Card>

                    <Card style="margin-top:16px">
                        <Descriptions :column="1" size="small">
                            <DescriptionsItem :label="$t('sales_orders.order_date')">{{ formatDate(order.order_date) }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('sales_orders.expected_delivery_date')">
                                {{ formatDate(order.expected_delivery_date) }}
                            </DescriptionsItem>
                            <DescriptionsItem v-if="order.shipped_at" :label="$t('sales_orders.shipped_at')">{{ formatDateTime(order.shipped_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="order.delivered_at" :label="$t('sales_orders.delivered_at')">{{ formatDateTime(order.delivered_at) }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('sales_orders.warehouse')">{{ order.warehouse?.name ?? '—' }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('sales_orders.payment_terms_days')">{{ order.payment_terms_days }} días</DescriptionsItem>
                            <DescriptionsItem :label="$t('sales_orders.payment_status')">
                                <Tag :color="paymentStatusColor(order.payment_status)" :bordered="false">
                                    {{ $t('sales_orders.payment_status_options.' + order.payment_status) }}
                                </Tag>
                            </DescriptionsItem>
                        </Descriptions>
                    </Card>

                    <Card style="margin-top:16px" :title="$t('global.record_audit')">
                        <Descriptions :column="1" size="small">
                            <DescriptionsItem v-if="isSuper" label="Slug">
                                <code class="muted">{{ order.slug }}</code>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.created_at')">{{ fmtDate(order.created_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="order.creator" :label="$t('global.created_by')">{{ order.creator.name }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('global.updated_at')">{{ fmtDate(order.updated_at) }}</DescriptionsItem>
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
.totals { display: flex; flex-direction: column; gap: 6px; max-width: 420px; margin-left: auto; }
.row { display: flex; justify-content: space-between; font-size: .9rem; }
.row.grand { font-size: 1.15rem; font-weight: 700; padding-top: 8px; margin-top: 4px; border-top: 1px solid var(--color-border, #e8e8e8); }
.text-success { color: #389e0d; }
.text-warn { color: #d48806; }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
</style>
