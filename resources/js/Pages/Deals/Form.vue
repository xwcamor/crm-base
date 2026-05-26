<script setup>
import { computed, watch } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, InputNumber, Switch, Space, Alert, Row, Col, Select, Tag, Anchor, DatePicker,
} from 'ant-design-vue';
import {
    DollarOutlined, FunnelPlotOutlined, TeamOutlined, FlagOutlined,
    SettingOutlined, FileTextOutlined, CheckCircleOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    deal:                { type: Object, default: null },
    pipelineOptions:     { type: Array, default: () => [] },
    stageOptions:        { type: Array, default: () => [] },
    statusOptions:       { type: Array, default: () => [] },
    companyOptions:      { type: Array, default: () => [] },
    contactOptions:      { type: Array, default: () => [] },
    ownerOptions:        { type: Array, default: () => [] },
    leadSourceOptions:   { type: Array, default: () => [] },
    currencyOptions:     { type: Array, default: () => [] },
    defaultCurrencyCode: { type: String, default: null },
    defaultPipelineId:   { type: Number, default: null },
});

const isEdit = computed(() => !!props.deal);

const form = useForm({
    name:        props.deal?.name ?? '',
    description: props.deal?.description ?? '',

    pipeline_id: props.deal?.pipeline_id ?? props.defaultPipelineId ?? null,
    stage_id:    props.deal?.stage_id ?? null,
    status:      props.deal?.status ?? 'open',

    value:           props.deal?.value ?? null,
    currency_code:   props.deal?.currency_code ?? props.defaultCurrencyCode ?? null,
    probability_pct: props.deal?.probability_pct ?? 0,

    expected_close_date: props.deal?.expected_close_date ?? null,
    won_at:              props.deal?.won_at ?? null,
    lost_at:             props.deal?.lost_at ?? null,
    lost_reason_note:    props.deal?.lost_reason_note ?? '',

    // Pre-fill desde ?company_id=X cuando se entra al form desde el boton
    // "Nuevo Deal" del tab Deals de una Company Show.
    company_id:     props.deal?.company_id ?? (Number(new URLSearchParams(window.location.search).get('company_id')) || null),
    contact_id:     props.deal?.contact_id ?? (Number(new URLSearchParams(window.location.search).get('contact_id')) || null),
    owner_id:       props.deal?.owner_id ?? null,
    lead_source_id: props.deal?.lead_source_id ?? null,

    external_id: props.deal?.external_id ?? '',

    is_active: props.deal?.is_active ?? true,
});

const submit = () => {
    if (isEdit.value) {
        form.put(route('crm.deals.update', props.deal.slug));
    } else {
        form.post(route('crm.deals.store'));
    }
};

// Stages filtrados por pipeline seleccionado
const filteredStageOptions = computed(() =>
    props.stageOptions.filter(s => s.pipeline_id === form.pipeline_id)
);

// Cuando cambia la etapa, heredar la probabilidad de la stage (patron Pipedrive/
// HubSpot). Permite que el usuario la override manualmente despues si tiene
// info especifica del deal — la heredada es solo un default razonable.
watch(() => form.stage_id, (newStageId) => {
    if (newStageId == null) return;
    const stage = props.stageOptions.find(s => s.value === newStageId);
    if (stage && typeof stage.probability_pct === 'number') {
        form.probability_pct = stage.probability_pct;
    }
});

// Contacts filtrados por empresa (si hay empresa). Si no hay empresa, muestra todos.
const filteredContactOptions = computed(() => {
    if (!form.company_id) return props.contactOptions;
    return props.contactOptions.filter(c => c.company_id === form.company_id || c.company_id == null);
});

// Conditional required: cuando status=lost se pide motivo y fecha
const lostFieldsRequired = computed(() => form.status === 'lost');
const wonFieldsRequired  = computed(() => form.status === 'won');

