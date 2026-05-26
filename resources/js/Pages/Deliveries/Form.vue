<script setup>
import { computed, ref, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Card, Form, FormItem, Input, InputNumber, Select, DatePicker, Row, Col, Table, Alert } from 'ant-design-vue';
import { CarOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const { t } = useI18n();

const props = defineProps({
    delivery:                { type: Object, default: null },
    preselectedSalesOrderId: { type: Number, default: null },
    salesOrderOptions:       { type: Array, default: () => [] },
    warehouseOptions:        { type: Array, default: () => [] },
    statusOptions:           { type: Array, default: () => [] },
    nextReference:           { type: String, default: '' },
});

const isEdit = computed(() => !!props.delivery);
const lines = ref([]);
const loading = ref(false);

const form = useForm({
    reference:              props.delivery?.reference ?? props.nextReference ?? '',
    sales_order_id:         props.delivery?.sales_order_id ?? props.preselectedSalesOrderId ?? null,
    warehouse_id:           props.delivery?.warehouse_id ?? null,
    status:                 props.delivery?.status ?? 'pending',
    expected_delivery_date: props.delivery?.expected_delivery_date ?? null,
    shipped_at:             props.delivery?.shipped_at ?? null,
    delivered_at:           props.delivery?.delivered_at ?? null,
    signed_by_name:         props.delivery?.signed_by_name ?? '',
    carrier:                props.delivery?.carrier ?? '',
    tracking_number:        props.delivery?.tracking_number ?? '',
    shipping_method:        props.delivery?.shipping_method ?? '',
    shipping_cost:          props.delivery?.shipping_cost ?? 0,
    notes:                  props.delivery?.notes ?? '',
    items: [],
});

const loadLines = async (soId) => {
    if (!soId) { lines.value = []; return; }
    loading.value = true;
    try {
        const res = await fetch(route('business_management.deliveries.so_lines', soId), { credentials: 'include' });
        const data = await res.json();
        const existingItems = isEdit.value ? (props.delivery.items || []) : [];
        const existingMap = Object.fromEntries(existingItems.map(i => [i.sales_order_item_id, Number(i.quantity)]));
        lines.value = data.map(l => ({
            ...l,
            qty_to_deliver: existingMap[l.sales_order_item_id] ?? l.quantity_pending,
        }));
    } finally { loading.value = false; }
};

watch(() => form.sales_order_id, (id) => {
    const so = props.salesOrderOptions.find(o => o.value === id);
    if (so && !isEdit.value) form.warehouse_id = so.warehouse_id;
    loadLines(id);
}, { immediate: true });

const submit = () => {
    form.items = lines.value.filter(l => Number(l.qty_to_deliver) > 0).map(l => ({
        sales_order_item_id: l.sales_order_item_id,
        product_id: l.product_id,
        quantity: Number(l.qty_to_deliver),
    }));
    if (isEdit.value) form.put(route('business_management.deliveries.update', props.delivery.slug));
    else form.post(route('business_management.deliveries.store'));
};

const lineColumns = computed(() => [
    { title: t('deliveries.items_title'),         dataIndex: 'name',               key: 'name' },
    { title: 'SKU',                                dataIndex: 'sku',               key: 'sku', width: 130 },
    { title: t('deliveries.item_qty_ordered'),    dataIndex: 'quantity_ordered',   key: 'ord', align: 'right', width: 90 },
    { title: t('deliveries.item_qty_fulfilled'),  dataIndex: 'quantity_fulfilled', key: 'ful', align: 'right', width: 110 },
    { title: t('deliveries.item_qty_pending'),    dataIndex: 'quantity_pending',   key: 'pen', align: 'right', width: 100 },
    { title: t('deliveries.item_qty_now'),        dataIndex: 'qty_to_deliver',     key: 'now', align: 'right', width: 160 },
]);
</script>

<template>
    <Head :title="isEdit ? $t('deliveries.edit_title') : $t('deliveries.create_title')" />

    <SectionHeader
        :back-href="route('business_management.deliveries.index')"
        :title="isEdit ? $t('deliveries.edit_title') : $t('deliveries.create_title')"
        :subtitle="form.reference || $t('deliveries.new')"
    >
        <template #icon><CarOutlined /></template>
    </SectionHeader>

    <Card>
        <Form layout="vertical" @submit.prevent="submit">
            <Row :gutter="[16, 0]">
                <Col :xs="12" :md="6"><FormItem :validate-status="form.errors.reference ? 'error' : ''" :help="form.errors.reference">
                    <template #label><LabelWithHelp :label="$t('deliveries.reference')" :help="$t('deliveries.reference_hint')" /></template>
                    <Input v-model:value="form.reference" size="large" :maxlength="30" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem required :validate-status="form.errors.status ? 'error' : ''" :help="form.errors.status">
                    <template #label><LabelWithHelp :label="$t('deliveries.status')" :help="$t('deliveries.status_hint')" /></template>
                    <Select v-model:value="form.status" :options="statusOptions" size="large" />
                </FormItem></Col>
                <Col :xs="24" :md="12"><FormItem required :validate-status="form.errors.sales_order_id ? 'error' : ''" :help="form.errors.sales_order_id">
                    <template #label><LabelWithHelp :label="$t('deliveries.sales_order')" :help="$t('deliveries.sales_order_hint')" /></template>
                    <Select
                        v-model:value="form.sales_order_id"
                        :options="salesOrderOptions"
                        size="large"
                        show-search
                        :filter-option="(i, o) => (o.label ?? '').toLowerCase().includes(i.toLowerCase())"
                        :disabled="isEdit"
                    />
                </FormItem></Col>

                <Col :xs="12" :md="6"><FormItem required :validate-status="form.errors.warehouse_id ? 'error' : ''" :help="form.errors.warehouse_id">
                    <template #label><LabelWithHelp :label="$t('deliveries.warehouse')" :help="$t('deliveries.warehouse_hint')" /></template>
                    <Select v-model:value="form.warehouse_id" :options="warehouseOptions" size="large" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.expected_delivery_date')" :help="$t('deliveries.expected_delivery_date_hint')" /></template>
                    <DatePicker v-model:value="form.expected_delivery_date" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.shipped_at')" :help="$t('deliveries.shipped_at_hint')" /></template>
                    <DatePicker v-model:value="form.shipped_at" valueFormat="YYYY-MM-DD HH:mm:ss" show-time size="large" style="width:100%" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.delivered_at')" :help="$t('deliveries.delivered_at_hint')" /></template>
                    <DatePicker v-model:value="form.delivered_at" valueFormat="YYYY-MM-DD HH:mm:ss" show-time size="large" style="width:100%" />
                </FormItem></Col>

                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.carrier')" :help="$t('deliveries.carrier_hint')" /></template>
                    <Input v-model:value="form.carrier" size="large" :maxlength="100" placeholder="DHL / FedEx" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.tracking_number')" :help="$t('deliveries.tracking_number_hint')" /></template>
                    <Input v-model:value="form.tracking_number" size="large" :maxlength="80" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.shipping_method')" :help="$t('deliveries.shipping_method_hint')" /></template>
                    <Input v-model:value="form.shipping_method" size="large" :maxlength="60" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.shipping_cost')" :help="$t('deliveries.shipping_cost_hint')" /></template>
                    <InputNumber v-model:value="form.shipping_cost" :min="0" :step="0.01" size="large" style="width:100%" />
                </FormItem></Col>

                <Col :xs="24" :md="12"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.signed_by_name')" :help="$t('deliveries.signed_by_name_hint')" /></template>
                    <Input v-model:value="form.signed_by_name" size="large" :maxlength="200" />
                </FormItem></Col>
                <Col :xs="24" :md="12"><FormItem>
                    <template #label><LabelWithHelp :label="$t('deliveries.notes')" :help="$t('deliveries.notes_hint')" /></template>
                    <Input.TextArea v-model:value="form.notes" :rows="2" :maxlength="1000" />
                </FormItem></Col>
            </Row>

            <Card :title="$t('deliveries.items_title')" :bodyStyle="{padding: 0}" style="margin-top: 16px">
                <Alert v-if="lines.length === 0 && form.sales_order_id" type="info" show-icon style="margin: 16px" :message="$t('deliveries.no_pending_lines')" />
                <Alert v-if="!form.sales_order_id" type="info" show-icon style="margin: 16px" :message="$t('deliveries.select_sales_order_first')" />
                <Alert v-if="form.errors.items" type="error" show-icon style="margin: 16px" :message="form.errors.items" />
                <Table v-if="lines.length > 0" :columns="lineColumns" :data-source="lines" :pagination="false" :loading="loading" size="middle" row-key="sales_order_item_id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'now'">
                            <InputNumber v-model:value="record.qty_to_deliver" :min="0" :max="record.quantity_pending + (isEdit ? 1000 : 0)" :step="1" style="width:100%" />
                        </template>
                    </template>
                </Table>
            </Card>

            <FormFooter
                :cancel-href="route('business_management.deliveries.index')"
                :is-edit="isEdit"
                :processing="form.processing"
                create-label-key="global.save_changes"
            />
        </Form>
    </Card>
</template>
