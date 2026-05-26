<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Card, Form, FormItem, Input, InputNumber, Select, DatePicker, Button, Row, Col, Tag, Space } from 'ant-design-vue';
import { InboxOutlined, PlusOutlined, DeleteOutlined, DragOutlined, ShoppingOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
defineOptions({ layout: AppLayout });

const props = defineProps({
    order:               { type: Object, default: null },
    supplierOptions:     { type: Array, default: () => [] },
    productOptions:      { type: Array, default: () => [] },
    warehouseOptions:    { type: Array, default: () => [] },
    ownerOptions:        { type: Array, default: () => [] },
    currencyOptions:     { type: Array, default: () => [] },
    statusOptions:       { type: Array, default: () => [] },
    defaultCurrencyCode: { type: String, default: null },
    defaultWarehouseId:  { type: Number, default: null },
    nextReference:       { type: String, default: '' },
});

const isEdit = computed(() => !!props.order);
const today = new Date().toISOString().substring(0, 10);
const in14  = new Date(Date.now() + 14 * 86400000).toISOString().substring(0, 10);

function emptyLine() { return { product_id: null, name: '', description: '', quantity: 1, unit_price: 0, discount_pct: 0, tax_pct: 18 }; }

const form = useForm({
    reference:              props.order?.reference ?? props.nextReference ?? '',
    supplier_company_id:    props.order?.supplier_company_id ?? null,
    owner_id:               props.order?.owner_id ?? null,
    warehouse_id:           props.order?.warehouse_id ?? props.defaultWarehouseId ?? null,
    status:                 props.order?.status ?? 'draft',
    order_date:             props.order?.order_date ?? today,
    expected_delivery_date: props.order?.expected_delivery_date ?? in14,
    currency_code:          props.order?.currency_code ?? props.defaultCurrencyCode ?? 'USD',
    payment_terms_days:     props.order?.payment_terms_days ?? 30,
    delivery_type:          props.order?.delivery_type ?? '',
    notes:                  props.order?.notes ?? '',
    items:                  props.order?.items?.length
        ? props.order.items.map(i => ({
            product_id: i.product_id ?? null, name: i.name, description: i.description ?? '',
            quantity: Number(i.quantity ?? i.quantity_ordered ?? 1),
            unit_price: Number(i.unit_price ?? i.unit_cost),
            discount_pct: Number(i.discount_pct ?? 0), tax_pct: Number(i.tax_pct ?? 0),
        }))
        : [emptyLine()],
});

const addLine = () => form.items.push(emptyLine());
const removeLine = (idx) => { if (form.items.length > 1) form.items.splice(idx, 1); };
const moveUp = (idx) => { if (idx > 0) { const a = form.items[idx-1]; form.items[idx-1] = form.items[idx]; form.items[idx] = a; }};
const onProductSelect = (idx, productId) => {
    const p = props.productOptions.find(o => o.value === productId);
    if (p) { form.items[idx].name = p.name; form.items[idx].unit_price = p.price ?? 0; }
};
const lineSubtotal = (i) => +(i.quantity * i.unit_price * (1 - (i.discount_pct || 0) / 100)).toFixed(2);
const lineTax      = (i) => +(lineSubtotal(i) * (i.tax_pct || 0) / 100).toFixed(2);
const lineTotal    = (i) => +(lineSubtotal(i) + lineTax(i)).toFixed(2);
const subtotal   = computed(() => form.items.reduce((s, i) => s + lineSubtotal(i), 0));
const taxTotal   = computed(() => form.items.reduce((s, i) => s + lineTax(i), 0));
const grandTotal = computed(() => subtotal.value + taxTotal.value);
const fmt = (n) => new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n));

const submit = () => isEdit.value
    ? form.put(route('business_management.purchase_orders.update', props.order.slug))
    : form.post(route('business_management.purchase_orders.store'));
</script>

