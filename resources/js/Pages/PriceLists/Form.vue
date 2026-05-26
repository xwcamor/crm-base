<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select, DatePicker,
} from 'ant-design-vue';
import { TagsOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    priceList:           { type: Object, default: null },
    currencyOptions:     { type: Array,  default: () => [] },
    defaultCurrencyCode: { type: String, default: null },
});

const isEdit = computed(() => !!props.priceList);

const toDate = (v) => v ? dayjs(v) : null;

const form = useForm({
    name:                props.priceList?.name ?? '',
    description:         props.priceList?.description ?? '',
    currency_code:       props.priceList?.currency_code ?? props.defaultCurrencyCode ?? null,
    valid_from:          toDate(props.priceList?.valid_from),
    valid_until:         toDate(props.priceList?.valid_until),
    global_discount_pct: props.priceList?.global_discount_pct ?? 0,
    priority:            props.priceList?.priority ?? 0,
    is_default:          props.priceList?.is_default ?? false,
    is_active:           props.priceList?.is_active ?? true,
});

const submit = () => {
    const payload = {
        ...form.data(),
        valid_from:  form.valid_from ? form.valid_from.format('YYYY-MM-DD HH:mm:ss') : null,
        valid_until: form.valid_until ? form.valid_until.format('YYYY-MM-DD HH:mm:ss') : null,
    };
    if (isEdit.value) {
        form.transform(() => payload).put(route('business_management.price_lists.update', props.priceList.slug));
    } else {
        form.transform(() => payload).post(route('business_management.price_lists.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('price_lists.edit_title') : $t('price_lists.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.price_lists.index')"
            :title="isEdit ? $t('price_lists.edit_title') : $t('price_lists.create_title')"
            :subtitle="isEdit ? priceList.name : $t('price_lists.create_subtitle')"
        >
            <template #icon><TagsOutlined /></template>
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
                            :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                            <template #label><LabelWithHelp :label="$t('price_lists.name')" :help="$t('price_lists.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="150" :placeholder="$t('price_lists.name_placeholder')" autofocus />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.currency_code ? 'error' : ''" :help="form.errors.currency_code">
                            <template #label><LabelWithHelp :label="$t('price_lists.currency')" :help="$t('price_lists.currency_hint')" /></template>
                            <Select v-model:value="form.currency_code" :options="currencyOptions" size="large" allow-clear show-search />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.global_discount_pct ? 'error' : ''" :help="form.errors.global_discount_pct">
                            <template #label><LabelWithHelp :label="$t('price_lists.global_discount_pct')" :help="$t('price_lists.global_discount_pct_hint')" /></template>
                            <InputNumber v-model:value="form.global_discount_pct" :min="0" :max="100" :step="0.01" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.priority ? 'error' : ''" :help="form.errors.priority">
                            <template #label><LabelWithHelp :label="$t('price_lists.priority')" :help="$t('price_lists.priority_hint')" /></template>
                            <InputNumber v-model:value="form.priority" :min="0" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.valid_from ? 'error' : ''" :help="form.errors.valid_from">
                            <template #label><LabelWithHelp :label="$t('price_lists.valid_from')" :help="$t('price_lists.valid_from_hint')" /></template>
                            <DatePicker v-model:value="form.valid_from" show-time size="large" style="width:100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.valid_until ? 'error' : ''" :help="form.errors.valid_until">
                            <template #label><LabelWithHelp :label="$t('price_lists.valid_until')" :help="$t('price_lists.valid_until_hint')" /></template>
                            <DatePicker v-model:value="form.valid_until" show-time size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                            <template #label><LabelWithHelp :label="$t('price_lists.description')" :help="$t('price_lists.description_hint')" /></template>
                            <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="500" show-count />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('price_lists.is_default')" :help="$t('price_lists.is_default_hint')" /></template>
                            <Space>
                                <Switch v-model:checked="form.is_default" />
                                <span class="state-label">
                                    {{ form.is_default ? $t('global.yes') : $t('global.no') }}
                                </span>
                            </Space>
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('price_lists.is_active')" :help="$t('price_lists.is_active_hint')" /></template>
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
                    :cancel-href="route('business_management.price_lists.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="price_lists.new"
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
