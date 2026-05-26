<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select,
} from 'ant-design-vue';
import { TagsOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    category:      { type: Object, default: null },
    parentOptions: { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.category);

const form = useForm({
    name:        props.category?.name ?? '',
    description: props.category?.description ?? '',
    parent_id:   props.category?.parent_id ?? null,
    sort_order:  props.category?.sort_order ?? 0,
    is_active:   props.category?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.product_categories.update', props.category.slug));
    } else {
        form.post(route('business_management.product_categories.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('product_categories.edit_title') : $t('product_categories.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.product_categories.index')"
            :title="isEdit ? $t('product_categories.edit_title') : $t('product_categories.create_title')"
            :subtitle="isEdit ? category.name : $t('product_categories.create_subtitle')"
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
                            <template #label><LabelWithHelp :label="$t('product_categories.name')" :help="$t('product_categories.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="150" :placeholder="$t('product_categories.name_placeholder')" autofocus />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="12">
                        <FormItem
                            :validate-status="form.errors.parent_id ? 'error' : ''" :help="form.errors.parent_id">
                            <template #label><LabelWithHelp :label="$t('product_categories.parent')" :help="$t('product_categories.parent_hint')" /></template>
                            <Select
                                v-model:value="form.parent_id"
                                :options="parentOptions"
                                size="large"
                                allow-clear
                                show-search
                                :filter-option="(i, o) => (o.label ?? '').toLowerCase().includes(i.toLowerCase())"
                                :placeholder="$t('product_categories.parent_hint')"
                            />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            :validate-status="form.errors.sort_order ? 'error' : ''" :help="form.errors.sort_order">
                            <template #label><LabelWithHelp :label="$t('product_categories.sort_order')" :help="$t('product_categories.sort_order_hint')" /></template>
                            <InputNumber v-model:value="form.sort_order" :min="0" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                            <template #label><LabelWithHelp :label="$t('product_categories.description')" :help="$t('product_categories.description_hint')" /></template>
                            <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="500" show-count />
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('product_categories.is_active')" :help="$t('product_categories.is_active_hint')" /></template>
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
                    :cancel-href="route('business_management.product_categories.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="product_categories.new"
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
