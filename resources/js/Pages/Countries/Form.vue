<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Space, Alert, Row, Col, Select,
} from 'ant-design-vue';
import { GlobalOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    country:       { type: Object, default: null },
    regionOptions: { type: Array, default: () => [] },
    localeOptions: { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.country);

const form = useForm({
    name:              props.country?.name ?? '',
    iso_code:          props.country?.iso_code ?? '',
    currency:          props.country?.currency ?? '',
    timezone:          props.country?.timezone ?? '',
    region_id:         props.country?.region_id ?? null,
    default_locale_id: props.country?.default_locale_id ?? null,
    is_active:         props.country?.is_active ?? true,
});

// Lista corta de timezones — el backend valida contra DateTimeZone::listIdentifiers() así
// que cualquier valor IANA pega. Mostramos los más comunes en el dropdown y permitimos
// custom para casos raros (mode="combobox").
const COMMON_TIMEZONES = [
    'UTC',
    'America/Lima', 'America/Caracas', 'America/Bogota', 'America/Sao_Paulo',
    'America/Santiago', 'America/Argentina/Buenos_Aires', 'America/Mexico_City',
    'America/New_York', 'America/Los_Angeles',
    'Europe/Madrid', 'Europe/London', 'Europe/Berlin', 'Europe/Paris', 'Europe/Rome',
    'Asia/Tokyo', 'Asia/Shanghai', 'Asia/Dubai', 'Asia/Kolkata',
    'Australia/Sydney', 'Pacific/Auckland',
];

const tzOptions = computed(() =>
    COMMON_TIMEZONES.map(tz => ({ value: tz, label: tz }))
);

const submit = () => {
    if (isEdit.value) {
        form.put(route('system_management.countries.update', props.country.slug));
    } else {
        form.post(route('system_management.countries.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('global.edit') + ' — ' + $t('countries.singular') : $t('countries.new')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('system_management.countries.index')"
            :title="isEdit ? $t('global.edit') + ' ' + $t('countries.record') : $t('countries.new')"
            :subtitle="isEdit ? country.name : $t('countries.form_create_hint')"
        >
            <template #icon><GlobalOutlined /></template>
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
                    <Col :xs="24" :md="12">
                        <FormItem
                            required
                            :validate-status="form.errors.name ? 'error' : ''"
                            :help="form.errors.name"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('countries.name')" :help="$t('countries.name_hint')" />
                            </template>
                            <Input
                                v-model:value="form.name"
                                :placeholder="$t('countries.name_placeholder')"
                                size="large"
                                :maxlength="255"
                                showCount
                                autofocus
                            />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            required
                            :validate-status="form.errors.iso_code ? 'error' : ''"
                            :help="form.errors.iso_code"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('countries.iso_code')" :help="$t('countries.iso_code_hint')" />
                            </template>
                            <Input
                                v-model:value="form.iso_code"
                                :placeholder="$t('countries.iso_code_placeholder')"
                                size="large"
                                :maxlength="2"
                                style="text-transform: uppercase;"
                            />
                        </FormItem>
                    </Col>

                    <Col :xs="12" :md="6">
                        <FormItem
                            required
                            :validate-status="form.errors.currency ? 'error' : ''"
                            :help="form.errors.currency"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('countries.currency')" :help="$t('countries.currency_hint')" />
                            </template>
                            <Input
                                v-model:value="form.currency"
                                :placeholder="$t('countries.currency_placeholder')"
                                size="large"
                                :maxlength="3"
                                style="text-transform: uppercase;"
                            />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="12">
                        <FormItem
                            required
                            :validate-status="form.errors.region_id ? 'error' : ''"
                            :help="form.errors.region_id"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('countries.region')" :help="$t('countries.region_hint')" />
                            </template>
                            <Select
                                v-model:value="form.region_id"
                                :options="regionOptions"
                                :placeholder="$t('countries.region_placeholder')"
                                size="large"
                                show-search
                                option-filter-prop="label"
                            />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="12">
                        <FormItem
                            required
                            :validate-status="form.errors.default_locale_id ? 'error' : ''"
                            :help="form.errors.default_locale_id"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('countries.default_locale')" :help="$t('countries.default_locale_hint')" />
                            </template>
                            <Select
                                v-model:value="form.default_locale_id"
                                :options="localeOptions"
                                :placeholder="$t('countries.default_locale_placeholder')"
                                size="large"
                                show-search
                                option-filter-prop="label"
                            />
                        </FormItem>
                    </Col>

                    <Col :xs="24" :md="isEdit ? 16 : 24">
                        <FormItem
                            required
                            :validate-status="form.errors.timezone ? 'error' : ''"
                            :help="form.errors.timezone"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('countries.timezone')" :help="$t('countries.timezone_hint')" />
                            </template>
                            <Select
                                v-model:value="form.timezone"
                                :options="tzOptions"
                                :placeholder="$t('countries.timezone_placeholder')"
                                size="large"
                                show-search
                                option-filter-prop="label"
                            />
                        </FormItem>
                    </Col>

                    <Col v-if="isEdit" :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.is_active ? 'error' : ''"
                            :help="form.errors.is_active"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('countries.is_active')" :help="$t('countries.is_active_hint')" />
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
                    :cancel-href="route('system_management.countries.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="countries.new"
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
