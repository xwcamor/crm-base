<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select, DatePicker,
} from 'ant-design-vue';
import { PercentageOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    discount:        { type: Object, default: null },
    typeOptions:     { type: Array,  default: () => [] },
    currencyOptions: { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.discount);

const toDate = (v) => v ? dayjs(v) : null;

const form = useForm({
    code:                props.discount?.code ?? '',
    name:                props.discount?.name ?? '',
    description:         props.discount?.description ?? '',
    type:                props.discount?.type ?? 'percentage',
    value:               props.discount?.value ?? 0,
    currency_code:       props.discount?.currency_code ?? null,
    min_purchase_amount: props.discount?.min_purchase_amount ?? null,
    usage_limit:         props.discount?.usage_limit ?? null,
    usage_per_customer:  props.discount?.usage_per_customer ?? null,
    valid_from:          toDate(props.discount?.valid_from),
    valid_until:         toDate(props.discount?.valid_until),
    is_active:           props.discount?.is_active ?? true,
});

const showCurrency = computed(() => form.type === 'fixed_amount');

const submit = () => {
    const payload = {
        ...form.data(),
        valid_from:  form.valid_from ? form.valid_from.format('YYYY-MM-DD HH:mm:ss') : null,
        valid_until: form.valid_until ? form.valid_until.format('YYYY-MM-DD HH:mm:ss') : null,
    };
    if (isEdit.value) {
        form.transform(() => payload).put(route('business_management.discounts.update', props.discount.slug));
    } else {
        form.transform(() => payload).post(route('business_management.discounts.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('discounts.edit_title') : $t('discounts.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.discounts.index')"
            :title="isEdit ? $t('discounts.edit_title') : $t('discounts.create_title')"
            :subtitle="isEdit ? discount.name : $t('discounts.create_subtitle')"
        >
            <template #icon><PercentageOutlined /></template>
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
                    <Col :xs="12" :md="6">
                        <FormItem required
                            :validate-status="form.errors.code ? 'error' : ''" :help="form.errors.code">
                            <template #label><LabelWithHelp :label="$t('discounts.code')" :help="$t('discounts.code_hint')" /></template>
                            <Input v-model:value="form.code" size="large" :maxlength="60" placeholder="WELCOME10" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="12">
                        <FormItem required
                            :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                            <template #label><LabelWithHelp :label="$t('discounts.name')" :help="$t('discounts.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="150" :placeholder="$t('discounts.name_placeholder')" autofocus />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem required
                            :validate-status="form.errors.type ? 'error' : ''">
                            <template #label><LabelWithHelp :label="$t('discounts.type')" :help="$t('discounts.type_hint')" /></template>
                            <Select v-model:value="form.type" :options="typeOptions" size="large" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem required
                            :validate-status="form.errors.value ? 'error' : ''" :help="form.errors.value">
                            <template #label><LabelWithHelp :label="$t('discounts.value')" :help="$t('discounts.value_hint')" /></template>
                            <InputNumber v-model:value="form.value" :min="0" :step="0.01" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col v-if="showCurrency" :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.currency_code ? 'error' : ''" :help="form.errors.currency_code">
                            <template #label><LabelWithHelp :label="$t('discounts.currency')" :help="$t('discounts.currency_hint')" /></template>
                            <Select v-model:value="form.currency_code" :options="currencyOptions" size="large" allow-clear show-search />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.min_purchase_amount ? 'error' : ''" :help="form.errors.min_purchase_amount">
                            <template #label><LabelWithHelp :label="$t('discounts.min_purchase_amount')" :help="$t('discounts.min_purchase_amount_hint')" /></template>
                            <InputNumber v-model:value="form.min_purchase_amount" :min="0" :step="0.01" size="large" style="width:100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.usage_limit ? 'error' : ''" :help="form.errors.usage_limit">
                            <template #label><LabelWithHelp :label="$t('discounts.usage_limit')" :help="$t('discounts.usage_limit_hint')" /></template>
                            <InputNumber v-model:value="form.usage_limit" :min="0" size="large" style="width:100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.usage_per_customer ? 'error' : ''" :help="form.errors.usage_per_customer">
                            <template #label><LabelWithHelp :label="$t('discounts.usage_per_customer')" :help="$t('discounts.usage_per_customer_hint')" /></template>
                            <InputNumber v-model:value="form.usage_per_customer" :min="0" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.valid_from ? 'error' : ''" :help="form.errors.valid_from">
                            <template #label><LabelWithHelp :label="$t('discounts.valid_from')" :help="$t('discounts.valid_from_hint')" /></template>
                            <DatePicker v-model:value="form.valid_from" show-time size="large" style="width:100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.valid_until ? 'error' : ''" :help="form.errors.valid_until">
                            <template #label><LabelWithHelp :label="$t('discounts.valid_until')" :help="$t('discounts.valid_until_hint')" /></template>
                            <DatePicker v-model:value="form.valid_until" show-time size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                            <template #label><LabelWithHelp :label="$t('discounts.description')" :help="$t('discounts.description_hint')" /></template>
                            <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="500" show-count />
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('discounts.is_active')" :help="$t('discounts.is_active_hint')" /></template>
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
                    :cancel-href="route('business_management.discounts.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="discounts.new"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.form-card { border-radius: 6px; }
.state-label { font-size: 0.875rem; color: var(--color-text); font-weight: 500; }
.mb-4 { margin-bottom: 16px; }
</style>
