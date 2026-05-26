<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select, Tag, Avatar, Anchor, DatePicker,
} from 'ant-design-vue';
import {
    UserOutlined, TeamOutlined, TagOutlined, MailOutlined, SafetyOutlined,
    GlobalOutlined, SettingOutlined, IdcardOutlined, ProfileOutlined, ContactsOutlined,
    AimOutlined, StopOutlined, SafetyCertificateOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    contact:             { type: Object, default: null },
    companyOptions:      { type: Array, default: () => [] },
    contactOptions:      { type: Array, default: () => [] },
    ownerOptions:        { type: Array, default: () => [] },
    languageOptions:     { type: Array, default: () => [] },
    stageOptions:        { type: Array, default: () => [] },
    ratingOptions:       { type: Array, default: () => [] },
    salutationOptions:   { type: Array, default: () => [] },
    genderOptions:       { type: Array, default: () => [] },
    seniorityOptions:    { type: Array, default: () => [] },
    decisionRoleOptions: { type: Array, default: () => [] },
    channelOptions:      { type: Array, default: () => [] },
    strengthOptions:     { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.contact);

const form = useForm({
    salutation:  props.contact?.salutation ?? null,
    first_name:  props.contact?.first_name ?? '',
    last_name:   props.contact?.last_name ?? '',
    middle_name: props.contact?.middle_name ?? '',
    nickname:    props.contact?.nickname ?? '',
    name:        props.contact?.name ?? '',
    job_title:   props.contact?.job_title ?? '',
    department:  props.contact?.department ?? '',
    description: props.contact?.description ?? '',

    primary_email: props.contact?.primary_email ?? '',
    primary_phone: props.contact?.primary_phone ?? '',
    mobile_phone:  props.contact?.mobile_phone ?? '',

    // Pre-fill desde ?company_id=X cuando se entra al form desde el boton
    // "Nuevo Contacto" del tab Contactos de una Company Show.
    company_id:              props.contact?.company_id ?? (Number(new URLSearchParams(window.location.search).get('company_id')) || null),
    reports_to_contact_id:   props.contact?.reports_to_contact_id ?? null,
    is_primary_for_company:  props.contact?.is_primary_for_company ?? false,

    lifecycle_stage: props.contact?.lifecycle_stage ?? 'lead',
    lead_source:     props.contact?.lead_source ?? '',
    rating:          props.contact?.rating ?? 'none',
    score:           props.contact?.score ?? 0,

    owner_id:               props.contact?.owner_id ?? null,
    preferred_language_id:  props.contact?.preferred_language_id ?? null,
    timezone:               props.contact?.timezone ?? '',

    seniority_level:   props.contact?.seniority_level ?? null,
    decision_role:     props.contact?.decision_role ?? null,
    is_decision_maker: props.contact?.is_decision_maker ?? false,
    preferred_channel: props.contact?.preferred_channel ?? null,

    assistant_name:  props.contact?.assistant_name ?? '',
    assistant_email: props.contact?.assistant_email ?? '',
    assistant_phone: props.contact?.assistant_phone ?? '',

    email_opt_in:    props.contact?.email_opt_in ?? true,
    sms_opt_in:      props.contact?.sms_opt_in ?? true,
    whatsapp_opt_in: props.contact?.whatsapp_opt_in ?? true,
    do_not_contact:  props.contact?.do_not_contact ?? false,

    marketing_opt_in_at:     props.contact?.marketing_opt_in_at ?? null,
    marketing_opt_in_source: props.contact?.marketing_opt_in_source ?? '',
    unsubscribed_at:         props.contact?.unsubscribed_at ?? null,
    unsubscribed_reason:     props.contact?.unsubscribed_reason ?? '',
    relationship_strength:   props.contact?.relationship_strength ?? 'cold',

    date_of_birth: props.contact?.date_of_birth ?? null,
    gender:        props.contact?.gender ?? null,

    linkedin_url:   props.contact?.linkedin_url ?? '',
    twitter_handle: props.contact?.twitter_handle ?? '',
    photo_url:      props.contact?.photo_url ?? '',
    external_id:    props.contact?.external_id ?? '',

    is_active: props.contact?.is_active ?? true,
});

const submit = () => {
    if (!form.name && (form.first_name || form.last_name)) {
        form.name = [form.first_name, form.last_name].filter(Boolean).join(' ');
    }
    if (isEdit.value) {
        form.put(route('crm.contacts.update', props.contact.slug));
    } else {
        form.post(route('crm.contacts.store'));
    }
};

// Conditional required
const nameRequired    = computed(() => !form.first_name && !form.last_name);
const contactRequired = computed(() => !form.primary_email && !form.primary_phone && !form.mobile_phone);

// Hero helpers
const fullName = computed(() => {
    const n = [form.first_name, form.last_name].filter(Boolean).join(' ').trim();
    return n || form.name || '?';
});
const initials = computed(() => {
    const fn = (form.first_name || '').trim();
    const ln = (form.last_name || '').trim();
    if (fn || ln) return ((fn[0] ?? '') + (ln[0] ?? '')).toUpperCase() || '?';
    const n = (form.name || '').trim();
    return n ? n.split(/\s+/).map(w => w[0]).slice(0, 2).join('').toUpperCase() : '?';
});
const ratingColor = computed(() => ({ hot: 'red', warm: 'orange', cold: 'blue', none: 'default' })[form.rating] ?? 'default');
const companyName = computed(() => props.companyOptions.find(c => c.value === form.company_id)?.label);

const sections = [
    { key: 'general',        label: 'Datos personales',     icon: UserOutlined },
    { key: 'contact',        label: 'Contacto',             icon: MailOutlined },
    { key: 'company',        label: 'Empresa',              icon: TeamOutlined },
    { key: 'classification', label: 'Clasificación CRM',    icon: TagOutlined },
    { key: 'assistant',      label: 'Asistente',            icon: ContactsOutlined },
    { key: 'compliance',     label: 'Marketing + Compliance', icon: SafetyOutlined },
    { key: 'personal',       label: 'Personal',             icon: ProfileOutlined },
    { key: 'social',         label: 'Social',               icon: GlobalOutlined },
];

const errorsBySection = computed(() => {
    const e = form.errors || {};
    const groups = {
        general:        ['name', 'first_name', 'last_name', 'middle_name', 'salutation', 'nickname', 'job_title', 'department', 'description'],
        contact:        ['primary_email', 'primary_phone', 'mobile_phone'],
        company:        ['company_id', 'reports_to_contact_id', 'is_primary_for_company'],
        classification: ['lifecycle_stage', 'lead_source', 'rating', 'score', 'owner_id', 'seniority_level', 'decision_role', 'is_decision_maker', 'preferred_channel'],
        assistant:      ['assistant_name', 'assistant_email', 'assistant_phone'],
        compliance:     ['email_opt_in', 'sms_opt_in', 'whatsapp_opt_in', 'do_not_contact', 'preferred_language_id', 'timezone', 'marketing_opt_in_at', 'marketing_opt_in_source', 'unsubscribed_at', 'relationship_strength'],
        personal:       ['date_of_birth', 'gender'],
        social:         ['linkedin_url', 'twitter_handle', 'photo_url', 'external_id'],
    };
    return Object.fromEntries(Object.entries(groups).map(([k, fs]) => [k, fs.filter(f => e[f]).length]));
});
</script>

<template>
    <Head :title="isEdit ? $t('contacts.edit_title') : $t('contacts.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('crm.contacts.index')"
            :title="isEdit ? $t('contacts.edit_title') : $t('contacts.create_title')"
            :subtitle="isEdit ? contact.name : $t('contacts.create_subtitle')"
        >
            <template #icon><UserOutlined /></template>
        </SectionHeader>

        <Form layout="vertical" @submit.prevent="submit" class="contact-form">

            <Alert v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                type="error" show-icon :message="$t('global.fix_marked_fields')" class="mb-4" />

            <!-- HERO -->
            <Card class="hero-card" :bodyStyle="{ padding: '20px 24px' }">
                <div class="hero-row">
                    <Avatar :size="56" shape="circle" class="hero-avatar" :src="form.photo_url || null">
                        <template v-if="!form.photo_url">{{ initials }}</template>
                    </Avatar>
                    <div class="hero-text">
                        <div class="hero-name">{{ fullName }}</div>
                        <div class="hero-meta">
                            <span v-if="form.job_title">{{ form.job_title }}</span>
                            <span v-if="companyName" class="hero-pill"><TeamOutlined /> {{ companyName }}</span>
                            <span v-if="form.primary_email" class="muted">{{ form.primary_email }}</span>
                        </div>
                    </div>
                    <div class="hero-tags">
                        <Tag v-if="form.is_decision_maker" color="purple" :bordered="false"><AimOutlined /> Decision Maker</Tag>
                        <Tag :color="ratingColor" :bordered="false">
                            {{ ratingOptions.find(o => o.value === form.rating)?.label ?? form.rating }}
                        </Tag>
                        <Tag v-if="form.do_not_contact" color="red" :bordered="false"><StopOutlined /> No contactar</Tag>
                        <Tag v-if="form.relationship_strength === 'champion'" color="gold" :bordered="false"><SafetyCertificateOutlined /> Champion</Tag>
                    </div>
                </div>
            </Card>

            <div class="form-layout">
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

                <main class="form-cards">

                    <!-- Datos personales -->
                    <Card id="section-general" class="section-card">
                        <template #title><span class="section-title"><UserOutlined /> {{ $t('contacts.section_general') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="4"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.salutation')" :help="$t('contacts.salutation_hint')" /></template>
                                <Select v-model:value="form.salutation" :options="salutationOptions" size="large" allow-clear
                                    :placeholder="$t('contacts.salutation_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem :required="nameRequired"
                                :validate-status="form.errors.first_name ? 'error' : ''" :help="form.errors.first_name">
                                <template #label><LabelWithHelp :label="$t('contacts.first_name')" :help="$t('contacts.first_name_hint')" /></template>
                                <Input v-model:value="form.first_name" size="large" :maxlength="120"
                                    :placeholder="$t('contacts.first_name_placeholder')" autofocus />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem :required="nameRequired"
                                :validate-status="form.errors.last_name ? 'error' : ''" :help="form.errors.last_name">
                                <template #label><LabelWithHelp :label="$t('contacts.last_name')" :help="$t('contacts.last_name_hint')" /></template>
                                <Input v-model:value="form.last_name" size="large" :maxlength="120"
                                    :placeholder="$t('contacts.last_name_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="4"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.nickname')" :help="$t('contacts.nickname_hint')" /></template>
                                <Input v-model:value="form.nickname" size="large" :maxlength="60"
                                    :placeholder="$t('contacts.nickname_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.job_title')" :help="$t('contacts.job_title_hint')" /></template>
                                <Input v-model:value="form.job_title" size="large" :maxlength="150"
                                    :placeholder="$t('contacts.job_title_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.department')" :help="$t('contacts.department_hint')" /></template>
                                <Input v-model:value="form.department" size="large" :maxlength="120"
                                    :placeholder="$t('contacts.department_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.description')" :help="$t('contacts.description_hint')" /></template>
                                <Input.TextArea v-model:value="form.description" :rows="3" :maxlength="1000"
                                    :placeholder="$t('contacts.description_placeholder')" show-count />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Contacto -->
                    <Card id="section-contact" class="section-card">
                        <template #title><span class="section-title"><MailOutlined /> {{ $t('contacts.section_communication') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="12"><FormItem :required="contactRequired"
                                :validate-status="form.errors.primary_email ? 'error' : ''" :help="form.errors.primary_email">
                                <template #label><LabelWithHelp :label="$t('contacts.primary_email')" :help="$t('contacts.primary_email_hint')" /></template>
                                <Input v-model:value="form.primary_email" type="email" size="large" :maxlength="254"
                                    :placeholder="$t('contacts.primary_email_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem :required="contactRequired"
                                :validate-status="form.errors.primary_phone ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('contacts.primary_phone')" :help="$t('contacts.primary_phone_hint')" /></template>
                                <Input v-model:value="form.primary_phone" size="large" :maxlength="30" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.mobile_phone')" :help="$t('contacts.mobile_phone_hint')" /></template>
                                <Input v-model:value="form.mobile_phone" size="large" :maxlength="30" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Empresa -->
                    <Card id="section-company" class="section-card">
                        <template #title><span class="section-title"><TeamOutlined /> {{ $t('contacts.section_company') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.company')" :help="$t('contacts.company_hint')" /></template>
                                <Select v-model:value="form.company_id" :options="companyOptions"
                                    :placeholder="$t('contacts.company_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.reports_to')" :help="$t('contacts.reports_to_hint')" /></template>
                                <Select v-model:value="form.reports_to_contact_id" :options="contactOptions"
                                    :placeholder="$t('contacts.reports_to_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24"><FormItem>
                                <Space>
                                    <Switch v-model:checked="form.is_primary_for_company" />
                                    <span class="state-label">{{ $t('contacts.is_primary_for_company') }}</span>
                                </Space>
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Clasificación CRM (lifecycle + MEDDIC) -->
                    <Card id="section-classification" class="section-card">
                        <template #title><span class="section-title"><TagOutlined /> {{ $t('contacts.section_classification') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.lifecycle_stage ? 'error' : ''">
                                <template #label><LabelWithHelp :label="$t('contacts.lifecycle_stage')" :help="$t('contacts.lifecycle_stage_hint')" /></template>
                                <Select v-model:value="form.lifecycle_stage" :options="stageOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.rating')" :help="$t('contacts.rating_hint')" /></template>
                                <Select v-model:value="form.rating" :options="ratingOptions" size="large" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.score')" :help="$t('contacts.score_hint')" /></template>
                                <InputNumber v-model:value="form.score" :min="0" :max="100" size="large" style="width:100%" />
                            </FormItem></Col>

                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.owner_id ? 'error' : ''" :help="form.errors.owner_id">
                                <template #label><LabelWithHelp :label="$t('contacts.owner')" :help="$t('contacts.owner_hint')" /></template>
                                <Select v-model:value="form.owner_id" :options="ownerOptions"
                                    :placeholder="$t('contacts.owner_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.lead_source')" :help="$t('contacts.lead_source_hint')" /></template>
                                <Input v-model:value="form.lead_source" size="large" :maxlength="60"
                                    :placeholder="$t('contacts.lead_source_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.preferred_channel')" :help="$t('contacts.preferred_channel_hint')" /></template>
                                <Select v-model:value="form.preferred_channel" :options="channelOptions" size="large" allow-clear />
                            </FormItem></Col>

                            <!-- MEDDIC -->
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.seniority_level')" :help="$t('contacts.seniority_level_hint')" /></template>
                                <Select v-model:value="form.seniority_level" :options="seniorityOptions" size="large" allow-clear />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.decision_role')" :help="$t('contacts.decision_role_hint')" /></template>
                                <Select v-model:value="form.decision_role" :options="decisionRoleOptions" size="large" allow-clear />
                            </FormItem></Col>
                            <Col :xs="12" :md="4"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.is_decision_maker')" :help="$t('contacts.is_decision_maker_hint')" /></template>
                                <Switch v-model:checked="form.is_decision_maker" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Asistente -->
                    <Card id="section-assistant" class="section-card">
                        <template #title><span class="section-title"><ContactsOutlined /> {{ $t('contacts.assistant_name') }}</span></template>
                        <p class="section-hint">{{ $t('contacts.assistant_hint') }}</p>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="10"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.assistant_name')" :help="$t('contacts.assistant_name_hint')" /></template>
                                <Input v-model:value="form.assistant_name" size="large" :maxlength="200" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.assistant_email')" :help="$t('contacts.assistant_email_hint')" /></template>
                                <Input v-model:value="form.assistant_email" type="email" size="large" :maxlength="254" />
                            </FormItem></Col>
                            <Col :xs="24" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.assistant_phone')" :help="$t('contacts.assistant_phone_hint')" /></template>
                                <Input v-model:value="form.assistant_phone" size="large" :maxlength="30" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Marketing + Compliance -->
                    <Card id="section-compliance" class="section-card">
                        <template #title><span class="section-title"><SafetyOutlined /> Marketing + Compliance</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.preferred_language')" :help="$t('contacts.preferred_language_hint')" /></template>
                                <Select v-model:value="form.preferred_language_id" :options="languageOptions"
                                    :placeholder="$t('contacts.preferred_language_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.relationship_strength')" :help="$t('contacts.relationship_strength_hint')" /></template>
                                <Select v-model:value="form.relationship_strength" :options="strengthOptions" size="large" />
                            </FormItem></Col>

                            <Col :xs="12" :md="6"><FormItem><Space>
                                <Switch v-model:checked="form.email_opt_in" />
                                <span class="state-label">{{ $t('contacts.email_opt_in') }}</span>
                            </Space></FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem><Space>
                                <Switch v-model:checked="form.sms_opt_in" />
                                <span class="state-label">{{ $t('contacts.sms_opt_in') }}</span>
                            </Space></FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem><Space>
                                <Switch v-model:checked="form.whatsapp_opt_in" />
                                <span class="state-label">{{ $t('contacts.whatsapp_opt_in') }}</span>
                            </Space></FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem><Space>
                                <Switch v-model:checked="form.do_not_contact" />
                                <span class="state-label" style="color: #d4380d">{{ $t('contacts.do_not_contact') }}</span>
                            </Space></FormItem></Col>

                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.marketing_opt_in_at')" :help="$t('contacts.marketing_opt_in_at_hint')" /></template>
                                <DatePicker v-model:value="form.marketing_opt_in_at" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.marketing_opt_in_source')" :help="$t('contacts.marketing_opt_in_source_hint')" /></template>
                                <Input v-model:value="form.marketing_opt_in_source" size="large" :maxlength="120"
                                    :placeholder="$t('contacts.marketing_opt_in_source_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.unsubscribed_at')" :help="$t('contacts.unsubscribed_at_hint')" /></template>
                                <DatePicker v-model:value="form.unsubscribed_at" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.unsubscribed_reason')" :help="$t('contacts.unsubscribed_reason_hint')" /></template>
                                <Input v-model:value="form.unsubscribed_reason" size="large" :maxlength="255" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Personal -->
                    <Card id="section-personal" class="section-card">
                        <template #title><span class="section-title"><ProfileOutlined /> {{ $t('contacts.section_personal') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.date_of_birth')" :help="$t('contacts.date_of_birth_hint')" /></template>
                                <DatePicker v-model:value="form.date_of_birth" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>
                            <Col :xs="12" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.gender')" :help="$t('contacts.gender_hint')" /></template>
                                <Select v-model:value="form.gender" :options="genderOptions" size="large" allow-clear />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.middle_name')" :help="$t('contacts.middle_name_hint')" /></template>
                                <Input v-model:value="form.middle_name" size="large" :maxlength="120" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Social -->
                    <Card id="section-social" class="section-card">
                        <template #title><span class="section-title"><GlobalOutlined /> {{ $t('contacts.section_social') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.photo_url')" :help="$t('contacts.photo_url_hint')" /></template>
                                <Input v-model:value="form.photo_url" size="large" :maxlength="500" placeholder="https://..." />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.linkedin_url')" :help="$t('contacts.linkedin_url_hint')" /></template>
                                <Input v-model:value="form.linkedin_url" size="large" :maxlength="255" placeholder="https://linkedin.com/in/..." />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.twitter_handle')" :help="$t('contacts.twitter_handle_hint')" /></template>
                                <Input v-model:value="form.twitter_handle" size="large" :maxlength="60" placeholder="@usuario" />
                            </FormItem></Col>
                            <Col :xs="12" :md="6"><FormItem>
                                <template #label><LabelWithHelp :label="$t('contacts.external_id')" :help="$t('contacts.external_id_hint')" /></template>
                                <Input v-model:value="form.external_id" size="large" :maxlength="100" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Estado (solo edit) -->
                    <Card v-if="isEdit" class="section-card">
                        <template #title><span class="section-title"><SettingOutlined /> {{ $t('contacts.is_active') }}</span></template>
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
                :cancel-href="route('crm.contacts.index')"
                :is-edit="isEdit"
                :processing="form.processing"
                create-label-key="contacts.new"
            />
        </Form>
    </div>
</template>

<style scoped>
.contact-form > * + * { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }

.hero-card {
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: linear-gradient(135deg, var(--color-surface, #fff) 0%, var(--color-surface-alt, #fafafa) 100%);
}
.hero-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.hero-avatar { background: var(--color-primary, #1677ff); color: #fff; font-weight: 600; font-size: 1.1rem; flex-shrink: 0; }
.hero-text { flex: 1 1 240px; min-width: 0; }
.hero-name { font-size: 1.25rem; font-weight: 600; color: var(--color-text-strong, #111); line-height: 1.3; }
.hero-meta { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; margin-top: 4px; color: var(--color-text-muted, #666); flex-wrap: wrap; }
.hero-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: var(--color-surface-alt, #f0f0f0); border-radius: 4px; font-size: 0.78rem; }
.hero-tags { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.muted { color: var(--color-text-muted, #666); }

.form-layout { display: grid; grid-template-columns: 240px 1fr; gap: 24px; align-items: start; }
.form-nav { position: sticky; top: 16px; align-self: start; }
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
.section-hint { color: var(--color-text-muted, #666); font-size: 0.85rem; margin: -8px 0 12px; }

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