<template>
    <Head :title="isEdit ? 'Editar OC' : 'Nueva OC'" />
    <div class="po-form">
        <SectionHeader :back-href="route('business_management.purchase_orders.index')"
            :title="isEdit ? 'Editar orden de compra' : 'Nueva orden de compra'" :subtitle="form.reference || 'Borrador'">
            <template #icon><InboxOutlined /></template>
        </SectionHeader>
        <Form layout="vertical" @submit.prevent="submit">
            <Row :gutter="[16, 16]">
                <Col :xs="24" :md="16">
                    <Card title="Datos del documento">
                        <Row :gutter="[16, 0]">
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('purchase_orders.reference')" :help="$t('purchase_orders.reference_hint')" /></template>
                                <Input v-model:value="form.reference" size="large" :maxlength="30" />
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem required>
                                <template #label><LabelWithHelp :label="$t('purchase_orders.status')" :help="$t('purchase_orders.status_hint')" /></template>
                                <Select v-model:value="form.status" :options="statusOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('purchase_orders.currency')" :help="$t('purchase_orders.currency_hint')" /></template>
                                <Select v-model:value="form.currency_code" :options="currencyOptions" size="large" show-search :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>

                            <Col :xs="24" :md="14"><FormItem required :validate-status="form.errors.supplier_company_id ? 'error' : ''" :help="form.errors.supplier_company_id">
                                <template #label><LabelWithHelp :label="$t('purchase_orders.supplier')" :help="$t('purchase_orders.supplier_hint')" /></template>
                                <Select v-model:value="form.supplier_company_id" :options="supplierOptions" size="large" show-search :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" placeholder="Empresa proveedora (type=supplier/both/partner)" />
                            </FormItem></Col>
                            <Col :xs="24" :md="10"><FormItem required :validate-status="form.errors.warehouse_id ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('purchase_orders.warehouse')" :help="$t('purchase_orders.warehouse_hint')" /></template>
                                <Select v-model:value="form.warehouse_id" :options="warehouseOptions" size="large" show-search :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>

                            <Col :xs="12" :md="6"><FormItem required>
                                <template #label><LabelWithHelp :label="$t('purchase_orders.order_date')" :help="$t('purchase_orders.order_date_hint')" /></template>
                                <DatePicker v-model:value="form.order_date" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('purchase_orders.expected_delivery_date')" :help="$t('purchase_orders.expected_delivery_date_hint')" /></template>
                                <DatePicker v-model:value="form.expected_delivery_date" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('purchase_orders.payment_terms_days')" :help="$t('purchase_orders.payment_terms_days_hint')" /></template>
                                <InputNumber v-model:value="form.payment_terms_days" :min="0" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('purchase_orders.delivery_type')" :help="$t('purchase_orders.delivery_type_hint')" /></template>
                                <Select v-model:value="form.delivery_type" :options="[{value:'pickup',label:'Pickup'},{value:'courier',label:'Courier'},{value:'freight',label:'Flete'}]" size="large" allow-clear />
                            </FormItem></Col>
                            <Col :xs="24"><FormItem>
                                <template #label><LabelWithHelp :label="$t('purchase_orders.owner')" :help="$t('purchase_orders.owner_hint')" /></template>
                                <Select v-model:value="form.owner_id" :options="ownerOptions" size="large" allow-clear show-search :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>
                        </Row>
                    </Card>
                </Col>
                <Col :xs="24" :md="8">
                    <Card title="Totales">
                        <div class="totals">
                            <div class="row"><span>Subtotal</span><strong>{{ form.currency_code }} {{ fmt(subtotal) }}</strong></div>
                            <div class="row"><span>Impuestos</span><strong>{{ form.currency_code }} {{ fmt(taxTotal) }}</strong></div>
                            <div class="row grand"><span>Total</span><strong>{{ form.currency_code }} {{ fmt(grandTotal) }}</strong></div>
                        </div>
                    </Card>
                </Col>

                <Col :xs="24">
                    <Card>
                        <template #title><Space><ShoppingOutlined /> Líneas <Tag :bordered="false">{{ form.items.length }}</Tag></Space></template>
                        <template #extra><Button @click="addLine"><PlusOutlined /> Agregar línea</Button></template>
                        <div v-if="form.errors.items" class="error-banner">{{ form.errors.items }}</div>
                        <div class="items-table">
                            <div class="items-header"><div></div><div>Producto / descripción</div><div>Cant.</div><div>Costo unit.</div><div>% Desc</div><div>% Imp</div><div style="text-align:right">Total</div><div></div></div>
                            <div v-for="(item, idx) in form.items" :key="idx" class="items-row">
                                <div class="col-handle"><DragOutlined @click="moveUp(idx)" style="cursor:pointer" /></div>
                                <div class="col-product">
                                    <Select :value="item.product_id" :options="productOptions" placeholder="Buscar producto…" show-search allow-clear style="width:100%; margin-bottom:6px"
                                        @change="v => { item.product_id = v; onProductSelect(idx, v); }"
                                        :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                                    <Input v-model:value="item.name" :maxlength="200" />
                                    <Input v-model:value="item.description" :maxlength="500" placeholder="Descripción (opcional)" style="margin-top:4px" />
                                </div>
                                <div><InputNumber v-model:value="item.quantity" :min="0.0001" style="width:100%" /></div>
                                <div><InputNumber v-model:value="item.unit_price" :min="0" :step="0.01" style="width:100%" /></div>
                                <div><InputNumber v-model:value="item.discount_pct" :min="0" :max="100" style="width:100%" /></div>
                                <div><InputNumber v-model:value="item.tax_pct" :min="0" :max="100" style="width:100%" /></div>
                                <div class="col-total"><strong>{{ fmt(lineTotal(item)) }}</strong></div>
                                <div class="col-actions"><DeleteOutlined @click="removeLine(idx)" style="color:#d4380d; cursor:pointer; font-size:1.1rem" :class="{ disabled: form.items.length === 1 }" /></div>
                            </div>
                        </div>
                    </Card>
                </Col>

                <Col :xs="24"><Card title="Notas / términos"><Input.TextArea v-model:value="form.notes" :rows="4" :maxlength="2000" show-count /></Card></Col>
            </Row>
            <FormFooter :cancel-href="route('business_management.purchase_orders.index')" :is-edit="isEdit" :processing="form.processing" create-label-key="global.save_changes" />
        </Form>
    </div>
