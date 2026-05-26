<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col, Select,
} from 'ant-design-vue';
import { ShopOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    warehouse:      { type: Object, default: null },
    typeOptions:    { type: Array, default: () => [] },
    countryOptions: { type: Array, default: () => [] },
    managerOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.warehouse);

const form = useForm({
    code:            props.warehouse?.code ?? '',
    name:            props.warehouse?.name ?? '',
    description:     props.warehouse?.description ?? '',
    address_line:    props.warehouse?.address_line ?? '',
    city:            props.warehouse?.city ?? '',
    country_id:      props.warehouse?.country_id ?? null,
    type:            props.warehouse?.type ?? 'main',
    is_default:      props.warehouse?.is_default ?? false,
    manager_user_id: props.warehouse?.manager_user_id ?? null,
    is_active:       props.warehouse?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.warehouses.update', props.warehouse.slug));
    } else {
        form.post(route('business_management.warehouses.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('warehouses.edit_title') : $t('warehouses.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.warehouses.index')"
            :title="isEdit ? $t('warehouses.edit_title') : $t('warehouses.create_title')"
            :subtitle="isEdit ? warehouse.name : $t('warehouses.create_subtitle')"
        >
            <template #icon><ShopOutlined /></template>
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
                            <template #label><LabelWithHelp :label="$t('warehouses.code')" :help="$t('warehouses.code_hint')" /></template>
                            <Input v-model:value="form.code" size="large" :maxlength="30" placeholder="WH01 / LIM-CENTRAL" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="12">
                        <FormItem required
                            :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                            <template #label><LabelWithHelp :label="$t('warehouses.name')" :help="$t('warehouses.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="150" :placeholder="$t('warehouses.name_placeholder')" autofocus />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem required
                            :validate-status="form.errors.type ? 'error' : ''">
                            <template #label><LabelWithHelp :label="$t('warehouses.type')" :help="$t('warehouses.type_hint')" /></template>
                            <Select v-model:value="form.type" :options="typeOptions" size="large" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                            <template #label><LabelWithHelp :label="$t('warehouses.description')" :help="$t('warehouses.description_hint')" /></template>
                            <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="500" show-count />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="12">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('warehouses.address_line')" :help="$t('warehouses.address_line_hint')" /></template>
                            <Input v-model:value="form.address_line" size="large" :maxlength="255" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('warehouses.city')" :help="$t('warehouses.city_hint')" /></template>
                            <Input v-model:value="form.city" size="large" :maxlength="100" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('warehouses.country')" :help="$t('warehouses.country_hint')" /></template>
                            <Select v-model:value="form.country_id" :options="countryOptions" size="large" allow-clear show-search
                                :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="12">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('warehouses.manager')" :help="$t('warehouses.manager_hint')" /></template>
                            <Select v-model:value="form.manager_user_id" :options="managerOptions" size="large" allow-clear show-search
                                :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem>
                            <Space><Switch v-model:checked="form.is_default" /><span class="state-label">{{ $t('warehouses.is_default') }}</span></Space>
                        </FormItem>
                    </Col>
                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('warehouses.is_active')" :help="$t('warehouses.is_active_hint')" /></template>
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
                    :cancel-href="route('business_management.warehouses.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="warehouses.new"
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
