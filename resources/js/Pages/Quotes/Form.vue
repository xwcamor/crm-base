<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Select, DatePicker, Button,
    Row, Col, Tag, Empty, Tooltip, Space,
} from 'ant-design-vue';
import {
    FileDoneOutlined, PlusOutlined, DeleteOutlined, DragOutlined,
    ShoppingOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    quote:               { type: Object, default: null },
    companyOptions:      { type: Array, default: () => [] },
    contactOptions:      { type: Array, default: () => [] },
    dealOptions:         { type: Array, default: () => [] },
    productOptions:      { type: Array, default: () => [] },
    ownerOptions:        { type: Array, default: () => [] },
    currencyOptions:     { type: Array, default: () => [] },
    statusOptions:       { type: Array, default: () => [] },
    defaultCurrencyCode: { type: String, default: null },
    nextReference:       { type: String, default: '' },
});

// Edit cuando el quote tiene id/slug (existe en BD). Si solo trae pre-fills
// (vienen de "Crear cotización" desde Deal Show), es create y debe POST.
const isEdit = computed(() => !!(props.quote?.id || props.quote?.slug));

const today = new Date().toISOString().substring(0, 10);
const in15  = new Date(Date.now() + 15 * 86400000).toISOString().substring(0, 10);

function emptyLine() {
    return { product_id: null, name: '', description: '', sku: '', quantity: 1, unit_price: 0, discount_pct: 0, tax_pct: 18 };
}

