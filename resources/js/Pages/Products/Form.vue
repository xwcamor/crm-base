<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select, Tag, Anchor,
} from 'ant-design-vue';
import {
    AppstoreOutlined, TagOutlined, DollarOutlined, InboxOutlined,
    ReloadOutlined, ColumnHeightOutlined, PictureOutlined, SettingOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    product:             { type: Object, default: null },
    categoryOptions:     { type: Array, default: () => [] },
    typeOptions:         { type: Array, default: () => [] },
    billingCycleOptions: { type: Array, default: () => [] },
    currencyOptions:     { type: Array, default: () => [] },
    defaultCurrencyCode: { type: String, default: null },
});

const isEdit = computed(() => !!props.product);

const form = useForm({
    name:             props.product?.name ?? '',
    sku:              props.product?.sku ?? '',
    barcode:          props.product?.barcode ?? '',
    description:      props.product?.description ?? '',
    long_description: props.product?.long_description ?? '',

    category_id: props.product?.category_id ?? null,
    type:        props.product?.type ?? 'good',
    brand:       props.product?.brand ?? '',

    cost:          props.product?.cost ?? null,
    final_cost:    props.product?.final_cost ?? null,
    list_price:    props.product?.list_price ?? 0,
    currency_code: props.product?.currency_code ?? props.defaultCurrencyCode ?? null,

    track_inventory:     props.product?.track_inventory ?? true,
    low_stock_threshold: props.product?.low_stock_threshold ?? 0,

    billing_cycle:  props.product?.billing_cycle ?? null,
    billing_period: props.product?.billing_period ?? 1,

    weight_kg: props.product?.weight_kg ?? null,
    length_cm: props.product?.length_cm ?? null,
    width_cm:  props.product?.width_cm  ?? null,
    height_cm: props.product?.height_cm ?? null,

    image_url:   props.product?.image_url ?? '',
    external_id: props.product?.external_id ?? '',

    is_active: props.product?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.products.update', props.product.slug));
    } else {
        form.post(route('business_management.products.store'));
    }
};

// Conditional rendering
const isGood         = computed(() => form.type === 'good');
const isSubscription = computed(() => form.type === 'subscription');

// Computed: margen
const marginAmount = computed(() => {
    if (form.cost == null || form.list_price == null) return null;
    return Number(form.list_price) - Number(form.cost);
});
const marginPct = computed(() => {
    if (!form.list_price || form.cost == null) return null;
    const v = ((Number(form.list_price) - Number(form.cost)) / Number(form.list_price)) * 100;
    return Number.isFinite(v) ? v.toFixed(1) : null;
});
// Margen real post-import: usa final_cost (landed cost) en vez de cost.
// null mientras no se haya registrado final_cost — el cliente lo llena cuando
// el producto llega al almacen y conoce flete + aduanas + agentes reales.
const finalMarginPct = computed(() => {
    if (!form.list_price || form.final_cost == null) return null;
    const v = ((Number(form.list_price) - Number(form.final_cost)) / Number(form.list_price)) * 100;
    return Number.isFinite(v) ? v.toFixed(1) : null;
});

// Hero helpers
const typeLabel = computed(() =>
    props.typeOptions.find(t => t.value === form.type)?.label ?? form.type
);
const typeColor = computed(() => ({
    good: 'blue', service: 'green', subscription: 'purple', bundle: 'orange',
}[form.type] ?? 'default'));

const formattedPrice = computed(() => {
    if (form.list_price == null || form.list_price === '') return null;
    const n = Number(form.list_price);
    if (Number.isNaN(n)) return null;
    return new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
});

const sections = [
    { key: 'general',        label: 'Datos generales', icon: AppstoreOutlined },
    { key: 'classification', label: 'Clasificación',   icon: TagOutlined },
    { key: 'pricing',        label: 'Precios + moneda', icon: DollarOutlined },
    { key: 'inventory',      label: 'Inventario',      icon: InboxOutlined },
    { key: 'subscription',   label: 'Suscripción',     icon: ReloadOutlined },
    { key: 'shipping',       label: 'Dimensiones',     icon: ColumnHeightOutlined },
    { key: 'media',          label: 'Imagen + IDs',    icon: PictureOutlined },
];

const errorsBySection = computed(() => {
    const e = form.errors || {};
    const groups = {
        general:        ['name', 'sku', 'barcode', 'description', 'long_description'],
        classification: ['category_id', 'type', 'brand'],
        pricing:        ['cost', 'final_cost', 'list_price', 'currency_code'],
        inventory:      ['track_inventory', 'low_stock_threshold'],
        subscription:   ['billing_cycle', 'billing_period'],
        shipping:       ['weight_kg', 'length_cm', 'width_cm', 'height_cm'],
        media:          ['image_url', 'external_id'],
    };
    return Object.fromEntries(Object.entries(groups).map(([k, fs]) => [k, fs.filter(f => e[f]).length]));
});

