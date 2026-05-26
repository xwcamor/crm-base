<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, Tag, Table, Descriptions, Row, Col, Empty, Button, Space } from 'ant-design-vue';
import {
    InboxOutlined, EditOutlined,
    DollarOutlined, ShoppingOutlined, CalendarOutlined, CheckSquareOutlined,
} from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import KPITiles from '@/Components/Common/KPITiles.vue';
import DocumentFlow from '@/Components/Common/DocumentFlow.vue';
import RecordMetaFooter from '@/Components/Common/RecordMetaFooter.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

const { formatDate } = useDateFormat();
const { t: $t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({ order: { type: Object, required: true } });
const statusColor = { draft: 'default', submitted: 'blue', confirmed: 'cyan', partially_received: 'gold', received: 'green', closed: 'purple', cancelled: 'red' };
const fmt = (n) => n == null ? '—' : new Intl.NumberFormat('es',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(n));
const cur = computed(() => props.order.currency_code || '');
const kpiTiles = computed(() => {
    const code = props.order.currency_code || '';
    const fmtMoney = (n) => code + ' ' + fmt(n);
    const totalOrdered = (props.order.items ?? []).reduce((acc, it) => acc + Number(it.quantity_ordered || 0), 0);
    const totalReceived = (props.order.items ?? []).reduce((acc, it) => acc + Number(it.quantity_received || 0), 0);
    const receivedPct = totalOrdered > 0 ? Math.round((totalReceived / totalOrdered) * 100) : 0;
    return [
        { icon: DollarOutlined,       label: $t('purchase_orders.grand_total'),  value: fmtMoney(props.order.grand_total), color: 'primary' },
        { icon: ShoppingOutlined,     label: $t('purchase_orders.items_title'), value: (props.order.items?.length ?? 0).toString() },
        { icon: CheckSquareOutlined,  label: $t('purchase_orders.received_pct'), value: receivedPct + '%',
          color: receivedPct >= 100 ? 'success' : (receivedPct > 0 ? 'warning' : 'default') },
        { icon: CalendarOutlined,     label: $t('purchase_orders.expected_delivery_date'), value: props.order.expected_delivery_date ? formatDate(props.order.expected_delivery_date) : '—' },
    ];
});

const flowSteps = computed(() => {
    const s = props.order.status;
    if (s === 'cancelled') {
        return [
            { value: 'draft',     label: $t('purchase_orders.status_options.draft') },
            { value: 'submitted', label: $t('purchase_orders.status_options.submitted') },
            { value: 'cancelled', label: $t('purchase_orders.status_options.cancelled'), isError: true },
        ];
    }
    return [
        { value: 'draft',              label: $t('purchase_orders.status_options.draft') },
        { value: 'submitted',          label: $t('purchase_orders.status_options.submitted') },
        { value: 'confirmed',          label: $t('purchase_orders.status_options.confirmed') },
        { value: 'partially_received', label: $t('purchase_orders.status_options.partially_received') },
        { value: 'received',           label: $t('purchase_orders.status_options.received'), isTerminal: s === 'received' },
        ...(s === 'closed' ? [{ value: 'closed', label: $t('purchase_orders.status_options.closed'), isTerminal: true }] : []),
    ];
});

const itemCols = [
    { title:'#', dataIndex:'sort_order', key:'idx', width:50, customRender: ({ index }) => index+1 },
    { title:'Producto', dataIndex:'name', key:'name' },
    { title:'Pedido', dataIndex:'quantity_ordered', key:'ord', align:'right', width:90 },
    { title:'Recibido', dataIndex:'quantity_received', key:'rec', align:'right', width:100 },
    { title:'Costo unit.', dataIndex:'unit_cost', key:'unit', align:'right', width:120 },
    { title:'Total', dataIndex:'line_total', key:'total', align:'right', width:130 },
];
</script>
<template>
    <Head :title="order.reference + ' — PO'" />
    <SectionHeader :back-href="route('business_management.purchase_orders.index')" :title="order.reference" :subtitle="order.supplier?.name">
        <template #icon><InboxOutlined /></template>
        <template #actions>
            <Space>
                <Tag :color="statusColor[order.status]||'default'" :bordered="false" style="font-size:.9rem;padding:4px 12px">{{ order.status?.toUpperCase() }}</Tag>
                <Link :href="route('business_management.purchase_orders.edit', order.slug)"><Button><EditOutlined /> Editar</Button></Link>
            </Space>
        </template>
    </SectionHeader>

    <KPITiles :tiles="kpiTiles" />
    <DocumentFlow :current-status="order.status" :steps="flowSteps" :title="$t('purchase_orders.singular')" />

    <Row :gutter="[16,16]">
        <Col :xs="24" :md="16">
            <Card title="Líneas" :bodyStyle="{padding:0}">
                <Table :columns="itemCols" :data-source="order.items || []" :pagination="false" size="middle" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'ord'">{{ fmt(record.quantity_ordered) }}</template>
                        <template v-else-if="column.key === 'rec'"><strong :class="Number(record.quantity_received) >= Number(record.quantity_ordered) ? 'text-success' : 'text-warn'">{{ fmt(record.quantity_received) }}</strong></template>
                        <template v-else-if="column.key === 'unit'">{{ cur }} {{ fmt(record.unit_cost) }}</template>
                        <template v-else-if="column.key === 'total'"><strong>{{ cur }} {{ fmt(record.line_total) }}</strong></template>
                    </template>
                    <template #emptyText><Empty description="Sin líneas" /></template>
                </Table>
            </Card>
            <Card style="margin-top:16px" :bodyStyle="{padding:'16px 24px'}">
                <div class="totals">
                    <div class="row"><span>Subtotal</span><span>{{ cur }} {{ fmt(order.subtotal) }}</span></div>
                    <div class="row"><span>Impuestos</span><span>{{ cur }} {{ fmt(order.tax_total) }}</span></div>
                    <div class="row grand"><span>Total</span><span>{{ cur }} {{ fmt(order.grand_total) }}</span></div>
                </div>
            </Card>
        </Col>
        <Col :xs="24" :md="8">
            <Card title="Proveedor">
                <Descriptions :column="1" size="small">
                    <Descriptions.Item label="Proveedor"><Link v-if="order.supplier" :href="route('crm.companies.show', order.supplier.slug || order.supplier_company_id)">{{ order.supplier.name }}</Link></Descriptions.Item>
                    <Descriptions.Item v-if="order.supplier?.tax_id" label="Tax ID">{{ order.supplier.tax_id }}</Descriptions.Item>
                </Descriptions>
            </Card>
            <Card style="margin-top:16px">
                <Descriptions :column="1" size="small">
                    <Descriptions.Item label="Fecha de orden">{{ formatDate(order.order_date) }}</Descriptions.Item>
                    <Descriptions.Item label="Entrega esperada">{{ formatDate(order.expected_delivery_date) }}</Descriptions.Item>
                    <Descriptions.Item label="Almacén destino">{{ order.warehouse?.name ?? '—' }}</Descriptions.Item>
                    <Descriptions.Item label="Términos pago">{{ order.payment_terms_days }} días</Descriptions.Item>
                    <Descriptions.Item v-if="order.delivery_type" label="Tipo entrega">{{ order.delivery_type }}</Descriptions.Item>
                </Descriptions>
            </Card>
        </Col>
    </Row>

    <RecordMetaFooter :record="order" />
</template>
<style scoped>
.totals{display:flex;flex-direction:column;gap:6px;max-width:420px;margin-left:auto}
.row{display:flex;justify-content:space-between;font-size:.9rem}
.row.grand{font-size:1.15rem;font-weight:700;padding-top:8px;margin-top:4px;border-top:1px solid var(--color-border, #e8e8e8)}
.text-success{color:#389e0d}.text-warn{color:#d48806}
</style>
