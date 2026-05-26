<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Alert, Row, Col, Divider, Tag, Modal,
    Select, SelectOption, Space,
} from 'ant-design-vue';
import { ExclamationCircleOutlined, SafetyCertificateOutlined } from '@ant-design/icons-vue';
import { h } from 'vue';
import { useI18n } from '@/Plugins/i18n';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
import { PLAN_ICONS, PLAN_COLORS, resolveIconComponent } from '@/Utils/planAppearance';

defineOptions({ layout: AppLayout });

const { t } = useI18n();

const props = defineProps({
    plan:          { type: Object, default: null },        // null = create, object = edit
    featureKeys:   { type: Array,  default: () => [] },
    tenants_count: { type: Number, default: 0 },
});

const isEdit = computed(() => !!props.plan);

// Niveles de soporte — enum fijo de dominio (3 tiers), no es una lista
// escalable como los planes. Analogo a los payment methods.
const SUPPORT_LEVELS = computed(() => [
    { value: 'community', label: t('plans.support_community') },
    { value: 'email',     label: t('plans.support_email') },
    { value: 'priority',  label: t('plans.support_priority') },
]);

// Inicializo features con TODOS los keys (los faltantes en DB → false).
const initialFeatures = props.featureKeys.reduce((acc, k) => {
    acc[k] = !!(props.plan?.features?.[k]);
    return acc;
}, {});

const form = useForm({
    slug:                   props.plan?.slug ?? '',
    name:                   props.plan?.name ?? '',
    tagline:                props.plan?.tagline ?? '',
    icon:                   props.plan?.icon ?? '',
    color:                  props.plan?.color ?? 'default',
    max_users:              props.plan?.max_users ?? 1,
    max_records_per_module: props.plan?.max_records_per_module ?? 100,
    export_rate_limit:      props.plan?.export_rate_limit ?? 1,
    support_level:          props.plan?.support_level ?? 'community',
    price_monthly:          Number(props.plan?.price_monthly ?? 0),
    price_yearly:           Number(props.plan?.price_yearly ?? 0),
    currency:               props.plan?.currency ?? 'USD',
    is_active:              props.plan?.is_active ?? true,
    is_public:              props.plan?.is_public ?? true,
    features:               initialFeatures,
});

// Preview en vivo del icono+color elegido para que el admin vea cómo va a
// renderizarse el plan antes de guardar.
const previewIconComponent = computed(() => resolveIconComponent(form.icon));

// Agrupo las features para layout (visual). IMPORTANTE: todas las keys de
// PlanController::featureKeys() DEBEN aparecer en algún grupo — sino el toggle
// no se renderiza y el plan queda incoherente. El catch-all de abajo garantiza
// que cualquier key nueva sin grupo asignado igual se muestre.
const featureGroups = computed(() => {
    const groups = [
        {
            title: t('plans.group_exports'),
            keys:  ['export_csv', 'export_excel', 'export_pdf', 'export_word', 'branded_exports'],
        },
        {
            title: t('plans.group_team'),
            keys:  ['team_management', 'bulk_operations', 'imports', 'edit_all'],
        },
        {
            title: t('plans.group_visibility'),
            keys:  ['audit_log_view', 'saved_views'],
        },
        {
            title: t('plans.group_advanced'),
            keys:  ['api_access', 'automations', 'scheduled_exports',
                    'export_webhook_delivery', 'export_email_delivery'],
        },
        {
            title: t('plans.group_quality'),
            keys:  ['extended_retention', 'higher_export_rate_limit'],
        },
    ];

    // Catch-all: cualquier featureKey que no quedó en un grupo se muestra acá.
    // Garantiza que el Plans Form sea SSOT real — ninguna feature queda oculta.
    const grouped = new Set(groups.flatMap(g => g.keys));
    const orphans = props.featureKeys.filter(k => !grouped.has(k));
    if (orphans.length) {
        groups.push({ title: t('plans.group_other'), keys: orphans });
    }

    return groups;
});

const doSubmit = () => {
    if (isEdit.value) {
        form.put(route('system_management.plans.update', props.plan.id));
    } else {
        form.post(route('system_management.plans.store'));
    }
};

// Si en edit está desactivando un plan con tenants activos → confirm modal.
const submit = () => {
    if (isEdit.value) {
        const goingInactive = props.plan.is_active && !form.is_active;
        if (goingInactive && props.tenants_count > 0) {
            Modal.confirm({
                title: t('plans.deactivate_warning_title'),
                icon: h(ExclamationCircleOutlined),
                content: t('plans.deactivate_warning_desc', { count: props.tenants_count }),
                okText: t('global.continue'),
                okButtonProps: { danger: true },
                cancelText: t('global.cancel'),
                onOk: doSubmit,
            });
            return;
        }
    }
    doSubmit();
};
</script>