const visibleSections = computed(() => sections.filter(s => {
    if (s.key === 'inventory')    return isGood.value;
    if (s.key === 'subscription') return isSubscription.value;
    if (s.key === 'shipping')     return isGood.value;
    return true;
}));
</script>

<template>
    <Head :title="isEdit ? $t('products.edit_title') : $t('products.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.products.index')"
            :title="isEdit ? $t('products.edit_title') : $t('products.create_title')"
            :subtitle="isEdit ? product.name : $t('products.create_subtitle')"
        >
            <template #icon><AppstoreOutlined /></template>
        </SectionHeader>

        <Form layout="vertical" @submit.prevent="submit" class="product-form">

            <Alert v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                type="error" show-icon :message="$t('global.fix_marked_fields')" class="mb-4" />

            <!-- HERO -->
            <Card class="hero-card" :bodyStyle="{ padding: '20px 24px' }">
                <div class="hero-row">
                    <div class="hero-icon-box" v-if="!form.image_url">
                        <AppstoreOutlined />
                    </div>
                    <img v-else :src="form.image_url" class="hero-img" alt="" />
                    <div class="hero-text">
                        <div class="hero-name">{{ form.name || $t('products.name_placeholder') }}</div>
                        <div class="hero-meta">
                            <span v-if="form.sku" class="hero-pill">SKU {{ form.sku }}</span>
                            <span v-if="form.brand" class="muted">{{ form.brand }}</span>
                        </div>
                    </div>
                    <div class="hero-value-block">
                        <div class="hero-value-amount" v-if="formattedPrice">
                            <span class="hero-currency">{{ form.currency_code }}</span> {{ formattedPrice }}
                        </div>
                        <div class="hero-value-amount muted" v-else>—</div>
                        <div class="hero-margin muted" v-if="marginPct != null">
                            {{ $t('products.margin') }}: {{ marginPct }}%
                        </div>
                    </div>
                    <div class="hero-tags">
                        <Tag :color="typeColor" :bordered="false">{{ typeLabel }}</Tag>
                        <Tag v-if="!form.track_inventory && isGood" color="default" :bordered="false">No-stock</Tag>
                    </div>
                </div>
            </Card>

            <div class="form-layout">
                <aside class="form-nav">
                    <Anchor :affix="true" :offsetTop="80" :show-ink-in-fixed="true">
                        <Anchor.Link v-for="s in visibleSections" :key="s.key" :href="`#section-${s.key}`">
                            <template #title>
                                <span class="nav-link">
                                    <component :is="s.icon" /> {{ s.label }}
                                    <Tag v-if="errorsBySection[s.key]" color="red" class="nav-badge">{{ errorsBySection[s.key] }}</Tag>
                                </span>
                            </template>
                        </Anchor.Link>
                    </Anchor>
                </aside>

                <main class="form-cards">

                    <!-- Datos generales -->
                    <Card id="section-general" class="section-card">
                        <template #title><span class="section-title"><AppstoreOutlined /> {{ $t('products.section_general') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="14"><FormItem required
                                :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                                <template #label><LabelWithHelp :label="$t('products.name')" :help="$t('products.name_hint')" /></template>
                                <Input v-model:value="form.name" size="large" :maxlength="200" show-count autofocus
                                    :placeholder="$t('products.name_placeholder_form')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="5"><FormItem
                                :validate-status="form.errors.sku ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('products.sku')" :help="$t('products.sku_hint')" /></template>
                                <Input v-model:value="form.sku" size="large" :maxlength="60"
                                    :placeholder="$t('products.sku_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="5"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.barcode')" :help="$t('products.barcode_hint')" /></template>
                                <Input v-model:value="form.barcode" size="large" :maxlength="60"
                                    :placeholder="$t('products.barcode_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.description')" :help="$t('products.description_hint')" /></template>
                                <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="1000" show-count
                                    :placeholder="$t('products.description_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.long_description')" :help="$t('products.long_description_hint')" /></template>
                                <Input.TextArea v-model:value="form.long_description" :rows="4" :maxlength="5000" show-count
                                    :placeholder="$t('products.long_description_placeholder')" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Clasificación -->
                    <Card id="section-classification" class="section-card">
                        <template #title><span class="section-title"><TagOutlined /> {{ $t('products.section_classification') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.type ? 'error' : ''" :help="form.errors.type">
                                <template #label><LabelWithHelp :label="$t('products.type')" :help="$t('products.type_hint')" /></template>
                                <Select v-model:value="form.type" :options="typeOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="24" :md="10"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.category')" :help="$t('products.category_hint')" /></template>
                                <Select v-model:value="form.category_id" :options="categoryOptions"
                                    :placeholder="$t('products.category_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.brand')" :help="$t('products.brand_hint')" /></template>
                                <Input v-model:value="form.brand" size="large" :maxlength="100"
                                    :placeholder="$t('products.brand_placeholder')" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Precios -->
                    <Card id="section-pricing" class="section-card">
                        <template #title><span class="section-title"><DollarOutlined /> {{ $t('products.section_pricing') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.cost')" :help="$t('products.cost_hint')" /></template>
                                <InputNumber v-model:value="form.cost" :min="0" :step="0.01" size="large"
                                    style="width:100%" :placeholder="$t('products.cost_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem required
                                :validate-status="form.errors.list_price ? 'error' : ''" :help="form.errors.list_price">
                                <template #label><LabelWithHelp :label="$t('products.list_price')" :help="$t('products.list_price_hint')" /></template>
                                <InputNumber v-model:value="form.list_price" :min="0" :step="0.01" size="large"
                                    style="width:100%" :placeholder="$t('products.list_price_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.currency')" :help="$t('products.currency_hint')" /></template>
                                <Select v-model:value="form.currency_code" :options="currencyOptions"
                                    :placeholder="defaultCurrencyCode ? `${$t('products.currency_placeholder')} (${defaultCurrencyCode})` : $t('products.currency_placeholder')"
                                    size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.margin_pct')" :help="$t('products.margin_pct_hint')" /></template>
                                <Input :value="marginPct != null ? marginPct + '%' : '—'" size="large" disabled />
                            </FormItem></Col>
                        </Row>

                        <!-- Fila paralela: costo y margen REALES post-import -->
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="6"><FormItem
                                :validate-status="form.errors.final_cost ? 'error' : ''" :help="form.errors.final_cost">
                                <template #label><LabelWithHelp :label="$t('products.final_cost')" :help="$t('products.final_cost_hint')" /></template>
                                <InputNumber v-model:value="form.final_cost" :min="0" :step="0.01" size="large"
                                    style="width:100%" :placeholder="$t('products.final_cost_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.final_margin_pct')" :help="$t('products.final_margin_pct_hint')" /></template>
                                <Input :value="finalMarginPct != null ? finalMarginPct + '%' : '—'" size="large" disabled />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Inventario (solo good) -->
                    <Card v-if="isGood" id="section-inventory" class="section-card">
                        <template #title><span class="section-title"><InboxOutlined /> {{ $t('products.section_inventory') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="8"><FormItem>
                                <Space>
                                    <Switch v-model:checked="form.track_inventory" />
                                    <span class="state-label">{{ $t('products.track_inventory') }}</span>
                                </Space>
                                <div class="field-hint">{{ $t('products.track_inventory_hint') }}</div>
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.low_stock_threshold')" :help="$t('products.low_stock_threshold_hint')" /></template>
                                <InputNumber v-model:value="form.low_stock_threshold" :min="0" size="large"
                                    style="width:100%" :disabled="!form.track_inventory"
                                    :placeholder="$t('products.low_stock_threshold_placeholder')" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Subscription (solo subscription) -->
                    <Card v-if="isSubscription" id="section-subscription" class="section-card">
                        <template #title><span class="section-title"><ReloadOutlined /> {{ $t('products.section_subscription') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="8"><FormItem required
                                :validate-status="form.errors.billing_cycle ? 'error' : ''" :help="form.errors.billing_cycle">
                                <template #label><LabelWithHelp :label="$t('products.billing_cycle')" :help="$t('products.billing_cycle_hint')" /></template>
                                <Select v-model:value="form.billing_cycle" :options="billingCycleOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.billing_period')" :help="$t('products.billing_period_hint')" /></template>
                                <InputNumber v-model:value="form.billing_period" :min="1" size="large" style="width:100%" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Dimensiones (solo good) -->
                    <Card v-if="isGood" id="section-shipping" class="section-card">
                        <template #title><span class="section-title"><ColumnHeightOutlined /> {{ $t('products.section_shipping') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.weight_kg')" :help="$t('products.weight_kg_hint')" /></template>
                                <InputNumber v-model:value="form.weight_kg" :min="0" :step="0.01" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.length_cm')" :help="$t('products.length_cm_hint')" /></template>
                                <InputNumber v-model:value="form.length_cm" :min="0" :step="0.1" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.width_cm')" :help="$t('products.width_cm_hint')" /></template>
                                <InputNumber v-model:value="form.width_cm" :min="0" :step="0.1" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.height_cm')" :help="$t('products.height_cm_hint')" /></template>
                                <InputNumber v-model:value="form.height_cm" :min="0" :step="0.1" size="large" style="width:100%" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Imagen + IDs -->
                    <Card id="section-media" class="section-card">
                        <template #title><span class="section-title"><PictureOutlined /> {{ $t('products.section_media') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="16"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.image_url')" :help="$t('products.image_url_hint')" /></template>
                                <Input v-model:value="form.image_url" size="large" :maxlength="500" placeholder="https://..." />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('products.external_id')" :help="$t('products.external_id_hint')" /></template>
                                <Input v-model:value="form.external_id" size="large" :maxlength="100" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Estado (solo edit) -->
                    <Card v-if="isEdit" class="section-card">
                        <template #title><span class="section-title"><SettingOutlined /> {{ $t('products.is_active') }}</span></template>
                        <Space>
                            <Switch v-model:checked="form.is_active" />
                            <span class="state-label">
                                {{ form.is_active ? $t('global.active') : $t('global.inactive') }}
                            </span>
                        </Space>
                    </Card>
                </main>
            </div>

            <FormFooter
                :cancel-href="route('business_management.products.index')"
                :is-edit="isEdit"
                :processing="form.processing"
                create-label-key="products.new"
            />
        </Form>
    </div>
