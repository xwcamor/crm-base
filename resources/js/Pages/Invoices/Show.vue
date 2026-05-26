<script setup>
import { computed, ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, Tag, Table, Descriptions, Row, Col, Empty, Progress, Button, Space, Popconfirm, Modal, Input } from 'ant-design-vue';
import {
    FileTextOutlined, CloseCircleOutlined, DollarCircleOutlined, EditOutlined,
    DollarOutlined, CreditCardOutlined, CalendarOutlined, PercentageOutlined,
    FilePdfOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import KPITiles from '@/Components/Common/KPITiles.vue';
import DocumentFlow from '@/Components/Common/DocumentFlow.vue';
import RecordMetaFooter from '@/Components/Common/RecordMetaFooter.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

const { formatDate, formatDateTime } = useDateFormat();
const { t: $t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    invoice: { type: Object, required: true },
});

const statusColor = {
    draft: 'default', sent: 'blue', paid: 'green', partial: 'gold',
    overdue: 'red', cancelled: 'default', refunded: 'purple',
};

const fmt = (n) => {
    if (n == null) return '—';
    const v = Number(n);
    if (Number.isNaN(v)) return '—';
    return new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
};

const cur = computed(() => props.invoice.currency_code || '');

const cancelOpen = ref(false);
const cancelReason = ref('');
const canCancel = computed(() => !['paid', 'refunded', 'cancelled'].includes(props.invoice.status));
const onCancel = () => { router.post(route('business_management.invoices.cancel', props.invoice.slug), { cancellation_reason: cancelReason.value }); cancelOpen.value = false; };

const paidPct = computed(() => {
    const total = Number(props.invoice.grand_total) || 0;
    const paid  = Number(props.invoice.amount_paid) || 0;
    if (total <= 0) return 0;
    return Math.round((paid / total) * 100);
});

const kpiTiles = computed(() => {
    const code = props.invoice.currency_code || '';
    const fmtMoney = (n) => code + ' ' + fmt(n);
    return [
        { icon: DollarOutlined,     label: $t('invoices.grand_total'), value: fmtMoney(props.invoice.grand_total), color: 'primary' },
        { icon: CreditCardOutlined, label: $t('invoices.amount_paid'), value: fmtMoney(props.invoice.amount_paid), color: 'success' },
        { icon: PercentageOutlined, label: $t('invoices.balance_due'), value: fmtMoney(props.invoice.balance_due),
          color: Number(props.invoice.balance_due) > 0 ? 'warning' : 'default' },
        { icon: CalendarOutlined,   label: $t('invoices.due_date'),    value: props.invoice.due_date ? formatDate(props.invoice.due_date) : '—' },
    ];
});

const flowSteps = computed(() => {
    const s = props.invoice.status;
    if (s === 'cancelled' || s === 'refunded') {
        return [
            { value: 'draft', label: $t('invoices.status_options.draft') },
            { value: 'sent',  label: $t('invoices.status_options.sent') },
            { value: s,       label: $t(`invoices.status_options.${s}`), isError: s === 'cancelled', isTerminal: s === 'refunded' },
        ];
    }
    return [
        { value: 'draft',   label: $t('invoices.status_options.draft') },
        { value: 'sent',    label: $t('invoices.status_options.sent') },
        ...(s === 'partial' ? [{ value: 'partial', label: $t('invoices.status_options.partial') }] : []),
        ...(s === 'overdue' ? [{ value: 'overdue', label: $t('invoices.status_options.overdue'), isError: true }] : []),
        { value: 'paid',    label: $t('invoices.status_options.paid'), isTerminal: true },
    ];
});

const itemColumns = [
    { title: '#', dataIndex: 'sort_order', key: 'idx', width: 50, customRender: ({ index }) => index + 1 },
    { title: 'Producto', dataIndex: 'name', key: 'name' },
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 110 },
    { title: 'Cant.', dataIndex: 'quantity', key: 'qty', align: 'right', width: 80 },
    { title: 'Precio unit.', dataIndex: 'unit_price', key: 'unit', align: 'right', width: 120 },
    { title: '% Imp.', dataIndex: 'tax_pct', key: 'tax', align: 'right', width: 80 },
    { title: 'Total', dataIndex: 'line_total', key: 'total', align: 'right', width: 130 },
];

const paymentColumns = [
    { title: 'Fecha', dataIndex: 'paid_at', key: 'paid_at', width: 160 },
    { title: 'Referencia', dataIndex: 'reference', key: 'reference', width: 160 },
    { title: 'Método', dataIndex: ['paymentMethod', 'name'], key: 'method' },
    { title: 'Monto', dataIndex: 'amount', key: 'amount', align: 'right', width: 140 },
    { title: 'Estado', dataIndex: 'status', key: 'status', width: 120 },
];
</script>

