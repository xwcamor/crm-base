<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select, Tag, Avatar, Anchor,
} from 'ant-design-vue';
import {
    BankOutlined, IdcardOutlined, TagOutlined, DollarOutlined,
    MailOutlined, GlobalOutlined, SettingOutlined, FlagOutlined,
    SafetyCertificateOutlined, FileTextOutlined, TeamOutlined,
    WarningOutlined, CloseCircleOutlined, ArrowUpOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    company:              { type: Object, default: null },
    countryOptions:       { type: Array, default: () => [] },
    industryOptions:      { type: Array, default: () => [] },
    ownerOptions:         { type: Array, default: () => [] },
    typeOptions:          { type: Array, default: () => [] },
    stageOptions:         { type: Array, default: () => [] },
    currencyOptions:      { type: Array, default: () => [] },
    languageOptions:      { type: Array, default: () => [] },
    ratingOptions:        { type: Array, default: () => [] },
    taxStatusOptions:     { type: Array, default: () => [] },
    priorityOptions:      { type: Array, default: () => [] },
    accountStatusOptions: { type: Array, default: () => [] },
    churnRiskOptions:     { type: Array, default: () => [] },
    legalEntityOptions:   { type: Array, default: () => [] },
    paymentMethodOptions: { type: Array, default: () => [] },
    referrerOptions:      { type: Array, default: () => [] },
    defaultCurrencyCode:  { type: String, default: null },
});

const isEdit = computed(() => !!props.company);

const form = useForm({
    name:              props.company?.name ?? '',
    legal_name:        props.company?.legal_name ?? '',
    tax_id:            props.company?.tax_id ?? '',
    tax_status:        props.company?.tax_status ?? null,
    description:       props.company?.description ?? '',
    company_type:      props.company?.company_type ?? 'prospect',
    lifecycle_stage:   props.company?.lifecycle_stage ?? 'lead',
    rating:            props.company?.rating ?? 'none',
    score:             props.company?.score ?? 0,
    country_id:        props.company?.country_id ?? null,
    industry_id:       props.company?.industry_id ?? null,
    owner_id:          props.company?.owner_id ?? null,
    website:           props.company?.website ?? '',
    annual_revenue:    props.company?.annual_revenue ?? null,
    employee_count:    props.company?.employee_count ?? null,
    founded_year:      props.company?.founded_year ?? null,
    external_id:       props.company?.external_id ?? '',

    preferred_currency_code: props.company?.preferred_currency_code ?? props.defaultCurrencyCode,
    payment_terms_days:      props.company?.payment_terms_days ?? 30,
    credit_limit:            props.company?.credit_limit ?? null,

    preferred_language_id: props.company?.preferred_language_id ?? null,
    billing_email:         props.company?.billing_email ?? '',

    logo_url:       props.company?.logo_url ?? '',
    linkedin_url:   props.company?.linkedin_url ?? '',
    facebook_url:   props.company?.facebook_url ?? '',
    twitter_handle: props.company?.twitter_handle ?? '',
    instagram_url:  props.company?.instagram_url ?? '',

    // Priorización + post-venta
    domain:              props.company?.domain ?? '',
    is_vip:              props.company?.is_vip ?? false,
    priority:            props.company?.priority ?? 'medium',
    customer_since:      props.company?.customer_since ?? null,
    account_manager_id:  props.company?.account_manager_id ?? null,

    // Pro fiscal + health
    tax_exempt:                props.company?.tax_exempt ?? false,
    tax_exempt_reason:         props.company?.tax_exempt_reason ?? '',
    legal_entity_type:         props.company?.legal_entity_type ?? null,
    bank_account_info:         props.company?.bank_account_info ?? '',
    discount_default_pct:      props.company?.discount_default_pct ?? 0,
    default_payment_method_id: props.company?.default_payment_method_id ?? null,
    account_status:            props.company?.account_status ?? 'active',
    health_score:              props.company?.health_score ?? null,
    churn_risk:                props.company?.churn_risk ?? 'low',
    referrer_company_id:       props.company?.referrer_company_id ?? null,

    is_active:         props.company?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('crm.companies.update', props.company.slug));
    } else {
        form.post(route('crm.companies.store'));
    }
};

