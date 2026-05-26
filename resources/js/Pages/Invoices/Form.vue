<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Select, DatePicker, Button,
    Row, Col, Tag, Empty, Tooltip, Space,
} from 'ant-design-vue';
import { FileTextOutlined, PlusOutlined, DeleteOutlined, DragOutlined, ShoppingOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
defineOptions({ layout: AppLayout });

const props = defineProps({
    invoice:             { type: Object, default: null },
    companyOptions:      { type: Array, default: () => [] },
    contactOptions:      { type: Array, default: () => [] },
    productOptions:      { type: Array, default: () => [] },
    ownerOptions:        { type: Array, default: () => [] },
    currencyOptions:     { type: Array, default: () => [] },
    statusOptions:       { type: Array, default: () => [] },
    defaultCurrencyCode: { type: String, default: null },
    nextNumber:          { type: String, default: '' },
});

const isEdit = computed(() => !!props.invoice);
const today = new Date().toISOString().substring(0, 10);
const in30  = new Date(Date.now() + 30 * 86400000).toISOString().substring(0, 10);

function emptyLine() { return { product_id: null, name: '', description: '', sku: '', quantity: 1, unit_price: 0, discount_pct: 0, tax_pct: 18 }; }

const form = useForm({
    number:             props.invoice?.number ?? props.nextNumber ?? '',
    reference:          props.invoice?.reference ?? '',
    document_type:      props.invoice?.document_type ?? '',
    // Pre-fill desde ?company_id=X cuando se entra al form desde el boton
    // "Nueva Factura" del tab Facturas de una Company Show.
    company_id:         props.invoice?.company_id ?? (Number(new URLSearchParams(window.location.search).get('company_id')) || null),
    contact_id:         props.invoice?.contact_id ?? null,
    owner_id:           props.invoice?.owner_id ?? null,
    status:             props.invoice?.status ?? 'draft',
    issue_date:         props.invoice?.issue_date ?? today,
    due_date:           props.invoice?.due_date ?? in30,
    currency_code:      props.invoice?.currency_code ?? props.defaultCurrencyCode ?? 'USD',
    billing_legal_name: props.invoice?.billing_legal_name ?? '',
    billing_tax_id:     props.invoice?.billing_tax_id ?? '',
    notes:              props.invoice?.notes ?? '',
    internal_notes:     props.invoice?.internal_notes ?? '',
    items:              props.invoice?.items?.length
        ? props.invoice.items.map(i => ({
            product_id: i.product_id ?? null, name: i.name, description: i.description ?? '', sku: i.sku ?? '',
            quantity: Number(i.quantity), unit_price: Number(i.unit_price),
            discount_pct: Number(i.discount_pct ?? 0), tax_pct: Number(i.tax_pct ?? 0),
        }))
        : [emptyLine()],
});

const addLine = () => form.items.push(emptyLine());
const removeLine = (idx) => { if (form.items.length > 1) form.items.splice(idx, 1); };
const moveUp = (idx) => { if (idx > 0) { const a = form.items[idx-1]; form.items[idx-1] = form.items[idx]; form.items[idx] = a; }};

const onProductSelect = (idx, productId) => {
    const p = props.productOptions.find(o => o.value === productId);
    if (p) {
        form.items[idx].name = p.name; form.items[idx].sku = p.sku ?? '';
        form.items[idx].unit_price = p.price ?? 0;
    }
};

const onCompanySelect = (id) => {
    form.contact_id = null;
    const c = props.companyOptions.find(o => o.value === id);
    if (c && !isEdit.value) {
        form.billing_legal_name = c.legal_name ?? c.label ?? '';
        form.billing_tax_id     = c.tax_id ?? '';
    }
};

const filteredContacts = computed(() => {
    if (!form.company_id) return props.contactOptions;
    return props.contactOptions.filter(c => c.company_id === form.company_id || c.company_id == null);
});

const lineSubtotal = (i) => +(i.quantity * i.unit_price * (1 - (i.discount_pct || 0) / 100)).toFixed(2);
const lineTax      = (i) => +(lineSubtotal(i) * (i.tax_pct || 0) / 100).toFixed(2);
const lineTotal    = (i) => +(lineSubtotal(i) + lineTax(i)).toFixed(2);

const subtotal   = computed(() => form.items.reduce((s, i) => s + lineSubtotal(i), 0));
const taxTotal   = computed(() => form.items.reduce((s, i) => s + lineTax(i), 0));
const grandTotal = computed(() => subtotal.value + taxTotal.value);
const fmt = (n) => new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n));