<template>
    <Head :title="invoice.number + ' — ' + $t('invoices.show_title')" />

    <div>
        <SectionHeader
            :back-href="route('business_management.invoices.index')"
            :title="invoice.number || $t('invoices.show_title')"
            :subtitle="invoice.company?.name"
        >
            <template #icon><FileTextOutlined /></template>
            <template #actions>
                <Space wrap>
                    <Tag :color="statusColor[invoice.status] || 'default'" :bordered="false" style="font-size: 0.9rem; padding: 4px 12px">
                        {{ invoice.status?.toUpperCase() }}
                    </Tag>
                    <Link :href="route('business_management.invoices.edit', invoice.slug)"><Button><EditOutlined /> Editar</Button></Link>
                    <a :href="route('business_management.invoices.show_pdf', invoice.slug)" target="_blank" rel="noopener">
                        <Button><FilePdfOutlined /> PDF</Button>
                    </a>
                    <Link v-if="Number(invoice.balance_due) > 0 && !['cancelled','refunded'].includes(invoice.status)"
                          :href="route('business_management.payments.create', { invoice_id: invoice.id })">
                        <Button type="primary"><DollarCircleOutlined /> Registrar pago</Button>
                    </Link>
                    <Button v-if="canCancel" danger @click="cancelOpen = true"><CloseCircleOutlined /> Anular</Button>
                </Space>
            </template>
        </SectionHeader>

        <KPITiles :tiles="kpiTiles" />
        <DocumentFlow :current-status="invoice.status" :steps="flowSteps" :title="$t('invoices.singular')" />

        <Modal v-model:open="cancelOpen" title="Anular factura" @ok="onCancel" ok-text="Anular" cancel-text="Cancelar" ok-button-props="{ danger: true }">
            <p class="muted">La factura quedará marcada como anulada con timestamp y motivo.</p>
            <Input.TextArea v-model:value="cancelReason" :rows="3" :maxlength="500" placeholder="Motivo (ej: error de emisión / cliente desistió)" />
        </Modal>

        <!-- Flujo del documento: cadena Quote -> SalesOrder -> esta Factura.
             Solo se muestra si hay al menos un upstream document linkeado. -->
        <Card v-if="invoice.salesOrder || invoice.salesOrder?.quote" style="margin-bottom: 16px;" :bodyStyle="{ padding: '12px 16px' }">
            <div style="display: flex; flex-wrap: wrap; align-items: center; gap: 12px;">
                <strong>{{ $t('global.document_flow') ?? 'Flujo del documento' }}:</strong>
                <Link v-if="invoice.salesOrder?.quote" :href="route('business_management.quotes.show', invoice.salesOrder.quote.slug)">
                    <Tag color="blue" :bordered="false">{{ $t('quotes.singular') }}: {{ invoice.salesOrder.quote.reference ?? '#' + invoice.salesOrder.quote.id }}</Tag>
                </Link>
                <span v-if="invoice.salesOrder?.quote">→</span>
                <Link v-if="invoice.salesOrder" :href="route('business_management.sales_orders.show', invoice.salesOrder.slug)">
                    <Tag color="cyan" :bordered="false">{{ $t('sales_orders.singular') }}: {{ invoice.salesOrder.reference ?? '#' + invoice.salesOrder.id }}</Tag>
                </Link>
                <span v-if="invoice.salesOrder">→</span>
                <Tag color="green" :bordered="false">{{ $t('invoices.singular') }}: {{ invoice.reference ?? '#' + invoice.id }} ({{ $t('global.current') ?? 'actual' }})</Tag>
            </div>
        </Card>

        <Row :gutter="[16, 16]">
            <Col :xs="24" :md="16">
                <Card :title="$t('invoices.items_title')" :bodyStyle="{ padding: 0 }">
                    <Table
                        :columns="itemColumns"
                        :data-source="invoice.items || []"
                        :pagination="false"
                        size="middle"
                        row-key="id"
                    >
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'qty'">{{ fmt(record.quantity) }}</template>
                            <template v-else-if="column.key === 'unit'">{{ cur }} {{ fmt(record.unit_price) }}</template>
                            <template v-else-if="column.key === 'tax'">{{ record.tax_pct }}%</template>
                            <template v-else-if="column.key === 'total'">
                                <strong>{{ cur }} {{ fmt(record.line_total) }}</strong>
                            </template>
                        </template>
                        <template #emptyText>
                            <Empty :description="$t('invoices.no_items')" />
                        </template>
                    </Table>
                </Card>

                <Card style="margin-top: 16px" :bodyStyle="{ padding: '16px 24px' }">
                    <div class="totals">
                        <div class="total-row"><span>{{ $t('invoices.subtotal') }}</span><span>{{ cur }} {{ fmt(invoice.subtotal) }}</span></div>
                        <div class="total-row" v-if="Number(invoice.discount_total) > 0"><span>{{ $t('invoices.discount_total') }}</span><span>− {{ cur }} {{ fmt(invoice.discount_total) }}</span></div>
                        <div class="total-row"><span>{{ $t('invoices.tax_total') }}</span><span>{{ cur }} {{ fmt(invoice.tax_total) }}</span></div>
                        <div class="total-row" v-if="Number(invoice.shipping_cost) > 0"><span>{{ $t('invoices.shipping_cost') }}</span><span>{{ cur }} {{ fmt(invoice.shipping_cost) }}</span></div>
                        <div class="total-row grand"><span>{{ $t('invoices.grand_total') }}</span><span>{{ cur }} {{ fmt(invoice.grand_total) }}</span></div>
                        <div class="total-row paid"><span>{{ $t('invoices.amount_paid') }}</span><span>{{ cur }} {{ fmt(invoice.amount_paid) }}</span></div>
                        <div class="total-row balance"><span>{{ $t('invoices.balance_due') }}</span><span>{{ cur }} {{ fmt(invoice.balance_due) }}</span></div>
                    </div>
                </Card>

                <Card style="margin-top: 16px" :title="$t('invoices.payments_title')" :bodyStyle="{ padding: 0 }">
                    <Table
                        :columns="paymentColumns"
                        :data-source="invoice.payments || []"
                        :pagination="false"
                        size="middle"
                        row-key="id"
                    >
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'amount'"><strong>{{ cur }} {{ fmt(record.amount) }}</strong></template>
                            <template v-else-if="column.key === 'status'">
                                <Tag :color="record.status === 'completed' ? 'green' : 'default'" :bordered="false">{{ record.status }}</Tag>
                            </template>
                        </template>
                        <template #emptyText>
                            <Empty :description="$t('invoices.no_payments')" />
                        </template>
                    </Table>
                </Card>
            </Col>

            <Col :xs="24" :md="8">
                <Card>
                    <div class="payment-progress">
                        <Progress :percent="paidPct" :status="paidPct === 100 ? 'success' : 'active'" />
                        <div class="progress-label">{{ paidPct }}% cobrado</div>
                    </div>
                </Card>

                <Card style="margin-top: 16px" :title="$t('invoices.company')">
                    <Descriptions :column="1" size="small">
                        <Descriptions.Item :label="$t('invoices.company')">
                            <Link v-if="invoice.company" :href="route('crm.companies.show', invoice.company.slug || invoice.company_id)">
                                {{ invoice.company.name }}
                            </Link>
                            <span v-else>—</span>
                        </Descriptions.Item>
                        <Descriptions.Item v-if="invoice.billing_legal_name" :label="$t('invoices.billing_legal_name')">{{ invoice.billing_legal_name }}</Descriptions.Item>
                        <Descriptions.Item v-if="invoice.billing_tax_id" :label="$t('invoices.billing_tax_id')">{{ invoice.billing_tax_id }}</Descriptions.Item>
                        <Descriptions.Item v-if="invoice.document_type" :label="$t('invoices.document_type')">{{ invoice.document_type }}</Descriptions.Item>
                    </Descriptions>
                </Card>

                <Card style="margin-top: 16px">
                    <Descriptions :column="1" size="small">
                        <Descriptions.Item :label="$t('invoices.issue_date')">{{ formatDate(invoice.issue_date) }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('invoices.due_date')">{{ formatDate(invoice.due_date) }}</Descriptions.Item>
                        <Descriptions.Item v-if="invoice.sent_at" :label="$t('invoices.sent_at')">{{ formatDateTime(invoice.sent_at) }}</Descriptions.Item>
                        <Descriptions.Item v-if="invoice.paid_at" :label="$t('invoices.paid_at')">{{ formatDateTime(invoice.paid_at) }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('invoices.owner')">{{ invoice.owner?.name ?? '—' }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('invoices.currency')">{{ invoice.currency_code }}</Descriptions.Item>
                    </Descriptions>
                </Card>
            </Col>
        </Row>

        <RecordMetaFooter :record="invoice" />
    </div>
</template>

<style scoped>
.totals { display: flex; flex-direction: column; gap: 6px; max-width: 420px; margin-left: auto; }
.total-row { display: flex; justify-content: space-between; font-size: 0.9rem; }
.total-row.grand { font-size: 1.15rem; font-weight: 700; padding-top: 8px; margin-top: 4px; border-top: 1px solid var(--color-border, #e8e8e8); }
.total-row.paid { color: #389e0d; }
.total-row.balance { font-weight: 700; color: #d4380d; padding-top: 4px; }
.payment-progress { text-align: center; }
.progress-label { margin-top: 8px; color: var(--color-text-muted); font-size: 0.85rem; }
</style>