// Helpers UI
const statusColor = computed(() => ({
    open: 'blue', won: 'green', lost: 'red', dormant: 'default',
}[form.status] ?? 'default'));

const statusLabel = computed(() =>
    props.statusOptions.find(s => s.value === form.status)?.label ?? form.status
);

const currencySymbol = computed(() => form.currency_code || '');

const formattedValue = computed(() => {
    if (form.value == null || form.value === '') return null;
    const n = Number(form.value);
    if (Number.isNaN(n)) return null;
    return new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(n);
});

const weightedValue = computed(() => {
    if (form.value == null || form.value === '') return null;
    const v = Number(form.value);
    const p = Number(form.probability_pct ?? 0);
    if (Number.isNaN(v) || Number.isNaN(p)) return null;
    return new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v * (p / 100));
});

const companyName = computed(() => props.companyOptions.find(c => c.value === form.company_id)?.label);
const pipelineName = computed(() => props.pipelineOptions.find(p => p.value === form.pipeline_id)?.label);

const sections = computed(() => [
    { key: 'general',   label: t('deals.section_general'),   icon: FileTextOutlined },
    { key: 'pipeline',  label: t('deals.section_pipeline'),  icon: FunnelPlotOutlined },
    { key: 'money',     label: t('deals.section_money'),     icon: DollarOutlined },
    { key: 'relations', label: t('deals.section_relations'), icon: TeamOutlined },
    { key: 'closing',   label: t('deals.section_closing'),   icon: CheckCircleOutlined },
]);

const errorsBySection = computed(() => {
    const e = form.errors || {};
    const groups = {
        general:   ['name', 'description', 'external_id'],
        pipeline:  ['pipeline_id', 'stage_id', 'status'],
        money:     ['value', 'currency_code'],
        relations: ['company_id', 'contact_id', 'owner_id', 'lead_source_id'],
        closing:   ['probability_pct', 'expected_close_date', 'won_at', 'lost_at', 'lost_reason_note'],
    };
    return Object.fromEntries(Object.entries(groups).map(([k, fs]) => [k, fs.filter(f => e[f]).length]));
});
</script>

