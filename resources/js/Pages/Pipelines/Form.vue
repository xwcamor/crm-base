<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col,
} from 'ant-design-vue';
import { FunnelPlotOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    pipeline: { type: Object, default: null },
});

const isEdit = computed(() => !!props.pipeline);

const form = useForm({
    name:        props.pipeline?.name ?? '',
    description: props.pipeline?.description ?? '',
    color:       props.pipeline?.color ?? '#1677ff',
    is_default:  props.pipeline?.is_default ?? false,
    sort_order:  props.pipeline?.sort_order ?? 0,
    is_active:   props.pipeline?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('crm.pipelines.update', props.pipeline.slug));
    } else {
        form.post(route('crm.pipelines.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('pipelines.edit_title') : $t('pipelines.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('crm.pipelines.index')"
            :title="isEdit ? $t('pipelines.edit_title') : $t('pipelines.create_title')"
            :subtitle="isEdit ? pipeline.name : $t('pipelines.create_subtitle')"
        >
            <template #icon><FunnelPlotOutlined /></template>
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
                            <template #label><LabelWithHelp :label="$t('pipelines.name')" :help="$t('pipelines.name_hint')" /></template>
                            <Input v-model:value="form.name" size="large" :maxlength="150" showCount autofocus
                                :placeholder="$t('pipelines.name_placeholder')" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="5">
                        <FormItem
                            :validate-status="form.errors.color ? 'error' : ''" :help="form.errors.color">
                            <template #label><LabelWithHelp :label="$t('pipelines.color')" :help="$t('pipelines.color_hint')" /></template>
                            <div class="color-input-wrap">
                                <input type="color" v-model="form.color" class="native-color" />
                                <Input v-model:value="form.color" size="large" :maxlength="7" :placeholder="$t('pipelines.color_placeholder')" />
                            </div>
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="5">
                        <FormItem
                            :validate-status="form.errors.sort_order ? 'error' : ''" :help="form.errors.sort_order">
                            <template #label><LabelWithHelp :label="$t('pipelines.sort_order')" :help="$t('pipelines.sort_order_hint')" /></template>
                            <InputNumber v-model:value="form.sort_order" :min="0" :step="1" size="large" style="width:100%" />
                        </FormItem>
                    </Col>

                    <Col :xs="24">
                        <FormItem
                            :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                            <template #label><LabelWithHelp :label="$t('pipelines.description')" :help="$t('pipelines.description_hint')" /></template>
                            <Input.TextArea v-model:value="form.description" :rows="3" :maxlength="500" show-count
                                :placeholder="$t('pipelines.description_placeholder')" />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="8">
                        <FormItem>
                            <template #label><LabelWithHelp :label="$t('pipelines.is_default')" :help="$t('pipelines.is_default_hint')" /></template>
                            <Space>
                                <Switch v-model:checked="form.is_default" />
                                <span class="state-label">{{ form.is_default ? $t('global.yes') : $t('global.no') }}</span>
                            </Space>
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="12" :md="8">
                        <FormItem
                            :validate-status="form.errors.is_active ? 'error' : ''" :help="form.errors.is_active">
                            <template #label><LabelWithHelp :label="$t('pipelines.is_active')" :help="$t('pipelines.is_active_hint')" /></template>
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
                    :cancel-href="route('crm.pipelines.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="pipelines.new"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.form-card { border-radius: 6px; }
.state-label {
    font-size: 0.875rem;
    color: var(--color-text);
    font-weight: 500;
}
.mb-4 { margin-bottom: 16px; }

.color-input-wrap {
    display: flex;
    align-items: stretch;
    gap: 4px;
}
.native-color {
    width: 44px;
    height: 40px;
    border: 1px solid var(--color-border, #d9d9d9);
    border-radius: 6px;
    cursor: pointer;
    background: transparent;
    padding: 2px;
    flex-shrink: 0;
}
</style>
