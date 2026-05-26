<script setup>
import { computed } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, Tag, Table, Descriptions, Row, Col, Space, Empty, Button, Popconfirm, Modal, Input, Form, FormItem, message } from 'ant-design-vue';
import { ref } from 'vue';
import {
    FileDoneOutlined, EditOutlined, SendOutlined, CheckOutlined, CloseOutlined,
    FileTextOutlined, ShoppingCartOutlined, DeleteOutlined, MailOutlined,
    DollarOutlined, ShoppingOutlined, CalendarOutlined, PercentageOutlined,
    FilePdfOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import KPITiles from '@/Components/Common/KPITiles.vue';
import DocumentFlow from '@/Components/Common/DocumentFlow.vue';
import ActivityFormModal from '@/Components/Crm/Activities/ActivityFormModal.vue';
import RecordMetaFooter from '@/Components/Common/RecordMetaFooter.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

const { formatDate, formatDateTime } = useDateFormat();
const { t: $t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    quote: { type: Object, required: true },
});

const statusColor = {
    draft: 'default', sent: 'blue', accepted: 'green',
    rejected: 'red', expired: 'orange', revised: 'purple',
};

// Workflow actions
const rejectOpen = ref(false);
const rejectReason = ref('');
const canSend   = computed(() => props.quote.status === 'draft');
const canAccept = computed(() => ['draft', 'sent'].includes(props.quote.status));
const canReject = computed(() => ['draft', 'sent'].includes(props.quote.status));
const canConvert = computed(() => props.quote.status === 'accepted');

const onSend   = () => router.post(route('business_management.quotes.send', props.quote.slug));
const onAccept = () => router.post(route('business_management.quotes.accept', props.quote.slug));
const onReject = () => { router.post(route('business_management.quotes.reject', props.quote.slug), { rejected_reason: rejectReason.value }); rejectOpen.value = false; };
const onToInvoice = () => router.post(route('business_management.quotes.to_invoice', props.quote.slug));
const onToSalesOrder = () => router.post(route('business_management.quotes.to_sales_order', props.quote.slug));
const onDelete = () => router.delete(route('business_management.quotes.destroy', props.quote.slug));

// "Registrar envio" — abre modal de Activity tipo email pre-cargado con
// el deal padre como activitable y este quote como related_quote.
const activityModalOpen = ref(false);
const activityActivitable = computed(() => {
    if (!props.quote.deal_id) return null;
    return { type: 'App\\Models\\Deal', id: props.quote.deal_id };
});
const quotesForModal = computed(() => [{
    id:        props.quote.id,
    slug:      props.quote.slug,
    reference: props.quote.reference,
    name:      props.quote.reference,
    status:    props.quote.status,
    total:     props.quote.grand_total,
    currency:  props.quote.currency_code,
}]);
const canLogSend = computed(() => !!props.quote.deal_id);

const fmt = (n) => {
    if (n == null) return '—';
    const v = Number(n);
    if (Number.isNaN(v)) return '—';
    return new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
};

const cur = computed(() => props.quote.currency_code || '');

const kpiTiles = computed(() => {
    const code = props.quote.currency_code || '';
    const fmtMoney = (n) => code + ' ' + fmt(n);
    return [
        { icon: DollarOutlined,     label: $t('quotes.grand_total'),    value: fmtMoney(props.quote.grand_total),                                color: 'primary' },
        { icon: ShoppingOutlined,   label: $t('quotes.items_title'),    value: (props.quote.items?.length ?? 0).toString() },
        { icon: PercentageOutlined, label: $t('quotes.tax_total'),      value: fmtMoney(props.quote.tax_total) },
        { icon: CalendarOutlined,   label: $t('quotes.valid_until'),    value: props.quote.valid_until ? formatDate(props.quote.valid_until) : '—' },
    ];
});

const flowSteps = computed(() => {
    const s = props.quote.status;
    const rejected  = s === 'rejected';
    const expired   = s === 'expired';
    const revised   = s === 'revised';
    if (rejected || expired || revised) {
        return [
            { value: 'draft',    label: $t('quotes.status_options.draft') },
            { value: 'sent',     label: $t('quotes.status_options.sent') },
            { value: s,          label: $t(`quotes.status_options.${s}`), isError: rejected || expired, isTerminal: revised },
        ];
    }
    return [
        { value: 'draft',    label: $t('quotes.status_options.draft') },
        { value: 'sent',     label: $t('quotes.status_options.sent') },
        { value: 'accepted', label: $t('quotes.status_options.accepted'), isTerminal: true },
    ];
});

