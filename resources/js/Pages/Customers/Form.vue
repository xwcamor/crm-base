<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col, Select,
} from 'ant-design-vue';
import { UserOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    customer:       { type: Object, default: null },
    countryOptions: { type: Array,  default: () => [] }, // @scaffold:remove-line
});

const isEdit = computed(() => !!props.customer);

const form = useForm({
    name:       props.customer?.name ?? '',
    // @scaffold:anchor description-useform
    // @scaffold:remove-begin commercial-useform
    cod:        props.customer?.cod ?? '',
    country_id: props.customer?.country_id ?? null,
    // @scaffold:remove-end
    is_active:  props.customer?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('business_management.customers.update', props.customer.slug));
    } else {
        form.post(route('business_management.customers.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('customers.edit_title') : $t('customers.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('business_management.customers.index')"
            :title="isEdit ? $t('customers.edit_title') : $t('customers.create_title')"
            :subtitle="isEdit ? customer.name : $t('customers.create_subtitle')"
        >
            <template #icon><UserOutlined /></template>
        </SectionHeader>

        <Card class="form-card" :bodyStyle="{ padding: '24px 28px' }">
            <Form layout="vertical" @submit.prevent="submit">

                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="16">
                        <FormItem
                            required
                            :validate-status="form.errors.name ? 'error' : ''"
                            :help="form.errors.name"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('customers.name')" :help="$t('customers.name_hint')" />
                            </template>
                            <Input
                                v-model:value="form.name"
                                size="large"
                                :maxlength="255"
                                showCount
                                autofocus
                            />
                        </FormItem>
                    </Col>

                    <!-- @scaffold:anchor description-formitem -->
                    <!-- @scaffold:remove-begin commercial-formitems -->
                    <Col :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.cod ? 'error' : ''"
                            :help="form.errors.cod"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('customers.cod')" :help="$t('customers.cod_hint')" />
                            </template>
                            <Input
                                v-model:value="form.cod"
                                size="large"
                                :maxlength="50"
                                :placeholder="$t('customers.cod_placeholder')"
                            />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="16">
                        <FormItem
                            :validate-status="form.errors.country_id ? 'error' : ''"
                            :help="form.errors.country_id"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('customers.country')" :help="$t('customers.country_hint')" />
                            </template>
                            <Select
                                v-model:value="form.country_id"
                                :options="countryOptions"
                                :placeholder="$t('customers.country_placeholder')"
                                size="large"
                                allow-clear
                                show-search
                                :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())"
                            />
                        </FormItem>
                    </Col>
                    <!-- @scaffold:remove-end -->

                    <Col v-if="isEdit" :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.is_active ? 'error' : ''"
                            :help="form.errors.is_active"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('customers.is_active')" :help="$t('customers.is_active_hint')" />
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

                <FormFooter
                    :cancel-href="route('business_management.customers.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="customers.new"
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
