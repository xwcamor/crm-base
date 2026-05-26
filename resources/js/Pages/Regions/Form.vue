<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col,
} from 'ant-design-vue';
import { GlobalOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    region: { type: Object, default: null },  // null = create, object = edit
});

const isEdit = computed(() => !!props.region);

const form = useForm({
    name:      props.region?.name ?? '',
    is_active: props.region?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('system_management.regions.update', props.region.slug));
    } else {
        form.post(route('system_management.regions.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('regions.singular') : $t('regions.new')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('system_management.regions.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('regions.record') : $t('regions.new')"
            :subtitle="isEdit ? region.name : $t('regions.form_create_hint')"
        >
            <template #icon><GlobalOutlined /></template>
        </SectionHeader>

        <!-- Form card -->
        <Card class="form-card" :bodyStyle="{ padding: '24px 28px' }">
            <Form layout="vertical" @submit.prevent="submit">

                <!-- General error banner -->
                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <!-- Layout grid: col-12 (24 en Ant) en mobile, distribuye en
                     desktop. Patrón a clonar para módulos con más campos: cada
                     FormItem en su <Col> con span responsive (xs=24 stack,
                     md=12 dos columnas, lg=8 tres columnas, etc.). -->
                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="isEdit ? 16 : 24">
                        <FormItem
                            required
                            :validate-status="form.errors.name ? 'error' : ''"
                            :help="form.errors.name"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('regions.name')" :help="$t('regions.name_hint')" />
                            </template>
                            <Input
                                v-model:value="form.name"
                                :placeholder="$t('regions.name_placeholder')"
                                size="large"
                                :maxlength="255"
                                showCount
                                autofocus
                            />
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.is_active ? 'error' : ''"
                            :help="form.errors.is_active"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('regions.is_active')" :help="$t('regions.is_active_hint')" />
                            </template>
                            <Space>
                                <Switch v-model:checked="form.is_active" />
                                <span class="state-label">
                                    {{ form.is_active ? $t('global.active') : $t('global.inactive') }}
                                </span>
                            </Space>
                        </FormItem>
                    </Col>
                </Row>

                <!-- Footer actions -->
                <FormFooter
                    :cancel-href="route('system_management.regions.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="regions.new"
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
.form-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--color-border-strong);
}
.mb-4 { margin-bottom: 16px; }

@media (max-width: 768px) {
    .form-footer { flex-direction: column-reverse; }
    .form-footer > * { width: 100%; }
    .form-footer .ant-btn { width: 100%; }
}
</style>
