<script setup>
import { computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Card, Form, FormItem, Input, InputNumber, Select, DatePicker, Row, Col, Alert } from 'ant-design-vue';
import { CreditCardOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
defineOptions({ layout: AppLayout });

const props = defineProps({
    payment:              { type: Object, default: null },
    preselectedInvoiceId: { type: Number, default: null },
    invoiceOptions:       { type: Array,  default: () => [] },
    paymentMethodOptions: { type: Array,  default: () => [] },
    typeOptions:          { type: Array,  default: () => [] },
    statusOptions:        { type: Array,  default: () => [] },
    companyOptions:       { type: Array,  default: () => [] },
    defaultCurrencyCode:  { type: String, default: null },
    nextReference:        { type: String, default: '' },
});

const isEdit = computed(() => !!props.payment);

const form = useForm({
    reference:        props.payment?.reference ?? props.nextReference ?? '',
    company_id:       props.payment?.company_id ?? null,
    invoice_id:       props.payment?.invoice_id ?? props.preselectedInvoiceId ?? null,
    type:             props.payment?.type ?? 'invoice_payment',
    payment_method_id: props.payment?.payment_method_id ?? null,
    amount:           props.payment?.amount ? Number(props.payment.amount) : null,
    currency_code:    props.payment?.currency_code ?? props.defaultCurrencyCode ?? 'USD',
    paid_at:          props.payment?.paid_at ?? new Date().toISOString().substring(0, 19),
    status:           props.payment?.status ?? 'completed',
    bank_reference:   props.payment?.bank_reference ?? '',
    external_transaction_id: props.payment?.external_transaction_id ?? '',
    notes:            props.payment?.notes ?? '',
});

const selectedInvoice = computed(() => props.invoiceOptions.find(i => i.value === form.invoice_id));
const selectedMethod  = computed(() => props.paymentMethodOptions.find(m => m.value === form.payment_method_id));
const balanceDue      = computed(() => selectedInvoice.value?.balance_due ?? 0);
const overpay         = computed(() => form.amount && balanceDue.value && form.amount > balanceDue.value);

watch(() => form.invoice_id, (newId) => {
    if (newId && selectedInvoice.value) {
        form.company_id = selectedInvoice.value.company_id;
        form.currency_code = selectedInvoice.value.currency_code;
        if (!form.amount || !isEdit.value) {
            form.amount = selectedInvoice.value.balance_due;
        }
    }
});

const submit = () => {
    if (isEdit.value) form.put(route('business_management.payments.update', props.payment.slug));
    else form.post(route('business_management.payments.store'));
};
</script>
<template>
    <Head :title="isEdit ? 'Editar pago' : 'Registrar pago'" />
    <SectionHeader :back-href="route('business_management.payments.index')"
        :title="isEdit ? 'Editar pago' : 'Registrar pago'" :subtitle="form.reference || 'Nuevo'">
        <template #icon><CreditCardOutlined /></template>
    </SectionHeader>
    <Card>
        <Form layout="vertical" @submit.prevent="submit">
            <Row :gutter="[16, 0]">
                <Col :xs="24" :md="12"><FormItem>
                    <template #label><LabelWithHelp :label="$t('payments.invoice')" :help="$t('payments.invoice_hint')" /></template>
                    <Select v-model:value="form.invoice_id" :options="invoiceOptions"
                        size="large" allow-clear show-search
                        :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())"
                        placeholder="Buscar factura por número o cliente" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem required :validate-status="form.errors.type ? 'error' : ''">
                    <template #label><LabelWithHelp :label="$t('payments.type')" :help="$t('payments.type_hint')" /></template>
                    <Select v-model:value="form.type" :options="typeOptions" size="large" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem required>
                    <template #label><LabelWithHelp :label="$t('payments.status')" :help="$t('payments.status_hint')" /></template>
                    <Select v-model:value="form.status" :options="statusOptions" size="large" />
                </FormItem></Col>

                <Col :xs="24" v-if="selectedInvoice">
                    <Alert type="info" show-icon style="margin-bottom: 16px"
                        :message="'Saldo pendiente: ' + selectedInvoice.currency_code + ' ' + Number(selectedInvoice.balance_due).toFixed(2)" />
                </Col>
                <Col :xs="24" v-if="overpay">
                    <Alert type="warning" show-icon style="margin-bottom: 16px"
                        message="El monto excede el saldo pendiente. Se aceptará pero quedará saldo a favor." />
                </Col>

                <Col :xs="12" :md="6"><FormItem required :validate-status="form.errors.amount ? 'error' : ''" :help="form.errors.amount">
                    <template #label><LabelWithHelp :label="$t('payments.amount')" :help="$t('payments.amount_hint')" /></template>
                    <InputNumber v-model:value="form.amount" :min="0.01" :step="0.01" size="large" style="width:100%" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('payments.currency')" :help="$t('payments.currency_hint')" /></template>
                    <Input v-model:value="form.currency_code" size="large" :maxlength="3" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem required :validate-status="form.errors.payment_method_id ? 'error' : ''" :help="form.errors.payment_method_id">
                    <template #label><LabelWithHelp :label="$t('payments.payment_method')" :help="$t('payments.payment_method_hint')" /></template>
                    <Select v-model:value="form.payment_method_id" :options="paymentMethodOptions" size="large" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem required>
                    <template #label><LabelWithHelp :label="$t('payments.paid_at')" :help="$t('payments.paid_at_hint')" /></template>
                    <DatePicker v-model:value="form.paid_at" valueFormat="YYYY-MM-DD HH:mm:ss" show-time size="large" style="width:100%" />
                </FormItem></Col>

                <Col :xs="24" :md="8"><FormItem>
                    <template #label><LabelWithHelp :label="$t('payments.reference')" :help="$t('payments.reference_hint')" /></template>
                    <Input v-model:value="form.reference" size="large" :maxlength="30" />
                </FormItem></Col>
                <Col :xs="24" :md="8"><FormItem>
                    <template #label><LabelWithHelp :label="selectedMethod?.requires_reference ? $t('payments.bank_reference') + ' (' + $t('payments.required_suffix') + ')' : $t('payments.bank_reference')" :help="$t('payments.bank_reference_hint')" /></template>
                    <Input v-model:value="form.bank_reference" size="large" :maxlength="100" placeholder="Nro transferencia / cheque" />
                </FormItem></Col>
                <Col :xs="24" :md="8"><FormItem>
                    <template #label><LabelWithHelp :label="$t('payments.external_transaction_id')" :help="$t('payments.external_transaction_id_hint')" /></template>
                    <Input v-model:value="form.external_transaction_id" size="large" :maxlength="100" placeholder="ID Stripe / MercadoPago" />
                </FormItem></Col>

                <Col :xs="24"><FormItem>
                    <template #label><LabelWithHelp :label="$t('payments.notes')" :help="$t('payments.notes_hint')" /></template>
                    <Input.TextArea v-model:value="form.notes" :rows="3" :maxlength="1000" />
                </FormItem></Col>
            </Row>
            <FormFooter :cancel-href="route('business_management.payments.index')" :is-edit="isEdit" :processing="form.processing" create-label-key="global.save_changes" />
        </Form>
    </Card>
</template>