</template>

<style scoped>
.product-form > * + * { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }

.hero-card {
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: linear-gradient(135deg, var(--color-surface, #fff) 0%, var(--color-surface-alt, #fafafa) 100%);
}
.hero-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.hero-icon-box {
    width: 56px; height: 56px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    background: var(--color-primary, #1677ff);
    color: #fff; font-size: 1.5rem; flex-shrink: 0;
}
.hero-img { width: 56px; height: 56px; border-radius: 12px; object-fit: cover; flex-shrink: 0; border: 1px solid var(--color-border); }
.hero-text { flex: 1 1 240px; min-width: 0; }
.hero-name { font-size: 1.25rem; font-weight: 600; color: var(--color-text-strong, #111); line-height: 1.3; }
.hero-meta { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; margin-top: 4px; color: var(--color-text-muted, #666); flex-wrap: wrap; }
.hero-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: var(--color-surface-alt, #f0f0f0); border-radius: 4px; font-size: 0.78rem; }
.hero-value-block { text-align: right; min-width: 140px; }
.hero-value-amount { font-size: 1.4rem; font-weight: 700; color: var(--color-text-strong, #111); }
.hero-currency { font-size: 0.95rem; font-weight: 500; color: var(--color-text-muted, #666); margin-right: 2px; }
.hero-margin { font-size: 0.75rem; margin-top: 2px; }
.hero-tags { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.muted { color: var(--color-text-muted, #666); }

.form-layout { display: grid; grid-template-columns: 240px 1fr; gap: 24px; align-items: start; }
.form-nav { position: sticky; top: 16px; align-self: start; }
.form-nav :deep(.ant-anchor) {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e8e8e8);
    border-radius: 8px;
    padding: 12px 8px;
}
.form-nav :deep(.ant-anchor-ink) { display: none; }
.nav-link { display: inline-flex; align-items: center; gap: 8px; font-size: 0.88rem; }
.nav-link :deep(.anticon) { font-size: 0.95rem; }
.nav-badge { margin-left: auto; font-size: 0.65rem; padding: 0 6px; line-height: 16px; border-radius: 8px; }
.form-cards { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

.section-card {
    border-radius: 8px;
    border: 1px solid var(--color-border, #e8e8e8);
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    scroll-margin-top: 80px;
}
.section-card :deep(.ant-card-head) {
    border-bottom: 1px solid var(--color-border-soft, #f0f0f0);
    background: var(--color-surface-alt, #fafafa);
    padding: 0 24px;
    min-height: 48px;
}
.section-card :deep(.ant-card-head-title) { padding: 12px 0; }
.section-title { display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 600; color: var(--color-text-strong, #111); }
.section-title :deep(.anticon) { color: var(--color-primary, #1677ff); font-size: 1rem; }

.state-label { font-size: 0.875rem; color: var(--color-text); font-weight: 500; }
.field-hint { font-size: 0.75rem; color: var(--color-text-muted, #666); margin-top: 4px; }

@media (max-width: 1024px) {
    .form-layout { grid-template-columns: 1fr; }
    .form-nav { position: static; }
    .form-nav :deep(.ant-anchor) { display: flex; flex-wrap: wrap; gap: 4px; padding: 8px; }
    .nav-link { font-size: 0.8rem; }
}
@media (max-width: 768px) {
    .hero-row { flex-direction: column; align-items: flex-start; }
    .hero-value-block { text-align: left; }
}
</style>
