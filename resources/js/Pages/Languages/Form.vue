<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col,
} from 'ant-design-vue';
import { TranslationOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    language: { type: Object, default: null },  // null = create, object = edit
});

const isEdit = computed(() => !!props.language);

const form = useForm({
    name:      props.language?.name      ?? '',
    iso_code:  props.language?.iso_code  ?? '',
    is_active: props.language?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('system_management.languages.update', props.language.slug));
    } else {
        form.post(route('system_management.languages.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('languages.singular') : $t('languages.new')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('system_management.languages.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('languages.record') : $t('languages.new')"
            :subtitle="isEdit ? language.name : $t('languages.form_create_hint')"
        >
            <template #icon><TranslationOutlined /></template>
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
                <!-- Form layout: stacked en mobile (col-24), 2-col en desktop.
                     Tres campos (name, iso_code, is_active) distribuidos para
                     llenar el ancho sin verse muy alto. -->
                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="isEdit ? 12 : 16">
                        <FormItem
                            required
                            :validate-status="form.errors.name ? 'error' : ''"
                            :help="form.errors.name"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('languages.name')" :help="$t('languages.name_hint')" />
                            </template>
                            <Input
                                v-model:value="form.name"
                                :placeholder="$t('languages.name_placeholder')"
                                size="large"
                                :maxlength="255"
                                showCount
                                autofocus
                            />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="isEdit ? 6 : 8">
                        <FormItem
                            required
                            :validate-status="form.errors.iso_code ? 'error' : ''"
                            :help="form.errors.iso_code || $t('languages.iso_code_help')"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('languages.iso_code')" :help="$t('languages.iso_code_help')" />
                            </template>
                            <Input
                                v-model:value="form.iso_code"
                                :placeholder="$t('languages.iso_code_placeholder')"
                                size="large"
                                :maxlength="10"
                            />
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="24" :md="6">
                        <FormItem
                            :validate-status="form.errors.is_active ? 'error' : ''"
                            :help="form.errors.is_active"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('languages.is_active')" :help="$t('languages.is_active_hint')" />
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
                    :cancel-href="route('system_management.languages.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="languages.new"
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
</style>
