<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col,
} from 'ant-design-vue';
import { CreditCardOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    method: { type: Object, default: null },
});

const isEdit = computed(() => !!props.method);

const form = useForm({
    name:                 props.method?.name ?? '',
    code:                 props.method?.code ?? '',
    description:          props.method?.description ?? '',
    integration_provider: props.method?.integration_provider ?? '',
    requires_reference:   props.method?.requires_reference ?? false,
    sort_order:           props.method?.sort_order ?? 0,
    is_active:            props.method?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.payment_methods.update', props.method.slug));
    } else {
        form.post(route('business_management.payment_methods.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('payment_methods.edit_title') : $t('payment_methods.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.payment_methods.index')"
            :title="isEdit ? $t('payment_methods.edit_title') : $t('payment_methods.create_title')"
            :subtitle="isEdit ? method.name : $t('payment_methods.create_subtitle')"
        >
            <template #icon><CreditCardOutlined /></template>
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
                            <template #label><LabelWithHelp :label="$t('payment_methods.name')" :help="$t('payment_methods.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="100" :placeholder="$t('payment_methods.name_placeholder')" autofocus />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="12">
                        <FormItem
                            :validate-status="form.errors.code ? 'error' : ''" :help="form.errors.code">
                            <template #label><LabelWithHelp :label="$t('payment_methods.code')" :help="$t('payment_methods.code_hint')" /></template>
                            <Input v-model:value="form.code" size="large" :maxlength="30" placeholder="transfer / card / cash" />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="12">
                        <FormItem
                            :validate-status="form.errors.integration_provider ? 'error' : ''" :help="form.errors.integration_provider">
                            <template #label><LabelWithHelp :label="$t('payment_methods.integration_provider')" :help="$t('payment_methods.integration_provider_hint')" /></template>
                            <Input v-model:value="form.integration_provider" size="large" :maxlength="60" placeholder="stripe / mercadopago / paypal" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.sort_order ? 'error' : ''" :help="form.errors.sort_order">
                            <template #label><LabelWithHelp :label="$t('payment_methods.sort_order')" :help="$t('payment_methods.sort_order_hint')" /></template>
                            <InputNumber v-model:value="form.sort_order" :min="0" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                            <template #label><LabelWithHelp :label="$t('payment_methods.description')" :help="$t('payment_methods.description_hint')" /></template>
                            <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="500" show-count />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('payment_methods.requires_reference')" :help="$t('payment_methods.requires_reference_hint')" /></template>
                            <Space>
                                <Switch v-model:checked="form.requires_reference" />
                                <span class="state-label">
                                    {{ form.requires_reference ? $t('global.yes') : $t('global.no') }}
                                </span>
                            </Space>
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('payment_methods.is_active')" :help="$t('payment_methods.is_active_hint')" /></template>
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
                    :cancel-href="route('business_management.payment_methods.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="payment_methods.new"
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