// ─── Conditional required (mismo trigger que backend) ───
const taxIdRequired = computed(() =>
    ['customer', 'evangelist'].includes(form.lifecycle_stage)
    || ['supplier', 'both'].includes(form.company_type)
);
const countryRequired = computed(() => form.lifecycle_stage === 'customer');
const currencyRequired = computed(() =>
    ['customer', 'supplier', 'both'].includes(form.company_type)
);

// ─── Hero helpers ───
const ratingColor = computed(() => ({ hot: 'red', warm: 'orange', cold: 'blue', none: 'default' })[form.rating] ?? 'default');
const typeColor = computed(() => ({ customer: 'green', supplier: 'blue', both: 'purple', partner: 'cyan', prospect: 'default' })[form.company_type] ?? 'default');
const initials = computed(() => {
    const n = (form.name || '').trim();
    if (!n) return '?';
    return n.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase();
});

// ─── Anchor nav (sticky sidebar) ───
const sections = [
    { key: 'general',        label: 'Datos generales',      icon: BankOutlined },
    { key: 'classification', label: 'Clasificación',       icon: TagOutlined },
    { key: 'priority',       label: 'Priorización',         icon: FlagOutlined },
    { key: 'financial',      label: 'Financiero',           icon: DollarOutlined },
    { key: 'fiscal',         label: 'Fiscal + legal',       icon: FileTextOutlined },
    { key: 'health',         label: 'Health + churn',       icon: SafetyCertificateOutlined },
    { key: 'contact',        label: 'Contacto',             icon: MailOutlined },
    { key: 'social',         label: 'Social + branding',    icon: GlobalOutlined },
];

const errorsBySection = computed(() => {
    const e = form.errors || {};
    const groups = {
        general:        ['name', 'tax_id', 'legal_name', 'tax_status', 'description', 'domain', 'customer_since'],
        classification: ['company_type', 'lifecycle_stage', 'rating', 'industry_id', 'country_id', 'owner_id', 'score', 'founded_year', 'employee_count', 'external_id'],
        priority:       ['priority', 'is_vip', 'account_manager_id'],
        financial:      ['preferred_currency_code', 'payment_terms_days', 'credit_limit', 'annual_revenue', 'discount_default_pct', 'default_payment_method_id'],
        fiscal:         ['tax_exempt', 'tax_exempt_reason', 'legal_entity_type', 'bank_account_info'],
        health:         ['account_status', 'health_score', 'churn_risk', 'referrer_company_id'],
        contact:        ['website', 'billing_email', 'preferred_language_id'],
        social:         ['logo_url', 'linkedin_url', 'facebook_url', 'twitter_handle', 'instagram_url'],
    };
    return Object.fromEntries(Object.entries(groups).map(([k, fs]) => [k, fs.filter(f => e[f]).length]));
});
</script>