const itemColumns = [
    { title: '#', dataIndex: 'sort_order', key: 'idx', width: 50, customRender: ({ index }) => index + 1 },
    { title: 'Producto', dataIndex: 'name', key: 'name' },
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 110 },
    { title: 'Cant.', dataIndex: 'quantity', key: 'qty', align: 'right', width: 80 },
    { title: 'Precio unit.', dataIndex: 'unit_price', key: 'unit', align: 'right', width: 120 },
    { title: '% Desc.', dataIndex: 'discount_pct', key: 'disc', align: 'right', width: 80 },
    { title: '% Imp.', dataIndex: 'tax_pct', key: 'tax', align: 'right', width: 80 },
    { title: 'Total', dataIndex: 'line_total', key: 'total', align: 'right', width: 130 },
];
</script>

<template>
    <Head :title="quote.reference + ' — ' + $t('quotes.show_title')" />

    <div class="quote-show">
        <SectionHeader
            :back-href="route('business_management.quotes.index')"
            :title="quote.reference || $t('quotes.show_title')"
            :subtitle="quote.company?.name"
        >
            <template #icon><FileDoneOutlined /></template>
            <template #actions>
                <Space wrap>
                    <Tag :color="statusColor[quote.status] || 'default'" :bordered="false" style="font-size: 0.9rem; padding: 4px 12px">
                        {{ quote.status?.toUpperCase() }}
                    </Tag>
                    <Link :href="route('business_management.quotes.edit', quote.slug)">
                        <Button><EditOutlined /> Editar</Button>
                    </Link>
                    <a :href="route('business_management.quotes.show_pdf', quote.slug)" target="_blank" rel="noopener">
                        <Button><FilePdfOutlined /> PDF</Button>
                    </a>
                    <Button v-if="canLogSend" type="default" @click="activityModalOpen = true">
                        <MailOutlined /> {{ $t('activities.log_send_button') }}
                    </Button>
                    <Popconfirm v-if="canSend" title="¿Marcar como enviada?" @confirm="onSend">
                        <Button type="primary"><SendOutlined /> Enviar</Button>
                    </Popconfirm>
                    <Popconfirm v-if="canAccept" title="¿Marcar como aceptada por el cliente?" @confirm="onAccept">
                        <Button type="primary" style="background:#52c41a; border-color:#52c41a"><CheckOutlined /> Aceptar</Button>
                    </Popconfirm>
                    <Button v-if="canReject" danger @click="rejectOpen = true"><CloseOutlined /> Rechazar</Button>
                    <Popconfirm v-if="canConvert" title="¿Generar factura desde esta cotización?" @confirm="onToInvoice">
                        <Button type="primary"><FileTextOutlined /> → Factura</Button>
                    </Popconfirm>
                    <Popconfirm v-if="canConvert" title="¿Generar orden de venta?" @confirm="onToSalesOrder">
                        <Button><ShoppingCartOutlined /> → Orden venta</Button>
                    </Popconfirm>
                    <Popconfirm title="¿Eliminar esta cotización?" @confirm="onDelete">
                        <Button danger><DeleteOutlined /></Button>
                    </Popconfirm>
                </Space>
            </template>
        </SectionHeader>

        <Modal v-model:open="rejectOpen" title="Motivo del rechazo" @ok="onReject" ok-text="Rechazar" cancel-text="Cancelar" ok-button-props="{ danger: true }">
            <p class="muted">Indica por qué el cliente no aceptó la cotización (queda registrado).</p>
            <Input.TextArea v-model:value="rejectReason" :rows="3" :maxlength="500" placeholder="Ej: precio fuera de presupuesto / postergaron decisión / eligieron competidor X" />
        </Modal>

        <KPITiles :tiles="kpiTiles" />
        <DocumentFlow :current-status="quote.status" :steps="flowSteps" :title="$t('quotes.show_title')" />

        <Row :gutter="[16, 16]">
            <Col :xs="24" :md="16">
                <Card :title="$t('quotes.items_title')" :bodyStyle="{ padding: 0 }">
                    <Table
                        :columns="itemColumns"
                        :data-source="quote.items || []"
                        :pagination="false"
                        size="middle"
                        row-key="id"
                    >
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'qty'">{{ fmt(record.quantity) }}</template>
                            <template v-else-if="column.key === 'unit'">{{ cur }} {{ fmt(record.unit_price) }}</template>
                            <template v-else-if="column.key === 'disc'">{{ record.discount_pct }}%</template>
                            <template v-else-if="column.key === 'tax'">{{ record.tax_pct }}%</template>
                            <template v-else-if="column.key === 'total'">
                                <strong>{{ cur }} {{ fmt(record.line_total) }}</strong>
                            </template>
                        </template>
                        <template #emptyText>
                            <Empty :description="$t('quotes.no_items')" />
                        </template>
                    </Table>
                </Card>

                <Card style="margin-top: 16px" :bodyStyle="{ padding: '16px 24px' }">
                    <div class="totals">
                        <div class="total-row"><span>{{ $t('quotes.subtotal') }}</span><span>{{ cur }} {{ fmt(quote.subtotal) }}</span></div>
                        <div class="total-row" v-if="Number(quote.discount_total) > 0"><span>{{ $t('quotes.discount_total') }}</span><span>− {{ cur }} {{ fmt(quote.discount_total) }}</span></div>
                        <div class="total-row"><span>{{ $t('quotes.tax_total') }}</span><span>{{ cur }} {{ fmt(quote.tax_total) }}</span></div>
                        <div class="total-row" v-if="Number(quote.shipping_cost) > 0"><span>{{ $t('quotes.shipping_cost') }}</span><span>{{ cur }} {{ fmt(quote.shipping_cost) }}</span></div>
                        <div class="total-row grand"><span>{{ $t('quotes.grand_total') }}</span><span>{{ cur }} {{ fmt(quote.grand_total) }}</span></div>
                    </div>
                </Card>

                <Card v-if="quote.notes" style="margin-top: 16px" :title="$t('quotes.notes')">
                    {{ quote.notes }}
                </Card>
            </Col>

            <Col :xs="24" :md="8">
                <Card :title="$t('quotes.company')">
                    <Descriptions :column="1" size="small">
                        <Descriptions.Item :label="$t('quotes.company')">
                            <Link v-if="quote.company" :href="route('crm.companies.show', quote.company.slug || quote.company_id)">
                                {{ quote.company.name }}
                            </Link>
                            <span v-else>—</span>
                        </Descriptions.Item>
                        <Descriptions.Item v-if="quote.company?.legal_name" label="Razón social">{{ quote.company.legal_name }}</Descriptions.Item>
                        <Descriptions.Item v-if="quote.company?.tax_id" label="Tax ID">{{ quote.company.tax_id }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('quotes.contact')">
                            <span v-if="quote.contact">{{ quote.contact.name }} <small v-if="quote.contact.job_title">— {{ quote.contact.job_title }}</small></span>
                            <span v-else>—</span>
                        </Descriptions.Item>
                        <Descriptions.Item v-if="quote.contact?.primary_email" label="Email">{{ quote.contact.primary_email }}</Descriptions.Item>
                    </Descriptions>
                </Card>

                <Card style="margin-top: 16px">
                    <Descriptions :column="1" size="small">
                        <Descriptions.Item :label="$t('quotes.issue_date')">{{ formatDate(quote.issue_date) }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('quotes.valid_until')">{{ formatDate(quote.valid_until) }}</Descriptions.Item>
                        <Descriptions.Item v-if="quote.sent_at" :label="$t('quotes.sent_at')">{{ formatDateTime(quote.sent_at) }}</Descriptions.Item>
                        <Descriptions.Item v-if="quote.accepted_at" :label="$t('quotes.accepted_at')">{{ formatDateTime(quote.accepted_at) }}</Descriptions.Item>
                        <Descriptions.Item v-if="quote.rejected_at" :label="$t('quotes.rejected_at')">{{ formatDateTime(quote.rejected_at) }}</Descriptions.Item>
                        <Descriptions.Item v-if="quote.rejected_reason" :label="$t('quotes.rejected_reason')">{{ quote.rejected_reason }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('quotes.owner')">{{ quote.owner?.name ?? '—' }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('quotes.currency')">{{ quote.currency_code }}</Descriptions.Item>
                    </Descriptions>
                </Card>
            </Col>
        </Row>

        <ActivityFormModal v-if="activityActivitable"
            v-model:open="activityModalOpen"
            :activitable="activityActivitable"
            :quotes="quotesForModal"
            :initial-quote-id="quote.id"
        />

        <RecordMetaFooter :record="quote" />
    </div>
</template>

<style scoped>
.totals { display: flex; flex-direction: column; gap: 6px; max-width: 420px; margin-left: auto; }
.total-row { display: flex; justify-content: space-between; font-size: 0.9rem; }
.total-row.grand { font-size: 1.15rem; font-weight: 700; padding-top: 8px; margin-top: 4px; border-top: 1px solid var(--color-border, #e8e8e8); }
</style>
