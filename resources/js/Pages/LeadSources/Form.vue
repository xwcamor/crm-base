<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col,
} from 'ant-design-vue';
import { CompassOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    leadSource: { type: Object, default: null },
});

const isEdit = computed(() => !!props.leadSource);

const form = useForm({
    name:        props.leadSource?.name ?? '',
    description: props.leadSource?.description ?? '',
    category:    props.leadSource?.category ?? '',
    sort_order:  props.leadSource?.sort_order ?? 0,
    is_active:   props.leadSource?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.lead_sources.update', props.leadSource.slug));
    } else {
        form.post(route('business_management.lead_sources.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('lead_sources.edit_title') : $t('lead_sources.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.lead_sources.index')"
            :title="isEdit ? $t('lead_sources.edit_title') : $t('lead_sources.create_title')"
            :subtitle="isEdit ? leadSource.name : $t('lead_sources.create_subtitle')"
        >
            <template #icon><CompassOutlined /></template>
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
                            <template #label><LabelWithHelp :label="$t('lead_sources.name')" :help="$t('lead_sources.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="120" :placeholder="$t('lead_sources.name_placeholder')" autofocus />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="12">
                        <FormItem
                            :validate-status="form.errors.category ? 'error' : ''" :help="form.errors.category">
                            <template #label><LabelWithHelp :label="$t('lead_sources.category')" :help="$t('lead_sources.category_hint')" /></template>
                            <Input v-model:value="form.category" size="large" :maxlength="60" :placeholder="$t('lead_sources.category_placeholder')" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.sort_order ? 'error' : ''" :help="form.errors.sort_order">
                            <template #label><LabelWithHelp :label="$t('lead_sources.sort_order')" :help="$t('lead_sources.sort_order_hint')" /></template>
                            <InputNumber v-model:value="form.sort_order" :min="0" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                            <template #label><LabelWithHelp :label="$t('lead_sources.description')" :help="$t('lead_sources.description_hint')" /></template>
                            <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="255" show-count />
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('lead_sources.is_active')" :help="$t('lead_sources.is_active_hint')" /></template>
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
                    :cancel-href="route('business_management.lead_sources.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="lead_sources.new"
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
