<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select, Button,
} from 'ant-design-vue';
import { AppstoreAddOutlined, PlusOutlined, DeleteOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    variant:        { type: Object, default: null },
    productOptions: { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.variant);

const attributesToPairs = (obj) => {
    if (!obj || typeof obj !== 'object') return [];
    return Object.entries(obj).map(([k, v]) => ({ key: String(k), value: String(v ?? '') }));
};

const pairsToObject = (pairs) => {
    const out = {};
    for (const p of pairs) {
        const k = (p.key ?? '').trim();
        if (k === '') continue;
        out[k] = p.value ?? '';
    }
    return out;
};

const attributePairs = ref(attributesToPairs(props.variant?.attributes));

const form = useForm({
    name:                props.variant?.name ?? '',
    sku:                 props.variant?.sku ?? '',
    product_id:          props.variant?.product_id ?? null,
    barcode:             props.variant?.barcode ?? '',
    attributes:          props.variant?.attributes ?? null,
    cost:                props.variant?.cost ?? null,
    price:               props.variant?.price ?? null,
    low_stock_threshold: props.variant?.low_stock_threshold ?? 0,
    image_url:           props.variant?.image_url ?? '',
    sort_order:          props.variant?.sort_order ?? 0,
    is_active:           props.variant?.is_active ?? true,
});

const addAttribute = () => {
    attributePairs.value.push({ key: '', value: '' });
};

const removeAttribute = (i) => {
    attributePairs.value.splice(i, 1);
};

const submit = () => {
    form.attributes = pairsToObject(attributePairs.value);
    if (Object.keys(form.attributes).length === 0) {
        form.attributes = null;
    }

    if (isEdit.value) {
        form.put(route('business_management.product_variants.update', props.variant.slug));
    } else {
        form.post(route('business_management.product_variants.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('product_variants.edit_title') : $t('product_variants.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.product_variants.index')"
            :title="isEdit ? $t('product_variants.edit_title') : $t('product_variants.create_title')"
            :subtitle="isEdit ? variant.name : $t('product_variants.create_subtitle')"
        >
            <template #icon><AppstoreAddOutlined /></template>
        </SectionHeader>

        <Card class="form-card" :bodyStyle="{ padding: '24px 28px' }">
            <Form layout="vertical" @submit.prevent="submit">

                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error" show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="12">
                        <FormItem required
                            :validate-status="form.errors.sku ? 'error' : ''" :help="form.errors.sku">
                            <template #label><LabelWithHelp :label="$t('product_variants.sku')" :help="$t('product_variants.sku_hint')" /></template>
                            <Input v-model:value="form.sku" size="large" :maxlength="60" :placeholder="$t('product_variants.sku_placeholder')" autofocus />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="12">
                        <FormItem required
                            :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                            <template #label><LabelWithHelp :label="$t('product_variants.name')" :help="$t('product_variants.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="200" :placeholder="$t('product_variants.name_placeholder')" />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="12">
                        <FormItem required
                            :validate-status="form.errors.product_id ? 'error' : ''" :help="form.errors.product_id">
                            <template #label><LabelWithHelp :label="$t('product_variants.product')" :help="$t('product_variants.product_hint')" /></template>
                            <Select
                                v-model:value="form.product_id"
                                :options="productOptions"
                                size="large"
                                show-search
                                :filter-option="(i, o) => (o.label ?? '').toLowerCase().includes(i.toLowerCase())"
                                :placeholder="$t('product_variants.product_hint')"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="12">
                        <FormItem
                            :validate-status="form.errors.barcode ? 'error' : ''" :help="form.errors.barcode">
                            <template #label><LabelWithHelp :label="$t('product_variants.barcode')" :help="$t('product_variants.barcode_hint')" /></template>
                            <Input v-model:value="form.barcode" size="large" :maxlength="60" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.cost ? 'error' : ''" :help="form.errors.cost">
                            <template #label><LabelWithHelp :label="$t('product_variants.cost')" :help="$t('product_variants.cost_hint')" /></template>
                            <InputNumber v-model:value="form.cost" :min="0" :step="0.01" size="large" style="width:100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.price ? 'error' : ''" :help="form.errors.price">
                            <template #label><LabelWithHelp :label="$t('product_variants.price')" :help="$t('product_variants.price_hint')" /></template>
                            <InputNumber v-model:value="form.price" :min="0" :step="0.01" size="large" style="width:100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.low_stock_threshold ? 'error' : ''" :help="form.errors.low_stock_threshold">
                            <template #label><LabelWithHelp :label="$t('product_variants.low_stock_threshold')" :help="$t('product_variants.low_stock_threshold_hint')" /></template>
                            <InputNumber v-model:value="form.low_stock_threshold" :min="0" size="large" style="width:100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.sort_order ? 'error' : ''" :help="form.errors.sort_order">
                            <template #label><LabelWithHelp :label="$t('product_variants.sort_order')" :help="$t('product_variants.sort_order_hint')" /></template>
                            <InputNumber v-model:value="form.sort_order" :min="0" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.image_url ? 'error' : ''" :help="form.errors.image_url">
                            <template #label><LabelWithHelp :label="$t('product_variants.image_url')" :help="$t('product_variants.image_url_hint')" /></template>
                            <Input v-model:value="form.image_url" size="large" :maxlength="500" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem :validate-status="form.errors.attributes ? 'error' : ''" :help="form.errors.attributes">
                            <template #label><LabelWithHelp :label="$t('product_variants.attributes')" :help="$t('product_variants.attributes_hint')" /></template>
                            <div class="attr-list">
                                <div v-for="(pair, i) in attributePairs" :key="i" class="attr-row">
                                    <Input
                                        v-model:value="pair.key"
                                        :placeholder="$t('product_variants.attributes_key')"
                                        :maxlength="50"
                                        class="attr-key"
                                    />
                                    <Input
                                        v-model:value="pair.value"
                                        :placeholder="$t('product_variants.attributes_value')"
                                        :maxlength="100"
                                        class="attr-value"
                                    />
                                    <Button type="text" danger @click="removeAttribute(i)" :aria-label="$t('global.delete')">
                                        <DeleteOutlined />
                                    </Button>
                                </div>
                                <Button type="dashed" block @click="addAttribute">
                                    <PlusOutlined /> {{ $t('product_variants.attributes_add') }}
                                </Button>
                            </div>
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('product_variants.is_active')" :help="$t('product_variants.is_active_hint')" /></template>
                            <Space>
                                <Switch v-model:checked="form.is_active" />
                                <span class="state-label">
                                    {{ form.is_active ? $t('global.active') : $t('global.inactive') }}
                                </span>
                            </Space>
                        </FormItem>
                    </Col>
                </Row>

                <FormFooter
                    :cancel-href="route('business_management.product_variants.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="product_variants.new"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.form-card { border-radius: 6px; }
.state-label { font-size: 0.875rem; color: var(--color-text); font-weight: 500; }
.mb-4 { margin-bottom: 16px; }

.attr-list { display: flex; flex-direction: column; gap: 8px; }
.attr-row {
    display: flex;
    gap: 8px;
    align-items: center;
}
.attr-key   { flex: 0 0 180px; }
.attr-value { flex: 1 1 auto; }
@media (max-width: 640px) {
    .attr-row { flex-direction: column; align-items: stretch; }
    .attr-key, .attr-value { flex: 1 1 auto; }
}
</style>
