<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select, DatePicker,
} from 'ant-design-vue';
import { SwapOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    rate:            { type: Object, default: null },
    currencyOptions: { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.rate);

const toDate = (v) => v ? dayjs(v) : null;

const form = useForm({
    base_code:  props.rate?.base_code  ?? null,
    quote_code: props.rate?.quote_code ?? null,
    rate:       props.rate?.rate       ?? null,
    valid_at:   toDate(props.rate?.valid_at) ?? dayjs(),
    source:     props.rate?.source     ?? 'manual',
    is_active:  props.rate?.is_active  ?? true,
});

const submit = () => {
    const payload = {
        ...form.data(),
        valid_at: form.valid_at ? form.valid_at.format('YYYY-MM-DD HH:mm:ss') : null,
    };
    if (isEdit.value) {
        form.transform(() => payload).put(route('business_management.exchange_rates.update', props.rate.slug));
    } else {
        form.transform(() => payload).post(route('business_management.exchange_rates.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('exchange_rates.edit_title') : $t('exchange_rates.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.exchange_rates.index')"
            :title="isEdit ? $t('exchange_rates.edit_title') : $t('exchange_rates.create_title')"
            :subtitle="isEdit ? `${rate.base_code} / ${rate.quote_code}` : $t('exchange_rates.create_subtitle')"
        >
            <template #icon><SwapOutlined /></template>
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
                            :validate-status="form.errors.base_code ? 'error' : ''" :help="form.errors.base_code">
                            <template #label><LabelWithHelp :label="$t('exchange_rates.base_code')" :help="$t('exchange_rates.base_code_hint')" /></template>
                            <Select
                                v-model:value="form.base_code"
                                :options="currencyOptions"
                                size="large"
                                show-search
                                :filter-option="(i, o) => (o.label ?? '').toLowerCase().includes(i.toLowerCase())"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem required
                            :validate-status="form.errors.quote_code ? 'error' : ''" :help="form.errors.quote_code">
                            <template #label><LabelWithHelp :label="$t('exchange_rates.quote_code')" :help="$t('exchange_rates.quote_code_hint')" /></template>
                            <Select
                                v-model:value="form.quote_code"
                                :options="currencyOptions"
                                size="large"
                                show-search
                                :filter-option="(i, o) => (o.label ?? '').toLowerCase().includes(i.toLowerCase())"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem required
                            :validate-status="form.errors.rate ? 'error' : ''" :help="form.errors.rate">
                            <template #label><LabelWithHelp :label="$t('exchange_rates.rate')" :help="$t('exchange_rates.rate_hint')" /></template>
                            <InputNumber v-model:value="form.rate" :min="0" :step="0.000001" size="large" style="width:100%" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem required
                            :validate-status="form.errors.valid_at ? 'error' : ''" :help="form.errors.valid_at">
                            <template #label><LabelWithHelp :label="$t('exchange_rates.valid_at')" :help="$t('exchange_rates.valid_at_hint')" /></template>
                            <DatePicker v-model:value="form.valid_at" show-time size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="12">
                        <FormItem
                            :validate-status="form.errors.source ? 'error' : ''" :help="form.errors.source">
                            <template #label><LabelWithHelp :label="$t('exchange_rates.source')" :help="$t('exchange_rates.source_hint')" /></template>
                            <Input v-model:value="form.source" size="large" :maxlength="60" placeholder="manual / fixer.io / bcra ..." />
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('exchange_rates.is_active')" :help="$t('exchange_rates.is_active_hint')" /></template>
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
                    :cancel-href="route('business_management.exchange_rates.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="exchange_rates.new"
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
