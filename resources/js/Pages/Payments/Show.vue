<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Card, Tag, Descriptions, Row, Col, Statistic } from 'ant-design-vue';
import { CreditCardOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDate, formatDateTime } = useDateFormat();

defineOptions({ layout: AppLayout });

const props = defineProps({
    payment: { type: Object, required: true },
});

const statusColor = {
    pending: 'gold', completed: 'green', failed: 'red',
    refunded: 'purple', disputed: 'orange',
};
</script>

<template>
    <Head :title="payment.reference + ' — ' + $t('payments.show_title')" />

    <div>
        <SectionHeader
            :back-href="route('business_management.payments.index')"
            :title="payment.reference || $t('payments.show_title')"
            :subtitle="payment.company?.name"
        >
            <template #icon><CreditCardOutlined /></template>
            <template #actions>
                <Tag :color="statusColor[payment.status] || 'default'" :bordered="false" style="font-size: 0.9rem; padding: 4px 12px">
                    {{ payment.status?.toUpperCase() }}
                </Tag>
            </template>
        </SectionHeader>

        <Row :gutter="[16, 16]">
            <Col :xs="24" :md="12">
                <Card>
                    <Statistic
                        :title="$t('payments.amount')"
                        :value="Number(payment.amount).toFixed(2)"
                        :prefix="payment.currency_code"
                        :precision="2"
                        :value-style="{ color: '#389e0d', fontSize: '1.8rem', fontWeight: 700 }"
                    />
                </Card>
            </Col>
            <Col :xs="24" :md="12">
                <Card v-if="payment.invoice">
                    <Descriptions :column="1" size="small" :title="$t('payments.invoice')">
                        <Descriptions.Item :label="$t('payments.invoice_number')">
                            <Link :href="route('business_management.invoices.show', payment.invoice.slug || payment.invoice_id)">
                                {{ payment.invoice.number }}
                            </Link>
                        </Descriptions.Item>
                        <Descriptions.Item label="Total factura">{{ payment.currency_code }} {{ Number(payment.invoice.grand_total).toFixed(2) }}</Descriptions.Item>
                        <Descriptions.Item label="Saldo restante">{{ payment.currency_code }} {{ Number(payment.invoice.balance_due).toFixed(2) }}</Descriptions.Item>
                    </Descriptions>
                </Card>
            </Col>

            <Col :xs="24">
                <Card>
                    <Descriptions :column="2" size="small">
                        <Descriptions.Item :label="$t('payments.payment_method')">{{ payment.paymentMethod?.name ?? '—' }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('payments.type')">{{ payment.type }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('payments.paid_at')">{{ formatDateTime(payment.paid_at) }}</Descriptions.Item>
                        <Descriptions.Item :label="$t('payments.reconciled_at')">{{ formatDateTime(payment.reconciled_at) }}</Descriptions.Item>
                        <Descriptions.Item v-if="payment.bank_reference" :label="$t('payments.bank_reference')">{{ payment.bank_reference }}</Descriptions.Item>
                        <Descriptions.Item v-if="payment.external_transaction_id" :label="$t('payments.external_transaction_id')">{{ payment.external_transaction_id }}</Descriptions.Item>
                        <Descriptions.Item v-if="payment.notes" :label="$t('payments.notes')" :span="2">{{ payment.notes }}</Descriptions.Item>
                    </Descriptions>
                </Card>
            </Col>
        </Row>
    </div>
</template>