<template>
    <Head :title="isEdit ? $t('companies.edit_title') : $t('companies.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('crm.companies.index')"
            :title="isEdit ? $t('companies.edit_title') : $t('companies.create_title')"
            :subtitle="isEdit ? company.name : $t('companies.create_subtitle')"
        >
            <template #icon><BankOutlined /></template>
        </SectionHeader>

        <Form layout="vertical" @submit.prevent="submit" class="company-form">

            <Alert v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                type="error" show-icon :message="$t('global.fix_marked_fields')" class="mb-4" />

            <!-- HERO -->
            <Card class="hero-card" :bodyStyle="{ padding: '20px 24px' }">
                <div class="hero-row">
                    <Avatar :size="56" shape="square" class="hero-avatar" :src="form.logo_url || null">
                        <template v-if="!form.logo_url">{{ initials }}</template>
                    </Avatar>
                    <div class="hero-text">
                        <div class="hero-name">{{ form.name || $t('companies.name_placeholder_form') }}</div>
                        <div class="hero-meta">
                            <span v-if="form.legal_name" class="muted">{{ form.legal_name }}</span>
                            <span v-if="form.tax_id" class="hero-pill"><IdcardOutlined /> {{ form.tax_id }}</span>
                        </div>
                    </div>
                    <div class="hero-tags">
                        <Tag v-if="form.is_vip" color="gold" :bordered="false">VIP</Tag>
                        <Tag :color="typeColor" :bordered="false">
                            {{ typeOptions.find(o => o.value === form.company_type)?.label ?? form.company_type }}
                        </Tag>
                        <Tag :color="ratingColor" :bordered="false">
                            {{ ratingOptions.find(o => o.value === form.rating)?.label ?? form.rating }}
                        </Tag>
                        <Tag v-if="form.priority === 'critical'" color="red" :bordered="false">Crítica</Tag>
                        <Tag v-else-if="form.priority === 'high'" color="orange" :bordered="false"><ArrowUpOutlined /> Alta</Tag>
                        <Tag v-if="form.account_status === 'at_risk'" color="orange" :bordered="false"><WarningOutlined /> En riesgo</Tag>
                        <Tag v-if="form.account_status === 'churned'" color="red" :bordered="false"><CloseCircleOutlined /> Churned</Tag>
                    </div>
                </div>
            </Card>

            <!-- LAYOUT: sidebar nav + sections -->
            <div class="form-layout">
                <!-- Sidebar de navegación (sticky en desktop) -->
                <aside class="form-nav">
                    <Anchor :affix="true" :offsetTop="80" :show-ink-in-fixed="true">
                        <Anchor.Link v-for="s in sections" :key="s.key" :href="`#section-${s.key}`">
                            <template #title>
                                <span class="nav-link">
                                    <component :is="s.icon" /> {{ s.label }}
                                    <Tag v-if="errorsBySection[s.key]" color="red" class="nav-badge">{{ errorsBySection[s.key] }}</Tag>
                                </span>
                            </template>
                        </Anchor.Link>
                    </Anchor>
                </aside>

                <!-- Cards stacked -->
                <main class="form-cards">

                    <!-- Datos generales -->
                    <Card id="section-general" class="section-card">
                        <template #title><span class="section-title"><BankOutlined /> {{ $t('companies.section_general') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="16"><FormItem required
                                :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                                <template #label><LabelWithHelp :label="$t('companies.name')" :help="$t('companies.name_hint')" /></template>
                                <Input v-model:value="form.name" size="large" :maxlength="255"
                                    :placeholder="$t('companies.name_placeholder_form')" showCount autofocus />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem :required="taxIdRequired"
                                :validate-status="form.errors.tax_id ? 'error' : ''"
                                :help="form.errors.tax_id">
                                <template #label><LabelWithHelp :label="$t('companies.tax_id')" :help="$t('companies.tax_id_hint')" /></template>
                                <Input v-model:value="form.tax_id" size="large" :maxlength="50"
                                    :placeholder="$t('companies.tax_id_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="16"><FormItem
                                :validate-status="form.errors.legal_name ? 'error' : ''" :help="form.errors.legal_name">
                                <template #label><LabelWithHelp :label="$t('companies.legal_name')" :help="$t('companies.legal_name_hint')" /></template>
                                <Input v-model:value="form.legal_name" size="large" :maxlength="200"
                                    :placeholder="$t('companies.legal_name_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem
                                :validate-status="form.errors.tax_status ? 'error' : ''" :help="form.errors.tax_status">
                                <template #label><LabelWithHelp :label="$t('companies.tax_status')" :help="$t('companies.tax_status_hint')" /></template>
                                <Select v-model:value="form.tax_status" :options="taxStatusOptions" size="large" allow-clear />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem
                                :validate-status="form.errors.domain ? 'error' : ''"
                                :help="form.errors.domain">
                                <template #label><LabelWithHelp :label="$t('companies.domain')" :help="$t('companies.domain_hint')" /></template>
                                <Input v-model:value="form.domain" size="large" :maxlength="120"
                                    :placeholder="$t('companies.domain_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem
                                :validate-status="form.errors.customer_since ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('companies.customer_since')" :help="$t('companies.customer_since_hint')" /></template>
                                <Input v-model:value="form.customer_since" type="date" size="large" />
                            </FormItem></Col>
                            <Col :xs="24"><FormItem
                                :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                                <template #label><LabelWithHelp :label="$t('companies.description')" :help="$t('companies.description_hint')" /></template>
                                <Input.TextArea v-model:value="form.description" :rows="3" :maxlength="1000"
                                    :placeholder="$t('companies.description_placeholder')" show-count />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Clasificación -->
                    <Card id="section-classification" class="section-card">
                        <template #title><span class="section-title"><TagOutlined /> {{ $t('companies.section_classification') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.company_type ? 'error' : ''" :help="form.errors.company_type">
                                <template #label><LabelWithHelp :label="$t('companies.company_type')" :help="$t('companies.company_type_hint')" /></template>
                                <Select v-model:value="form.company_type" :options="typeOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.lifecycle_stage ? 'error' : ''" :help="form.errors.lifecycle_stage">
                                <template #label><LabelWithHelp :label="$t('companies.lifecycle_stage')" :help="$t('companies.lifecycle_stage_hint')" /></template>
                                <Select v-model:value="form.lifecycle_stage" :options="stageOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem
                                :validate-status="form.errors.rating ? 'error' : ''" :help="form.errors.rating">
                                <template #label><LabelWithHelp :label="$t('companies.rating')" :help="$t('companies.rating_hint')" /></template>
                                <Select v-model:value="form.rating" :options="ratingOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem
                                :validate-status="form.errors.industry_id ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('companies.industry')" :help="$t('companies.industry_hint')" /></template>
                                <Select v-model:value="form.industry_id" :options="industryOptions"
                                    :placeholder="$t('companies.industry_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem :required="countryRequired"
                                :validate-status="form.errors.country_id ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('companies.country')" :help="$t('companies.country_hint')" /></template>
                                <Select v-model:value="form.country_id" :options="countryOptions"
                                    :placeholder="$t('companies.country_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.owner_id ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('companies.owner')" :help="$t('companies.owner_hint')" /></template>
                                <Select v-model:value="form.owner_id" :options="ownerOptions"
                                    :placeholder="$t('companies.owner_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.score')" :help="$t('companies.score_hint')" /></template>
                                <InputNumber v-model:value="form.score" :min="0" :max="100" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.founded_year')" :help="$t('companies.founded_year_hint')" /></template>
                                <InputNumber v-model:value="form.founded_year" :min="1800" :max="new Date().getFullYear()"
                                    size="large" style="width:100%" :placeholder="$t('companies.founded_year_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.employee_count')" :help="$t('companies.employee_count_hint')" /></template>
                                <InputNumber v-model:value="form.employee_count" :min="0" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.external_id')" :help="$t('companies.external_id_hint')" /></template>
                                <Input v-model:value="form.external_id" size="large" :maxlength="100" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Priorización + Post-venta -->
                    <Card id="section-priority" class="section-card">
                        <template #title><span class="section-title"><FlagOutlined /> {{ $t('companies.section_priority') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.priority')" :help="$t('companies.priority_hint')" /></template>
                                <Select v-model:value="form.priority" :options="priorityOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.is_vip')" :help="$t('companies.is_vip_hint')" /></template>
                                <Space><Switch v-model:checked="form.is_vip" />
                                    <span class="state-label">{{ form.is_vip ? 'VIP' : '—' }}</span>
                                </Space>
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.account_manager')" :help="$t('companies.account_manager_hint')" /></template>
                                <Select v-model:value="form.account_manager_id" :options="ownerOptions"
                                    :placeholder="$t('companies.account_manager_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Financiero -->
                    <Card id="section-financial" class="section-card">
                        <template #title><span class="section-title"><DollarOutlined /> {{ $t('companies.section_financial') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="8"><FormItem :required="currencyRequired"
                                :validate-status="form.errors.preferred_currency_code ? 'error' : ''"
                                :help="form.errors.preferred_currency_code">
                                <template #label><LabelWithHelp :label="$t('companies.preferred_currency_code')" :help="$t('companies.preferred_currency_hint')" /></template>
                                <Select v-model:value="form.preferred_currency_code" :options="currencyOptions"
                                    :placeholder="$t('companies.preferred_currency_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.payment_terms_days')" :help="$t('companies.payment_terms_days_hint')" /></template>
                                <InputNumber v-model:value="form.payment_terms_days" :min="0" :max="365" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.credit_limit')" :help="$t('companies.credit_limit_hint')" /></template>
                                <InputNumber v-model:value="form.credit_limit" :min="0" :step="0.01" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.discount_default_pct')" :help="$t('companies.discount_default_hint')" /></template>
                                <InputNumber v-model:value="form.discount_default_pct" :min="0" :max="100" :step="0.5" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.default_payment_method')" :help="$t('companies.default_payment_method_hint')" /></template>
                                <Select v-model:value="form.default_payment_method_id" :options="paymentMethodOptions"
                                    :placeholder="$t('companies.default_payment_method_placeholder')" size="large" allow-clear />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.annual_revenue')" :help="$t('companies.annual_revenue_hint')" /></template>
                                <InputNumber v-model:value="form.annual_revenue" :min="0" :step="0.01" size="large" style="width:100%" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Fiscal + Legal -->
                    <Card id="section-fiscal" class="section-card">
                        <template #title><span class="section-title"><FileTextOutlined /> {{ $t('companies.section_legal') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.legal_entity_type')" :help="$t('companies.legal_entity_type_hint')" /></template>
                                <Select v-model:value="form.legal_entity_type" :options="legalEntityOptions" size="large" allow-clear
                                    :placeholder="$t('companies.legal_entity_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="4"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.tax_exempt')" :help="$t('companies.tax_exempt_hint')" /></template>
                                <Switch v-model:checked="form.tax_exempt" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12" v-if="form.tax_exempt"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.tax_exempt_reason')" :help="$t('companies.tax_exempt_reason_hint')" /></template>
                                <Input v-model:value="form.tax_exempt_reason" size="large" :maxlength="255" />
                            </FormItem></Col>
                            <Col :xs="24"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.bank_account_info')" :help="$t('companies.bank_account_info_hint')" /></template>
                                <Input.TextArea v-model:value="form.bank_account_info" :rows="3" :maxlength="2000" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Health + Churn -->
                    <Card id="section-health" class="section-card">
                        <template #title><span class="section-title"><SafetyCertificateOutlined /> {{ $t('companies.section_health') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.account_status')" :help="$t('companies.account_status_hint')" /></template>
                                <Select v-model:value="form.account_status" :options="accountStatusOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.churn_risk')" :help="$t('companies.churn_risk_hint')" /></template>
                                <Select v-model:value="form.churn_risk" :options="churnRiskOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.health_score')" :help="$t('companies.health_score_hint')" /></template>
                                <InputNumber v-model:value="form.health_score" :min="0" :max="100" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.referrer_company')" :help="$t('companies.referrer_company_hint')" /></template>
                                <Select v-model:value="form.referrer_company_id" :options="referrerOptions"
                                    :placeholder="$t('companies.referrer_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Contacto y comunicación -->
                    <Card id="section-contact" class="section-card">
                        <template #title><span class="section-title"><MailOutlined /> {{ $t('companies.section_contact') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.website')" :help="$t('companies.website_hint')" /></template>
                                <Input v-model:value="form.website" size="large" :maxlength="255" :placeholder="$t('companies.website_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.billing_email')" :help="$t('companies.billing_email_hint')" /></template>
                                <Input v-model:value="form.billing_email" type="email" size="large" :maxlength="254" :placeholder="$t('companies.billing_email_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.preferred_language_id')" :help="$t('companies.preferred_language_hint')" /></template>
                                <Select v-model:value="form.preferred_language_id" :options="languageOptions"
                                    :placeholder="$t('companies.preferred_language_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Social -->
                    <Card id="section-social" class="section-card">
                        <template #title><span class="section-title"><GlobalOutlined /> {{ $t('companies.section_social') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.logo_url')" :help="$t('companies.logo_url_hint')" /></template>
                                <Input v-model:value="form.logo_url" size="large" :maxlength="500" :placeholder="$t('companies.logo_url_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.linkedin_url')" :help="$t('companies.linkedin_url_hint')" /></template>
                                <Input v-model:value="form.linkedin_url" size="large" :maxlength="255" :placeholder="$t('companies.linkedin_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.facebook_url')" :help="$t('companies.facebook_url_hint')" /></template>
                                <Input v-model:value="form.facebook_url" size="large" :maxlength="255" :placeholder="$t('companies.facebook_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.twitter_handle')" :help="$t('companies.twitter_handle_hint')" /></template>
                                <Input v-model:value="form.twitter_handle" size="large" :maxlength="60" :placeholder="$t('companies.twitter_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('companies.instagram_url')" :help="$t('companies.instagram_url_hint')" /></template>
                                <Input v-model:value="form.instagram_url" size="large" :maxlength="255" :placeholder="$t('companies.instagram_placeholder')" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Estado (solo edit) -->
                    <Card v-if="isEdit" class="section-card">
                        <template #title><span class="section-title"><SettingOutlined /> {{ $t('companies.is_active') }}</span></template>
                        <Space>
                            <Switch v-model:checked="form.is_active" />
                            <span class="state-label">
                                {{ form.is_active ? $t('global.active') : $t('global.inactive') }}
                            </span>
                        </Space>
                    </Card>
                </main>
            </div>

            <FormFooter
                :cancel-href="route('crm.companies.index')"
                :is-edit="isEdit"
                :processing="form.processing"
                create-label-key="companies.new"
            />
        </Form>
    </div>