const form = useForm({
    reference:     props.quote?.reference ?? props.nextReference ?? '',
    // Pre-fill desde ?company_id=X cuando se entra al form desde el boton
    // "Nueva Cotizacion" del tab Cotizaciones de una Company Show.
    company_id:    props.quote?.company_id ?? (Number(new URLSearchParams(window.location.search).get('company_id')) || null),
    contact_id:    props.quote?.contact_id ?? null,
    owner_id:      props.quote?.owner_id ?? null,
    deal_id:       props.quote?.deal_id ?? null,
    status:        props.quote?.status ?? 'draft',
    issue_date:    props.quote?.issue_date ?? today,
    valid_until:   props.quote?.valid_until ?? in15,
    currency_code: props.quote?.currency_code ?? props.defaultCurrencyCode ?? 'USD',
    notes:         props.quote?.notes ?? '',
    internal_notes: props.quote?.internal_notes ?? '',
    items:         props.quote?.items?.length
        ? props.quote.items.map(i => ({
            product_id: i.product_id ?? null,
            name: i.name, description: i.description ?? '', sku: i.sku ?? '',
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
        form.items[idx].name = p.name;
        form.items[idx].sku  = p.sku ?? '';
        form.items[idx].unit_price = p.price ?? 0;
    }
};

const filteredContacts = computed(() => {
    if (!form.company_id) return props.contactOptions;
    return props.contactOptions.filter(c => c.company_id === form.company_id || c.company_id == null);
});
const filteredDeals = computed(() => {
    if (!form.company_id) return props.dealOptions;
    return props.dealOptions.filter(d => d.company_id === form.company_id);
});

const lineSubtotal = (item) => +(item.quantity * item.unit_price * (1 - (item.discount_pct || 0) / 100)).toFixed(2);
const lineTax      = (item) => +(lineSubtotal(item) * (item.tax_pct || 0) / 100).toFixed(2);
const lineTotal    = (item) => +(lineSubtotal(item) + lineTax(item)).toFixed(2);

const subtotal   = computed(() => form.items.reduce((s, i) => s + lineSubtotal(i), 0));
const taxTotal   = computed(() => form.items.reduce((s, i) => s + lineTax(i), 0));
const grandTotal = computed(() => subtotal.value + taxTotal.value);
const fmt = (n) => new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n));

const submit = () => {
    if (isEdit.value) form.put(route('business_management.quotes.update', props.quote.slug));
    else form.post(route('business_management.quotes.store'));
};
</script>

<template>
    <Head :title="isEdit ? 'Editar cotización' : 'Nueva cotización'" />

    <div class="quote-form">
        <SectionHeader
            :back-href="route('business_management.quotes.index')"
            :title="isEdit ? 'Editar cotización' : 'Nueva cotización'"
            :subtitle="form.reference || 'Borrador'">
            <template #icon><FileDoneOutlined /></template>
        </SectionHeader>

        <Form layout="vertical" @submit.prevent="submit">
            <Row :gutter="[16, 16]">
                <Col :xs="24" :md="16">
                    <Card title="Datos del documento">
                        <Row :gutter="[16, 0]">
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('quotes.reference')" :help="$t('quotes.reference_hint')" /></template>
                                <Input v-model:value="form.reference" size="large" :maxlength="30" />
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem required>
                                <template #label><LabelWithHelp :label="$t('quotes.status')" :help="$t('quotes.status_hint')" /></template>
                                <Select v-model:value="form.status" :options="statusOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('quotes.currency')" :help="$t('quotes.currency_hint')" /></template>
                                <Select v-model:value="form.currency_code" :options="currencyOptions" size="large" show-search
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>

                            <Col :xs="24" :md="12"><FormItem required :validate-status="form.errors.company_id ? 'error' : ''" :help="form.errors.company_id">
                                <template #label><LabelWithHelp :label="$t('quotes.company')" :help="$t('quotes.company_hint')" /></template>
                                <Select v-model:value="form.company_id" :options="companyOptions" size="large"
                                    show-search allow-clear @change="form.contact_id = null; form.deal_id = null"
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('quotes.contact')" :help="$t('quotes.contact_hint')" /></template>
                                <Select v-model:value="form.contact_id" :options="filteredContacts" size="large"
                                    show-search allow-clear
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>

                            <Col :xs="12" :md="6"><FormItem required>
                                <template #label><LabelWithHelp :label="$t('quotes.issue_date')" :help="$t('quotes.issue_date_hint')" /></template>
                                <DatePicker v-model:value="form.issue_date" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('quotes.valid_until')" :help="$t('quotes.valid_until_hint')" /></template>
                                <DatePicker v-model:value="form.valid_until" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('quotes.owner')" :help="$t('quotes.owner_hint')" /></template>
                                <Select v-model:value="form.owner_id" :options="ownerOptions" size="large" show-search allow-clear
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('quotes.deal')" :help="$t('quotes.deal_hint')" /></template>
                                <Select v-model:value="form.deal_id" :options="filteredDeals" size="large" show-search allow-clear
                                    :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
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
                            <Space><ShoppingOutlined /> Líneas
                                <Tag color="default" :bordered="false">{{ form.items.length }} {{ form.items.length === 1 ? 'línea' : 'líneas' }}</Tag>
                            </Space>
                        </template>
                        <template #extra>
                            <Button @click="addLine"><PlusOutlined /> Agregar línea</Button>
                        </template>

                        <div v-if="form.errors.items" class="error-banner">{{ form.errors.items }}</div>

                        <div class="items-table">
                            <div class="items-header">
                                <div class="col-handle"></div>
                                <div class="col-product">Producto / descripción</div>
                                <div class="col-qty">Cant.</div>
                                <div class="col-price">Precio unit.</div>
                                <div class="col-disc">% Desc</div>
                                <div class="col-tax">% Imp</div>
                                <div class="col-total">Total línea</div>
                                <div class="col-actions"></div>
                            </div>

                            <div v-for="(item, idx) in form.items" :key="idx" class="items-row">
                                <div class="col-handle">
                                    <Tooltip title="Subir"><DragOutlined @click="moveUp(idx)" style="cursor:pointer" /></Tooltip>
                                </div>
                                <div class="col-product">
                                    <Select :value="item.product_id" :options="productOptions" placeholder="Buscar producto…"
                                        show-search allow-clear style="width:100%; margin-bottom:6px"
                                        @change="v => { item.product_id = v; onProductSelect(idx, v); }"
                                        :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                                    <Input v-model:value="item.name" placeholder="Nombre (auto-rellena al elegir producto)" :maxlength="200" />
                                    <Input v-model:value="item.description" placeholder="Descripción (opcional)" style="margin-top:4px" :maxlength="500" />
                                </div>
                                <div class="col-qty">
                                    <InputNumber v-model:value="item.quantity" :min="0.0001" :step="1" style="width:100%" />
                                </div>
                                <div class="col-price">
                                    <InputNumber v-model:value="item.unit_price" :min="0" :step="0.01" style="width:100%" />
                                </div>
                                <div class="col-disc">
                                    <InputNumber v-model:value="item.discount_pct" :min="0" :max="100" :step="1" style="width:100%" />
                                </div>
                                <div class="col-tax">
                                    <InputNumber v-model:value="item.tax_pct" :min="0" :max="100" :step="1" style="width:100%" />
                                </div>
                                <div class="col-total">
                                    <div class="line-total">{{ fmt(lineTotal(item)) }}</div>
                                    <div class="line-sub muted">sub {{ fmt(lineSubtotal(item)) }}</div>
                                </div>
                                <div class="col-actions">
                                    <Tooltip title="Eliminar línea">
                                        <DeleteOutlined @click="removeLine(idx)" style="color:#d4380d; cursor:pointer; font-size:1.1rem" :class="{ disabled: form.items.length === 1 }" />
                                    </Tooltip>
                                </div>
                            </div>

                            <div v-if="form.items.length === 0" class="empty">
                                <Empty description="Agrega la primera línea" />
                            </div>
                        </div>
                    </Card>
                </Col>

                <Col :xs="24" :md="12">
                    <Card title="Notas para el cliente">
                        <Input.TextArea v-model:value="form.notes" :rows="4" :maxlength="2000" show-count placeholder="Términos, condiciones, observaciones…" />
                    </Card>
                </Col>
                <Col :xs="24" :md="12">
                    <Card>
                        <template #title>Notas internas <Tag color="default" :bordered="false">No se imprimen</Tag></template>
                        <Input.TextArea v-model:value="form.internal_notes" :rows="4" :maxlength="2000" show-count placeholder="Recordatorios, info backstage…" />
                    </Card>
                </Col>
            </Row>

            <FormFooter :cancel-href="route('business_management.quotes.index')" :is-edit="isEdit" :processing="form.processing" create-label-key="global.save_changes" />
        </Form>
    </div>
</template>

<style scoped>
.quote-form > * + * { margin-top: 16px; }

.totals { display: flex; flex-direction: column; gap: 10px; }
.totals .row { display: flex; justify-content: space-between; align-items: baseline; font-size: 0.9rem; }
.totals .row.grand { font-size: 1.2rem; padding-top: 10px; border-top: 1px solid var(--color-border, #e8e8e8); margin-top: 4px; }
.totals .row.grand strong { color: var(--color-primary, #1677ff); }

.error-banner { color: #d4380d; padding: 8px 12px; background: #fff2f0; border: 1px solid #ffccc7; border-radius: 4px; margin-bottom: 12px; font-size: 0.85rem; }

.items-table { display: flex; flex-direction: column; gap: 8px; }
.items-header, .items-row {
    display: grid;
    grid-template-columns: 30px 1fr 90px 120px 80px 80px 130px 40px;
    gap: 8px; align-items: start;
}
.items-header { font-weight: 600; font-size: 0.78rem; color: var(--color-text-muted, #666); padding: 6px 0; border-bottom: 2px solid var(--color-border, #e8e8e8); }
.items-row { padding: 8px 0; border-bottom: 1px dashed var(--color-border-soft, #f0f0f0); }
.col-handle, .col-actions { display: flex; align-items: center; justify-content: center; padding-top: 8px; }
.col-product { display: flex; flex-direction: column; gap: 0; }
.col-total { text-align: right; padding-top: 6px; }
.line-total { font-weight: 700; font-size: 1rem; color: var(--color-text-strong, #111); }
.line-sub { font-size: 0.72rem; }
.muted { color: var(--color-text-muted, #666); }
.disabled { opacity: 0.3; pointer-events: none; }
.empty { padding: 32px; text-align: center; }

@media (max-width: 768px) {
    .items-header { display: none; }
    .items-row {
        grid-template-columns: 1fr 1fr;
        gap: 8px;
        padding: 12px;
        border: 1px solid var(--color-border-soft, #f0f0f0);
        border-radius: 6px;
    }
    .col-handle, .col-actions, .col-product { grid-column: 1 / -1; }
    .col-total { text-align: right; grid-column: 2; }
}
</style>