<template>
    <Head :title="isEdit ? $t('deals.edit_title') : $t('deals.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('crm.deals.index')"
            :title="isEdit ? $t('deals.edit_title') : $t('deals.create_title')"
            :subtitle="isEdit ? deal.name : $t('deals.create_subtitle')"
        >
            <template #icon><DollarOutlined /></template>
        </SectionHeader>

        <Form layout="vertical" @submit.prevent="submit" class="deal-form">

            <Alert v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                type="error" show-icon :message="$t('global.fix_marked_fields')" class="mb-4" />

            <!-- HERO -->
            <Card class="hero-card" :bodyStyle="{ padding: '20px 24px' }">
                <div class="hero-row">
                    <div class="hero-icon-box">
                        <DollarOutlined />
                    </div>
                    <div class="hero-text">
                        <div class="hero-name">{{ form.name || $t('deals.name_placeholder') }}</div>
                        <div class="hero-meta">
                            <span v-if="companyName" class="hero-pill"><TeamOutlined /> {{ companyName }}</span>
                            <span v-if="pipelineName" class="hero-pill"><FunnelPlotOutlined /> {{ pipelineName }}</span>
                            <span v-if="form.probability_pct" class="muted">{{ form.probability_pct }}%</span>
                        </div>
                    </div>
                    <div class="hero-value-block">
                        <div class="hero-value-amount" v-if="formattedValue">
                            <span class="hero-currency">{{ currencySymbol }}</span> {{ formattedValue }}
                        </div>
                        <div class="hero-value-amount muted" v-else>—</div>
                        <div class="hero-weighted muted" v-if="weightedValue">
                            {{ $t('deals.weighted_value') }}: {{ currencySymbol }} {{ weightedValue }}
                        </div>
                    </div>
                    <div class="hero-tags">
                        <Tag :color="statusColor" :bordered="false">{{ statusLabel }}</Tag>
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

                    <!-- Datos generales -->
                    <Card id="section-general" class="section-card">
                        <template #title><span class="section-title"><FileTextOutlined /> {{ $t('deals.section_general') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="16"><FormItem required
                                :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                                <template #label><LabelWithHelp :label="$t('deals.name')" :help="$t('deals.name_hint')" /></template>
                                <Input v-model:value="form.name" size="large" :maxlength="255" show-count autofocus
                                    :placeholder="$t('deals.name_placeholder_form')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('deals.external_id')" :help="$t('deals.external_id_hint')" /></template>
                                <Input v-model:value="form.external_id" size="large" :maxlength="100" />
                            </FormItem></Col>
                            <Col :xs="24"><FormItem
                                :validate-status="form.errors.description ? 'error' : ''" :help="form.errors.description">
                                <template #label><LabelWithHelp :label="$t('deals.description')" :help="$t('deals.description_hint')" /></template>
                                <Input.TextArea v-model:value="form.description" :rows="3" :maxlength="1000" show-count
                                    :placeholder="$t('deals.description_placeholder')" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Pipeline + estado -->
                    <Card id="section-pipeline" class="section-card">
                        <template #title><span class="section-title"><FunnelPlotOutlined /> {{ $t('deals.section_pipeline') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.pipeline_id ? 'error' : ''" :help="form.errors.pipeline_id">
                                <template #label><LabelWithHelp :label="$t('deals.pipeline')" :help="$t('deals.pipeline_hint')" /></template>
                                <Select v-model:value="form.pipeline_id" :options="pipelineOptions"
                                    :placeholder="$t('deals.pipeline_placeholder')" size="large"
                                    @change="form.stage_id = null" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.stage_id ? 'error' : ''" :help="form.errors.stage_id">
                                <template #label><LabelWithHelp :label="$t('deals.stage')" :help="$t('deals.stage_hint')" /></template>
                                <Select v-model:value="form.stage_id" :options="filteredStageOptions"
                                    :placeholder="$t('deals.stage_placeholder')" size="large"
                                    :disabled="!form.pipeline_id" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem required
                                :validate-status="form.errors.status ? 'error' : ''" :help="form.errors.status">
                                <template #label><LabelWithHelp :label="$t('deals.status')" :help="$t('deals.status_hint')" /></template>
                                <Select v-model:value="form.status" :options="statusOptions" size="large" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Valor + moneda -->
                    <Card id="section-money" class="section-card">
                        <template #title><span class="section-title"><DollarOutlined /> {{ $t('deals.section_money') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="14"><FormItem
                                :validate-status="form.errors.value ? 'error' : ''" :help="form.errors.value">
                                <template #label><LabelWithHelp :label="$t('deals.value')" :help="$t('deals.value_hint')" /></template>
                                <InputNumber v-model:value="form.value" :min="0" :step="0.01" size="large"
                                    style="width:100%" :placeholder="$t('deals.value_placeholder')" />
                            </FormItem></Col>
                            <Col :xs="24" :md="10"><FormItem required
                                :validate-status="form.errors.currency_code ? 'error' : ''" :help="form.errors.currency_code">
                                <template #label><LabelWithHelp :label="$t('deals.currency')" :help="$t('deals.currency_hint')" /></template>
                                <Select v-model:value="form.currency_code" :options="currencyOptions"
                                    :placeholder="$t('deals.currency_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Relaciones -->
                    <Card id="section-relations" class="section-card">
                        <template #title><span class="section-title"><TeamOutlined /> {{ $t('deals.section_relations') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="12"><FormItem required
                                :validate-status="form.errors.company_id ? 'error' : ''" :help="form.errors.company_id">
                                <template #label><LabelWithHelp :label="$t('deals.company')" :help="$t('deals.company_hint')" /></template>
                                <Select v-model:value="form.company_id" :options="companyOptions"
                                    :placeholder="$t('deals.company_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())"
                                    @change="form.contact_id = null" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem
                                :validate-status="form.errors.contact_id ? 'error' : ''" :help="form.errors.contact_id">
                                <template #label><LabelWithHelp :label="$t('deals.contact')" :help="$t('deals.contact_hint')" /></template>
                                <Select v-model:value="form.contact_id" :options="filteredContactOptions"
                                    :placeholder="$t('deals.contact_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem required
                                :validate-status="form.errors.owner_id ? 'error' : ''" :help="form.errors.owner_id">
                                <template #label><LabelWithHelp :label="$t('deals.owner')" :help="$t('deals.owner_hint')" /></template>
                                <Select v-model:value="form.owner_id" :options="ownerOptions"
                                    :placeholder="$t('deals.owner_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                            <Col :xs="24" :md="12"><FormItem>
                                <template #label><LabelWithHelp :label="$t('deals.lead_source')" :help="$t('deals.lead_source_hint')" /></template>
                                <Select v-model:value="form.lead_source_id" :options="leadSourceOptions"
                                    :placeholder="$t('deals.lead_source_placeholder')" size="large"
                                    allow-clear show-search
                                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())" />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Cierre -->
                    <Card id="section-closing" class="section-card">
                        <template #title><span class="section-title"><CheckCircleOutlined /> {{ $t('deals.section_closing') }}</span></template>
                        <Row :gutter="[20, 0]">
                            <Col :xs="24" :md="8"><FormItem
                                :validate-status="form.errors.probability_pct ? 'error' : ''" :help="form.errors.probability_pct">
                                <template #label><LabelWithHelp :label="$t('deals.probability_pct')" :help="$t('deals.probability_pct_hint')" /></template>
                                <InputNumber v-model:value="form.probability_pct"
                                    :min="0" :max="100" :step="5" :precision="0"
                                    size="large" style="width:100%"
                                    :formatter="v => `${v}%`"
                                    :parser="v => {
                                        const n = parseInt(String(v ?? '').replace(/[^0-9]/g, ''), 10);
                                        if (isNaN(n)) return 0;
                                        return Math.min(100, Math.max(0, n));
                                    }" />
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem>
                                <template #label><LabelWithHelp :label="$t('deals.weighted_value')" :help="$t('deals.weighted_value_hint')" /></template>
                                <div class="weighted-value-display">
                                    <strong>{{ weightedValue ?? '—' }}</strong>
                                    <span v-if="form.currency_code" class="muted">{{ form.currency_code }}</span>
                                </div>
                            </FormItem></Col>
                            <Col :xs="24" :md="8"><FormItem
                                :validate-status="form.errors.expected_close_date ? 'error' : ''" :help="form.errors.expected_close_date">
                                <template #label><LabelWithHelp :label="$t('deals.expected_close_date')" :help="$t('deals.expected_close_date_hint')" /></template>
                                <DatePicker v-model:value="form.expected_close_date" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>

                            <Col v-if="wonFieldsRequired" :xs="24" :md="8"><FormItem :required="wonFieldsRequired"
                                :validate-status="form.errors.won_at ? 'error' : ''" :help="form.errors.won_at">
                                <template #label><LabelWithHelp :label="$t('deals.won_at')" :help="$t('deals.won_at_hint')" /></template>
                                <DatePicker v-model:value="form.won_at" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>

                            <Col v-if="lostFieldsRequired" :xs="24" :md="8"><FormItem :required="lostFieldsRequired"
                                :validate-status="form.errors.lost_at ? 'error' : ''" :help="form.errors.lost_at">
                                <template #label><LabelWithHelp :label="$t('deals.lost_at')" :help="$t('deals.lost_at_hint')" /></template>
                                <DatePicker v-model:value="form.lost_at" valueFormat="YYYY-MM-DD" size="large" style="width:100%" />
                            </FormItem></Col>

                            <Col v-if="lostFieldsRequired" :xs="24"><FormItem :required="lostFieldsRequired"
                                :validate-status="form.errors.lost_reason_note ? 'error' : ''" :help="form.errors.lost_reason_note">
                                <template #label><LabelWithHelp :label="$t('deals.lost_reason_note')" :help="$t('deals.lost_reason_note_hint')" /></template>
                                <Input.TextArea v-model:value="form.lost_reason_note" :rows="2" :maxlength="500" show-count />
                            </FormItem></Col>
                        </Row>
                    </Card>

                    <!-- Toggle is_active removido: Deal usa `status`
                         (open/won/lost/dormant) como ciclo de vida. is_active
                         queda en BD con default true para no romper queries
                         pero NO se expone como toggle en la UI — el usuario
                         se confundia con el status del deal. -->
                </main>
            </div>

            <FormFooter
                :cancel-href="route('crm.deals.index')"
                :is-edit="isEdit"
                :processing="form.processing"
                create-label-key="deals.new"
            />
        </Form>
    </div>