const submit = () => {
    if (isEdit.value) form.put(route('business_management.invoices.update', props.invoice.slug));
    else form.post(route('business_management.invoices.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Editar factura' : 'Nueva factura'" />
    <div class="invoice-form">
        <SectionHeader :back-href="route('business_management.invoices.index')"
            :title="isEdit ? 'Editar factura' : 'Nueva factura'" :subtitle="form.number || 'Borrador'">
            <template #icon><FileTextOutlined /></template>
        </SectionHeader>

        <Form layout="vertical" @submit.prevent="submit">
            <Row :gutter="[16, 16]">
                <Col :xs="24" :md="16">
                    <Card title="Datos del documento">
                        <Row :gutter="[16, 0]">
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('invoices.number')" :help="$t('invoices.number_hint')" /></template>
                                <Input v-model:value="form.number" size="large" :maxlength="40" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('invoices.document_type')" :help="$t('invoices.document_type_hint')" /></template>
                                <Input v-model:value="form.document_type" size="large" :maxlength="30" placeholder="Factura / Boleta / A / B" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem required>
                                <template #label><LabelWithHelp :label="$t('invoices.status')" :help="$t('invoices.status_hint')" /></template>
                                <Select v-model:value="form.status" :options="statusOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('invoices.currency')" :help="$t('invoices.currency_hint')" /></template>
                                <Select v-model:value="form.currency_code" :options="currencyOptions" size="large" show-search
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>

                            <Col :xs="24" :md="12"><FormItem required :validate-status="form.errors.company_id ? 'error' : ''" :help="form.errors.company_id">
                                <template #label><LabelWithHelp :label="$t('invoices.company')" :help="$t('invoices.company_hint')" /></template>
                                <Select :value="form.company_id" :options="companyOptions" size="large" show-search allow-clear
                                    @change="v => { form.company_id = v; onCompanySelect(v); }"
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('invoices.contact')" :help="$t('invoices.contact_hint')" /></template>
                                <Select v-model:value="form.contact_id" :options="filteredContacts" size="large" show-search allow-clear
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>

                            <Col :xs="12" :md="6"><FormItem required>
                                <template #label><LabelWithHelp :label="$t('invoices.issue_date')" :help="$t('invoices.issue_date_hint')" /></template>
                                <DatePicker v-model:value="form.issue_date" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem required :validate-status="form.errors.due_date ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('invoices.due_date')" :help="$t('invoices.due_date_hint')" /></template>
                                <DatePicker v-model:value="form.due_date" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('invoices.billing_legal_name')" :help="$t('invoices.billing_legal_name_hint')" /></template>
                                <Input v-model:value="form.billing_legal_name" size="large" :maxlength="200" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('invoices.billing_tax_id')" :help="$t('invoices.billing_tax_id_hint')" /></template>
                                <Input v-model:value="form.billing_tax_id" size="large" :maxlength="50" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('invoices.owner')" :help="$t('invoices.owner_hint')" /></template>
                                <Select v-model:value="form.owner_id" :options="ownerOptions" size="large" show-search allow-clear
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('invoices.reference')" :help="$t('invoices.reference_hint')" /></template>
                                <Input v-model:value="form.reference" size="large" :maxlength="30" />
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
                        <template #title>
                            <Space><ShoppingOutlined /> Líneas <Tag color="default" :bordered="false">{{ form.items.length }}</Tag></Space>
                        </template>
                        <template #extra><Button @click="addLine"><PlusOutlined /> Agregar línea</Button></template>

                        <div v-if="form.errors.items" class="error-banner">{{ form.errors.items }}</div>

                        <div class="items-table">
                            <div class="items-header">
                                <div></div>
                                <div>Producto / descripción</div>
                                <div>Cant.</div>
                                <div>Precio unit.</div>
                                <div>% Desc</div>
                                <div>% Imp</div>
                                <div style="text-align:right">Total línea</div>
                                <div></div>
                            </div>
                            <div v-for="(item, idx) in form.items" :key="idx" class="items-row">
                                <div class="col-handle"><DragOutlined @click="moveUp(idx)" style="cursor:pointer" /></div>
                                <div class="col-product">
                                    <Select :value="item.product_id" :options="productOptions" placeholder="Buscar producto…"
                                        show-search allow-clear style="width:100%; margin-bottom:6px"
                                        @change="v => { item.product_id = v; onProductSelect(idx, v); }"
                                        :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                                    <Input v-model:value="item.name" :maxlength="200" placeholder="Nombre" />
                                    <Input v-model:value="item.description" :maxlength="500" placeholder="Descripción (opcional)" style="margin-top:4px" />
                                </div>
                                <div><InputNumber v-model:value="item.quantity" :min="0.0001" :step="1" style="width:100%" /></div>
                                <div><InputNumber v-model:value="item.unit_price" :min="0" :step="0.01" style="width:100%" /></div>
                                <div><InputNumber v-model:value="item.discount_pct" :min="0" :max="100" style="width:100%" /></div>
                                <div><InputNumber v-model:value="item.tax_pct" :min="0" :max="100" style="width:100%" /></div>
                                <div class="col-total">
                                    <div class="line-total">{{ fmt(lineTotal(item)) }}</div>
                                    <div class="line-sub muted">sub {{ fmt(lineSubtotal(item)) }}</div>
                                </div>
                                <div class="col-actions">
                                    <DeleteOutlined @click="removeLine(idx)" style="color:#d4380d; cursor:pointer; font-size:1.1rem" :class="{ disabled: form.items.length === 1 }" />
                                </div>
                            </div>
                        </div>
                    </Card>
                </Col>

                <Col :xs="24" :md="12">
                    <Card title="Notas para el cliente">
                        <Input.TextArea v-model:value="form.notes" :rows="4" :maxlength="2000" show-count />
                    </Card>
                </Col>
                <Col :xs="24" :md="12">
                    <Card>
                        <template #title>Notas internas <Tag color="default" :bordered="false">No se imprimen</Tag></template>
                        <Input.TextArea v-model:value="form.internal_notes" :rows="4" :maxlength="2000" show-count />
                    </Card>
                </Col>
            </Row>
            <FormFooter :cancel-href="route('business_management.invoices.index')" :is-edit="isEdit" :processing="form.processing" create-label-key="dashboards.save_changes" />
        </Form>
    </div>
</template>

<style scoped>
.invoice-form > * + * { margin-top: 16px; }
.totals { display: flex; flex-direction: column; gap: 10px; }
.totals .row { display: flex; justify-content: space-between; font-size: 0.9rem; }
.totals .row.grand { font-size: 1.2rem; padding-top: 10px; border-top: 1px solid var(--color-border, #e8e8e8); margin-top: 4px; }
.totals .row.grand strong { color: var(--color-primary, #1677ff); }
.error-banner { color: #d4380d; padding: 8px 12px; background: #fff2f0; border: 1px solid #ffccc7; border-radius: 4px; margin-bottom: 12px; font-size: 0.85rem; }
.items-table { display: flex; flex-direction: column; gap: 8px; }
.items-header, .items-row { display: grid; grid-template-columns: 30px 1fr 90px 120px 80px 80px 130px 40px; gap: 8px; align-items: start; }
.items-header { font-weight: 600; font-size: 0.78rem; color: var(--color-text-muted, #666); padding: 6px 0; border-bottom: 2px solid var(--color-border, #e8e8e8); }
.items-row { padding: 8px 0; border-bottom: 1px dashed var(--color-border-soft, #f0f0f0); }
.col-handle, .col-actions { display: flex; align-items: center; justify-content: center; padding-top: 8px; }
.col-total { text-align: right; padding-top: 6px; }
.line-total { font-weight: 700; font-size: 1rem; }
.line-sub { font-size: 0.72rem; }
.muted { color: var(--color-text-muted, #666); }
.disabled { opacity: 0.3; pointer-events: none; }
@media (max-width: 768px) {
    .items-header { display: none; }
    .items-row { grid-template-columns: 1fr 1fr; padding: 12px; border: 1px solid var(--color-border-soft, #f0f0f0); border-radius: 6px; }
    .col-handle, .col-actions, .col-product { grid-column: 1 / -1; }
    .col-total { grid-column: 2; }
}
</style>