</template>

<style scoped>
.company-form > * + * { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }

/* Hero */
.hero-card {
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: linear-gradient(135deg, var(--color-surface, #fff) 0%, var(--color-surface-alt, #fafafa) 100%);
}
.hero-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.hero-avatar { background: var(--color-primary, #1677ff); color: #fff; font-weight: 600; font-size: 1.25rem; flex-shrink: 0; }
.hero-text { flex: 1 1 240px; min-width: 0; }
.hero-name { font-size: 1.25rem; font-weight: 600; color: var(--color-text-strong, #111); line-height: 1.3; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.hero-meta { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; margin-top: 4px; color: var(--color-text-muted, #666); flex-wrap: wrap; }
.hero-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: var(--color-surface-alt, #f0f0f0); border-radius: 4px; font-family: ui-monospace, Consolas, monospace; font-size: 0.78rem; }
.hero-tags { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.muted { color: var(--color-text-muted, #666); }

/* Layout sidebar + content */
.form-layout { display: grid; grid-template-columns: 240px 1fr; gap: 24px; align-items: start; }
.form-nav {
    position: sticky;
    top: 16px;
    align-self: start;
}
.form-nav :deep(.ant-anchor) {
    background: var(--color-surface, #fff);
    border: 1px solid var(--color-border, #e8e8e8);
    border-radius: 8px;
    padding: 12px 8px;
}
.form-nav :deep(.ant-anchor-ink) { display: none; }
.nav-link { display: inline-flex; align-items: center; gap: 8px; font-size: 0.88rem; }
.nav-link :deep(.anticon) { font-size: 0.95rem; }
.nav-badge { margin-left: auto; font-size: 0.65rem; padding: 0 6px; line-height: 16px; border-radius: 8px; }
.form-cards { display: flex; flex-direction: column; gap: 16px; min-width: 0; }

/* Section cards */
.section-card {
    border-radius: 8px;
    border: 1px solid var(--color-border, #e8e8e8);
    box-shadow: 0 1px 2px rgba(0,0,0,0.02);
    scroll-margin-top: 80px;
}
.section-card :deep(.ant-card-head) {
    border-bottom: 1px solid var(--color-border-soft, #f0f0f0);
    background: var(--color-surface-alt, #fafafa);
    padding: 0 24px;
    min-height: 48px;
}
.section-card :deep(.ant-card-head-title) { padding: 12px 0; }
.section-title { display: inline-flex; align-items: center; gap: 8px; font-size: 0.95rem; font-weight: 600; color: var(--color-text-strong, #111); }
.section-title :deep(.anticon) { color: var(--color-primary, #1677ff); font-size: 1rem; }

.state-label { font-size: 0.875rem; color: var(--color-text); font-weight: 500; }

@media (max-width: 1024px) {
    .form-layout { grid-template-columns: 1fr; }
    .form-nav { position: static; }
    .form-nav :deep(.ant-anchor) { display: flex; flex-wrap: wrap; gap: 4px; padding: 8px; }
    .nav-link { font-size: 0.8rem; }
}
@media (max-width: 768px) {
    .hero-row { flex-direction: column; align-items: flex-start; }
}
</style>