</template>

<style scoped>
.deal-form > * + * { margin-top: 16px; }
.mb-4 { margin-bottom: 16px; }

.hero-card {
    border-radius: 8px;
    border: 1px solid var(--color-border);
    background: linear-gradient(135deg, var(--color-surface, #fff) 0%, var(--color-surface-alt, #fafafa) 100%);
}
.hero-row { display: flex; align-items: center; gap: 16px; flex-wrap: wrap; }
.hero-icon-box {
    width: 56px; height: 56px; border-radius: 12px;
    display: flex; align-items: center; justify-content: center;
    background: var(--color-primary, #1677ff);
    color: #fff; font-size: 1.5rem; flex-shrink: 0;
}
.hero-text { flex: 1 1 240px; min-width: 0; }
.hero-name { font-size: 1.25rem; font-weight: 600; color: var(--color-text-strong, #111); line-height: 1.3; }
.hero-meta { display: flex; align-items: center; gap: 12px; font-size: 0.85rem; margin-top: 4px; color: var(--color-text-muted, #666); flex-wrap: wrap; }
.hero-pill { display: inline-flex; align-items: center; gap: 4px; padding: 2px 8px; background: var(--color-surface-alt, #f0f0f0); border-radius: 4px; font-size: 0.78rem; }
.hero-value-block { text-align: right; min-width: 140px; }
.hero-value-amount { font-size: 1.4rem; font-weight: 700; color: var(--color-text-strong, #111); }
.hero-currency { font-size: 0.95rem; font-weight: 500; color: var(--color-text-muted, #666); margin-right: 2px; }
.hero-weighted { font-size: 0.75rem; margin-top: 2px; }
.hero-tags { display: flex; gap: 6px; flex-wrap: wrap; align-items: center; }
.muted { color: var(--color-text-muted, #666); }
.weighted-value-display {
    display: flex; align-items: center; gap: 8px;
    height: 40px; padding: 4px 12px;
    background: var(--color-surface-alt, #fafafa);
    border: 1px solid var(--color-border, #d9d9d9);
    border-radius: 6px;
    font-size: 1rem;
}
.weighted-value-display strong { color: var(--color-primary, #1677ff); }

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

.state-label { font-size: 0.875rem; color: var(--color-text); font-weight: 500; }

@media (max-width: 1024px) {
    .form-layout { grid-template-columns: 1fr; }
    .form-nav { position: static; }
    .form-nav :deep(.ant-anchor) { display: flex; flex-wrap: wrap; gap: 4px; padding: 8px; }
    .nav-link { font-size: 0.8rem; }
}
@media (max-width: 768px) {
    .hero-row { flex-direction: column; align-items: flex-start; }
    .hero-value-block { text-align: left; }
}
</style>