<template>
    <Head :title="isEdit ? $t('plans.edit_title', { name: plan.name }) : $t('plans.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('system_management.plans.index')"
            :title="isEdit ? $t('plans.edit_title', { name: plan.name }) : $t('plans.create_title')"
            :subtitle="isEdit ? $t('plans.edit_subtitle') : $t('plans.create_subtitle')"
        >
            <template #icon><SafetyCertificateOutlined /></template>
        </SectionHeader>

        <Alert
            type="info"
            show-icon
            class="mb-3"
            :message="isEdit ? $t('plans.edit_info') : $t('plans.create_info')"
        />

        <Card :bodyStyle="{ padding: '24px 28px' }" class="form-card">
            <Form layout="vertical" @submit.prevent="submit">
                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error"
                    show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <!-- Identidad del plan -->
                <Divider orientation="left" plain>
                    <strong>{{ $t('plans.section_identity') }}</strong>
                </Divider>

                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="6">
                        <FormItem
                            :required="!isEdit"
                            :validate-status="form.errors.slug ? 'error' : ''"
                            :help="form.errors.slug || $t(isEdit ? 'plans.slug_locked_hint' : 'plans.slug_create_hint')"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.slug')" :help="$t('plans.slug_help')" />
                            </template>
                            <Input
                                v-model:value="form.slug"
                                :disabled="isEdit"
                                :placeholder="$t('plans.slug_placeholder')"
                                size="large"
                                :maxlength="60"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="10">
                        <FormItem
                            required
                            :validate-status="form.errors.name ? 'error' : ''"
                            :help="form.errors.name"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.name')" :help="$t('plans.name_hint')" />
                            </template>
                            <Input v-model:value="form.name" size="large" :maxlength="100" />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.tagline ? 'error' : ''"
                            :help="form.errors.tagline"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.tagline')" :help="$t('plans.tagline_hint')" />
                            </template>
                            <Input v-model:value="form.tagline" size="large" :maxlength="200" />
                        </FormItem>
                    </Col>
                </Row>

                <!-- Apariencia: icono + color + preview en vivo -->
                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="10">
                        <FormItem
                            :validate-status="form.errors.icon ? 'error' : ''"
                            :help="form.errors.icon || $t('plans.icon_hint')"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.icon')" :help="$t('plans.icon_hint')" />
                            </template>
                            <Select
                                v-model:value="form.icon"
                                size="large"
                                allow-clear
                                :placeholder="$t('plans.icon_placeholder')"
                            >
                                <SelectOption v-for="icon in PLAN_ICONS" :key="icon.value" :value="icon.value">
                                    <Space>
                                        <component :is="icon.component" />
                                        <span>{{ icon.label }}</span>
                                    </Space>
                                </SelectOption>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.color ? 'error' : ''"
                            :help="form.errors.color"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.color')" :help="$t('plans.color_hint')" />
                            </template>
                            <Select v-model:value="form.color" size="large">
                                <SelectOption v-for="c in PLAN_COLORS" :key="c.value" :value="c.value">
                                    <Space>
                                        <span class="color-swatch" :style="{ background: c.swatch }" />
                                        <span>{{ c.label }}</span>
                                    </Space>
                                </SelectOption>
                            </Select>
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="6">
                        <FormItem :label="$t('plans.preview')">
                            <Tag :color="form.color || 'default'" :bordered="false" class="preview-tag">
                                <component :is="previewIconComponent" v-if="previewIconComponent" />
                                {{ (form.name || $t('plans.preview_placeholder')).toUpperCase() }}
                            </Tag>
                        </FormItem>
                    </Col>
                </Row>

                <!-- Límites numéricos -->
                <Divider orientation="left" plain>
                    <strong>{{ $t('plans.section_limits') }}</strong>
                </Divider>
                <p class="hint">{{ $t('plans.limits_hint') }}</p>

                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="8">
                        <FormItem
                            required
                            :validate-status="form.errors.max_users ? 'error' : ''"
                            :help="form.errors.max_users || $t('plans.unlimited_hint')"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.max_users')" :help="$t('plans.unlimited_hint')" />
                            </template>
                            <InputNumber
                                v-model:value="form.max_users"
                                :min="-1"
                                size="large"
                                style="width: 100%"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            required
                            :validate-status="form.errors.max_records_per_module ? 'error' : ''"
                            :help="form.errors.max_records_per_module || $t('plans.unlimited_hint')"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.max_records_per_module')" :help="$t('plans.unlimited_hint')" />
                            </template>
                            <InputNumber
                                v-model:value="form.max_records_per_module"
                                :min="-1"
                                size="large"
                                style="width: 100%"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            required
                            :validate-status="form.errors.export_rate_limit ? 'error' : ''"
                            :help="form.errors.export_rate_limit || $t('plans.export_rate_hint')"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.export_rate_limit')" :help="$t('plans.export_rate_hint')" />
                            </template>
                            <InputNumber
                                v-model:value="form.export_rate_limit"
                                :min="1" :max="10000"
                                size="large"
                                style="width: 100%"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            required
                            :validate-status="form.errors.support_level ? 'error' : ''"
                            :help="form.errors.support_level || $t('plans.support_level_hint')"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.support_level')" :help="$t('plans.support_level_hint')" />
                            </template>
                            <Select v-model:value="form.support_level" size="large">
                                <SelectOption
                                    v-for="lvl in SUPPORT_LEVELS"
                                    :key="lvl.value"
                                    :value="lvl.value"
                                >
                                    {{ lvl.label }}
                                </SelectOption>
                            </Select>
                        </FormItem>
                    </Col>
                </Row>

                <!-- Pricing -->
                <Divider orientation="left" plain>
                    <strong>{{ $t('plans.section_pricing') }}</strong>
                </Divider>

                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.price_monthly ? 'error' : ''"
                            :help="form.errors.price_monthly"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.price_monthly')" :help="$t('plans.price_monthly_hint')" />
                            </template>
                            <InputNumber
                                v-model:value="form.price_monthly"
                                :min="0" :step="0.01" :precision="2"
                                size="large"
                                style="width: 100%"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.price_yearly ? 'error' : ''"
                            :help="form.errors.price_yearly"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.price_yearly')" :help="$t('plans.price_yearly_hint')" />
                            </template>
                            <InputNumber
                                v-model:value="form.price_yearly"
                                :min="0" :step="0.01" :precision="2"
                                size="large"
                                style="width: 100%"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="8">
                        <FormItem
                            required
                            :validate-status="form.errors.currency ? 'error' : ''"
                            :help="form.errors.currency"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('plans.currency')" :help="$t('plans.currency_hint')" />
                            </template>
                            <Input
                                v-model:value="form.currency"
                                size="large"
                                :maxlength="3"
                                style="text-transform: uppercase;"
                            />
                        </FormItem>
                    </Col>
                </Row>

                <!-- Features bool -->
                <Divider orientation="left" plain>
                    <strong>{{ $t('plans.section_features') }}</strong>
                </Divider>
                <p class="hint">{{ $t('plans.features_hint') }}</p>

                <div v-for="g in featureGroups" :key="g.title" class="feature-group">
                    <div class="feature-group__title">{{ g.title }}</div>
                    <Row :gutter="[16, 8]">
                        <Col v-for="key in g.keys" :key="key" :xs="24" :md="12" :lg="8">
                            <div class="feature-row">
                                <Switch v-model:checked="form.features[key]" />
                                <span class="feature-label">{{ $t(`plans.feature_${key.replace(/_(.)/g, (_, c) => c.toUpperCase())}`) || key }}</span>
                            </div>
                        </Col>
                    </Row>
                </div>

                <!-- Activo / Público -->
                <Divider orientation="left" plain>
                    <strong>{{ $t('plans.section_visibility') }}</strong>
                </Divider>

                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="12">
                        <FormItem>
                            <template #label>
                                <LabelWithHelp :label="$t('plans.is_active')" :help="$t('plans.is_active_hint')" />
                            </template>
                            <Switch v-model:checked="form.is_active" />
                            <p class="hint">{{ $t('plans.is_active_hint') }}</p>
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="12">
                        <FormItem>
                            <template #label>
                                <LabelWithHelp :label="$t('plans.is_public')" :help="$t('plans.is_public_hint')" />
                            </template>
                            <Switch v-model:checked="form.is_public" />
                            <p class="hint">{{ $t('plans.is_public_hint') }}</p>
                        </FormItem>
                    </Col>
                </Row>

                <FormFooter
                    :cancel-href="route('system_management.plans.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="plans.create"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.form-card { border-radius: 6px; }
.mb-3 { margin-bottom: 12px; }
.mb-4 { margin-bottom: 16px; }
.hint { font-size: 0.8125rem; color: var(--color-text-muted); margin: 4px 0 12px 0; line-height: 1.4; }

.slug-label { display: inline-flex; align-items: center; gap: 5px; }
.slug-label__help {
    color: var(--color-text-muted);
    font-size: 0.8rem;
    cursor: help;
}
.slug-label__help:hover { color: var(--color-primary); }

.color-swatch {
    display: inline-block;
    width: 14px;
    height: 14px;
    border-radius: 3px;
    border: 1px solid rgba(0, 0, 0, 0.1);
    vertical-align: middle;
}
.preview-tag {
    font-weight: 600;
    letter-spacing: 0.3px;
    padding: 4px 10px;
    font-size: 0.9rem;
}

.feature-group { margin-bottom: 18px; }
.feature-group__title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-text-strong);
    margin-bottom: 8px;
}
.feature-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 6px 0;
}
.feature-label { font-size: 0.875rem; }
</style>
