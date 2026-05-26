<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col,
} from 'ant-design-vue';
import { CalculatorOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    taxClass: { type: Object, default: null },
});

const isEdit = computed(() => !!props.taxClass);

const form = useForm({
    name:        props.taxClass?.name ?? '',
    code:        props.taxClass?.code ?? '',
    description: props.taxClass?.description ?? '',
    is_default:  props.taxClass?.is_default ?? false,
    is_active:   props.taxClass?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.tax_classes.update', props.taxClass.slug));
    } else {
        form.post(route('business_management.tax_classes.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('tax_classes.edit_title') : $t('tax_classes.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.tax_classes.index')"
            :title="isEdit ? $t('tax_classes.edit_title') : $t('tax_classes.create_title')"
            :subtitle="isEdit ? taxClass.name : $t('tax_classes.create_subtitle')"
        >
            <template #icon><CalculatorOutlined /></template>
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
                    <Col :xs="24" :md="14">
                        <FormItem required
                            :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                            <template #label><LabelWithHelp :label="$t('tax_classes.name')" :help="$t('tax_classes.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="100" :placeholder="$t('tax_classes.name_placeholder')" autofocus />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="10">
                        <FormItem
                            :validate-status="form.errors.code ? 'error' : ''" :help="form.errors.code">
                            <template #label><LabelWithHelp :label="$t('tax_classes.code')" :help="$t('tax_classes.code_hint')" /></template>
                            <Input v-model:value="form.code" size="large" :maxlength="30" :placeholder="$t('tax_classes.code_placeholder')" />
                        </FormItem>
                    </Col>
                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                            <template #label><LabelWithHelp :label="$t('tax_classes.description')" :help="$t('tax_classes.description_hint')" /></template>
                            <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="255" show-count />
                        </FormItem>
                    </Col>
                    <Col :xs="12" :md="6">
                        <FormItem>
                            <Space>
                                <Switch v-model:checked="form.is_default" />
                                <span class="state-label">{{ $t('tax_classes.is_default') }}</span>
                            </Space>
                        </FormItem>
                    </Col>
                    <Col v-if="isEdit" :xs="12" :md="6">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('tax_classes.is_active')" :help="$t('tax_classes.is_active_hint')" /></template>
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
                    :cancel-href="route('business_management.tax_classes.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="tax_classes.new"
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