</template>

<style scoped>
.po-form > * + * { margin-top: 16px; }
.totals { display: flex; flex-direction: column; gap: 10px; }
.totals .row { display: flex; justify-content: space-between; font-size: 0.9rem; }
.totals .row.grand { font-size: 1.2rem; padding-top: 10px; border-top: 1px solid var(--color-border); margin-top: 4px; }
.totals .row.grand strong { color: var(--color-primary, #1677ff); }
.error-banner { color: #d4380d; padding: 8px 12px; background: #fff2f0; border: 1px solid #ffccc7; border-radius: 4px; margin-bottom: 12px; }
.items-table { display: flex; flex-direction: column; gap: 8px; }
.items-header, .items-row { display: grid; grid-template-columns: 30px 1fr 90px 120px 80px 80px 130px 40px; gap: 8px; align-items: start; }
.items-header { font-weight: 600; font-size: 0.78rem; color: var(--color-text-muted); padding: 6px 0; border-bottom: 2px solid var(--color-border); }
.items-row { padding: 8px 0; border-bottom: 1px dashed var(--color-border-soft, #f0f0f0); }
.col-handle, .col-actions { display: flex; align-items: center; justify-content: center; padding-top: 8px; }
.col-total { text-align: right; padding-top: 6px; font-size: 1rem; }
.disabled { opacity: 0.3; pointer-events: none; }
@media (max-width: 768px) {
    .items-header { display: none; }
    .items-row { grid-template-columns: 1fr 1fr; padding: 12px; border: 1px solid var(--color-border-soft); border-radius: 6px; }
    .col-handle, .col-actions, .col-product { grid-column: 1 / -1; }
    .col-total { grid-column: 2; }
}
</style>
