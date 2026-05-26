<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert, Table, Empty, Button, Row, Col,
} from 'ant-design-vue';
import {
    HistoryOutlined, TeamOutlined, PlusOutlined,
    UserOutlined, FunnelPlotOutlined, FileDoneOutlined, DollarOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import KPITiles from '@/Components/Common/KPITiles.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import ActivitiesPanel from '@/Components/Crm/Activities/ActivitiesPanel.vue';
import QuickNoteWidget from '@/Components/Crm/Activities/QuickNoteWidget.vue';
import TagPicker from '@/Components/Common/TagPicker.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    company: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
    activities: { type: Array,  default: () => [] },
    contacts:   { type: Array,  default: () => [] },
    deals:      { type: Array,  default: () => [] },
    quotes:     { type: Array,  default: () => [] },
    invoices:   { type: Array,  default: () => [] },
    canManageActivities: { type: Boolean, default: false },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDate, formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.company.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);

// Format money simple — sin grupos de miles especificos del locale para
// mantener consistencia entre tabs. Modulos individuales tienen su propio
// formatter mas elegante; aqui solo necesitamos legibilidad rapida.
const fmtMoney = (n) => {
    if (n == null || n === '') return '—';
    const v = Number(n);
    if (!Number.isFinite(v)) return '—';
    return v.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Color por status — reusamos las mismas familias que el resto del CRM para
// que el usuario aprenda los codigos de color una sola vez (won=verde,
// lost/rejected=rojo, draft/pending=default, sent/open=blue, etc.).
const dealsWonCount = computed(() => (props.deals ?? []).filter(d => d.status === 'won').length);
const totalInvoiced = computed(() => (props.invoices ?? []).reduce((acc, inv) => acc + Number(inv.total ?? inv.grand_total ?? 0), 0));

const fmtDate = (d) => d ? formatDate(d) : '—';
const hasSocial = computed(() => !!(props.company.linkedin_url || props.company.facebook_url || props.company.twitter_handle || props.company.instagram_url || props.company.logo_url));

const kpiTiles = computed(() => [
    { icon: UserOutlined,      label: 'Contactos',           value: (props.contacts?.length ?? 0).toString() },
    { icon: FunnelPlotOutlined,label: 'Deals',               value: (props.deals?.length ?? 0).toString(), hint: dealsWonCount.value > 0 ? `${dealsWonCount.value} ganados` : null,
      color: dealsWonCount.value > 0 ? 'success' : 'default' },
    { icon: FileDoneOutlined,  label: 'Cotizaciones',        value: (props.quotes?.length ?? 0).toString() },
    { icon: DollarOutlined,    label: 'Facturado',           value: fmtMoney(totalInvoiced.value), color: 'primary' },
]);

const dealStatusColor = (s) => ({ open: 'blue', won: 'success', lost: 'red', dormant: 'default' }[s] ?? 'default');
const docStatusColor  = (s) => ({
    draft: 'default', sent: 'blue', accepted: 'success', rejected: 'red', expired: 'orange', revised: 'purple',
    pending: 'default', paid: 'success', partial: 'orange', overdue: 'red', voided: 'default',
}[s] ?? 'default');
</script>

<template>
    <Head :title="company.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('crm.companies.index')"
            :title="company.name"
            :icon-bg="iconBg"
        >
            <template #icon><TeamOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="company.is_active ? 'success' : 'default'" :bordered="false">
                        {{ company.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="muted">ID #{{ company.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="companies"
                    route-prefix="crm"
                    :slug="company.slug"
                    :id="company.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('companies.edit')"
                    :can-delete="can('companies.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(company.deleted_at) }}</div>
                <div v-if="company.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ company.deleter.name }}
                </div>
                <div v-if="company.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ company.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="companies" route-prefix="crm" />
            </template>
        </Alert>

        <div style="margin-bottom: 16px;">
            <TagPicker
                :taggable="{ type: 'App\\Models\\Company', id: company.id }"
                :initial-tags="company.tags ?? []"
                :can-edit="can('companies.edit')"
            />
        </div>

        <KPITiles :tiles="kpiTiles" />

        <QuickNoteWidget
            :activitable="{ type: 'App\\Models\\Company', id: company.id }"
            :can-create="canManageActivities"
        />

        <EntityShowTabs
            :show-history="canSeeAudit"
            :history-count="activity.length"
            :show-activities="true"
            :activities-count="activities.length"
            :show-contacts="can('contacts.view')"
            :contacts-count="contacts.length"
            :show-deals="can('deals.view')"
            :deals-count="deals.length"
            :show-quotes="can('quotes.view')"
            :quotes-count="quotes.length"
            :show-invoices="can('invoices.view')"
            :invoices-count="invoices.length"
        
        :record="company"
        :activity="activity"
    >
            <template #activities>
                <ActivitiesPanel
                    :activitable="{ type: 'App\\Models\\Company', id: company.id }"
                    :activities="activities"
                    :can-edit="canManageActivities"
                    :can-delete="canManageActivities"
                />
            </template>

            <template #contacts>
                <div v-if="can('contacts.create')" class="tab-toolbar">
                    <Link :href="route('crm.contacts.create', { company_id: company.id })">
                        <Button type="primary"><PlusOutlined /> {{ $t('contacts.new') ?? 'Nuevo contacto' }}</Button>
                    </Link>
                </div>
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <Empty v-if="contacts.length === 0" :description="$t('contacts.empty_for_company') ?? 'Sin contactos'" style="padding: 32px" />
                    <Table v-else :dataSource="contacts" rowKey="id" size="middle" :pagination="false">
                        <Table.Column :title="$t('contacts.name')" key="name">
                            <template #default="{ record }">
                                <Link :href="route('crm.contacts.show', record.slug)">{{ record.name }}</Link>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('contacts.email')" data-index="email" />
                        <Table.Column :title="$t('contacts.phone')" data-index="phone" />
                        <Table.Column :title="$t('contacts.position')" data-index="position" />
                        <Table.Column :title="$t('global.status')" :width="100">
                            <template #default="{ record }">
                                <Tag :color="record.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ record.is_active ? $t('global.active') : $t('global.inactive') }}
                                </Tag>
                            </template>
                        </Table.Column>
                    </Table>
                </Card>
            </template>

            <template #deals>
                <div v-if="can('deals.create')" class="tab-toolbar">
                    <Link :href="route('crm.deals.create', { company_id: company.id })">
                        <Button type="primary"><PlusOutlined /> {{ $t('deals.new') ?? 'Nueva oportunidad' }}</Button>
                    </Link>
                </div>
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <Empty v-if="deals.length === 0" :description="$t('deals.empty_for_company') ?? 'Sin deals'" style="padding: 32px" />
                    <Table v-else :dataSource="deals" rowKey="id" size="middle" :pagination="false">
                        <Table.Column :title="$t('deals.name')" key="name">
                            <template #default="{ record }">
                                <Link :href="route('crm.deals.show', record.slug)">{{ record.name }}</Link>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('global.status')" :width="120">
                            <template #default="{ record }">
                                <Tag :color="dealStatusColor(record.status)" :bordered="false">{{ record.status }}</Tag>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('deals.value')" :width="160" align="right">
                            <template #default="{ record }">
                                <strong>{{ record.currency }} {{ fmtMoney(record.value) }}</strong>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('deals.expected_close_date')" :width="150" data-index="expected_close_date" />
                    </Table>
                </Card>
            </template>

            <template #quotes>
                <div v-if="can('quotes.create')" class="tab-toolbar">
                    <Link :href="route('business_management.quotes.create', { company_id: company.id })">
                        <Button type="primary"><PlusOutlined /> {{ $t('quotes.new') ?? 'Nueva cotizacion' }}</Button>
                    </Link>
                </div>
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <Empty v-if="quotes.length === 0" :description="$t('quotes.empty_for_company') ?? 'Sin cotizaciones'" style="padding: 32px" />
                    <Table v-else :dataSource="quotes" rowKey="id" size="middle" :pagination="false">
                        <Table.Column :title="$t('quotes.reference')" key="reference">
                            <template #default="{ record }">
                                <Link :href="route('business_management.quotes.show', record.slug)">
                                    {{ record.reference ?? '#' + record.id }}
                                </Link>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('global.status')" :width="120">
                            <template #default="{ record }">
                                <Tag :color="docStatusColor(record.status)" :bordered="false">{{ record.status }}</Tag>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('quotes.grand_total')" :width="160" align="right">
                            <template #default="{ record }">
                                <strong>{{ record.currency }} {{ fmtMoney(record.total) }}</strong>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('quotes.issue_date')" :width="120">
                            <template #default="{ record }">{{ record.issue_date ? formatDate(record.issue_date) : '—' }}</template>
                        </Table.Column>
                        <Table.Column :title="$t('quotes.valid_until')" :width="120">
                            <template #default="{ record }">{{ record.valid_until ? formatDate(record.valid_until) : '—' }}</template>
                        </Table.Column>
                    </Table>
                </Card>
            </template>

            <template #invoices>
                <div v-if="can('invoices.create')" class="tab-toolbar">
                    <Link :href="route('business_management.invoices.create', { company_id: company.id })">
                        <Button type="primary"><PlusOutlined /> {{ $t('invoices.new') ?? 'Nueva factura' }}</Button>
                    </Link>
                </div>
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <Empty v-if="invoices.length === 0" :description="$t('invoices.empty_for_company') ?? 'Sin facturas'" style="padding: 32px" />
                    <Table v-else :dataSource="invoices" rowKey="id" size="middle" :pagination="false">
                        <Table.Column :title="$t('invoices.reference')" key="reference">
                            <template #default="{ record }">
                                <Link :href="route('business_management.invoices.show', record.slug)">
                                    {{ record.reference ?? '#' + record.id }}
                                </Link>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('global.status')" :width="120">
                            <template #default="{ record }">
                                <Tag :color="docStatusColor(record.status)" :bordered="false">{{ record.status }}</Tag>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('invoices.grand_total')" :width="160" align="right">
                            <template #default="{ record }">
                                <strong>{{ record.currency }} {{ fmtMoney(record.total) }}</strong>
                            </template>
                        </Table.Column>
                        <Table.Column :title="$t('invoices.issue_date')" :width="120">
                            <template #default="{ record }">{{ record.issue_date ? formatDate(record.issue_date) : '—' }}</template>
                        </Table.Column>
                        <Table.Column :title="$t('invoices.due_date')" :width="120">
                            <template #default="{ record }">{{ record.due_date ? formatDate(record.due_date) : '—' }}</template>
                        </Table.Column>
                    </Table>
                </Card>
            </template>

            <template #general>
                <Row :gutter="[16, 16]">
                    <!-- DATOS GENERALES -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('companies.section_general')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem :label="$t('companies.name')">{{ company.name }}</DescriptionsItem>
                                <DescriptionsItem v-if="company.legal_name" :label="$t('companies.legal_name')">{{ company.legal_name }}</DescriptionsItem>
                                <DescriptionsItem v-if="company.tax_id" :label="$t('companies.tax_id')">
                                    <code>{{ company.tax_id }}</code>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.description" :label="$t('companies.description')">{{ company.description }}</DescriptionsItem>
                                <DescriptionsItem v-if="company.industry" :label="$t('companies.industry')">{{ company.industry.name }}</DescriptionsItem>
                                <DescriptionsItem v-if="company.country" :label="$t('companies.country')">
                                    {{ company.country.name }} <span v-if="company.country.iso2" class="muted">({{ company.country.iso2 }})</span>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.founded_year" :label="$t('companies.founded_year')">{{ company.founded_year }}</DescriptionsItem>
                                <DescriptionsItem v-if="company.employee_count" :label="$t('companies.employee_count')">{{ company.employee_count }}</DescriptionsItem>
                                <DescriptionsItem v-if="company.reference" :label="$t('companies.reference')">
                                    <code>{{ company.reference }}</code>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.external_id" :label="$t('companies.external_id')">
                                    <code>{{ company.external_id }}</code>
                                </DescriptionsItem>
                                <DescriptionsItem :label="$t('companies.is_active')">
                                    <Tag :color="company.is_active ? 'success' : 'default'" :bordered="false">
                                        {{ company.is_active ? $t('global.active') : $t('global.inactive') }}
                                    </Tag>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- CLASIFICACIÓN -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('companies.section_classification')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="company.company_type" :label="$t('companies.company_type')">
                                    <Tag :bordered="false">{{ $t('companies.company_type_options.' + company.company_type) }}</Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.lifecycle_stage" :label="$t('companies.lifecycle_stage')">
                                    <Tag :bordered="false">{{ $t('companies.lifecycle_stage_options.' + company.lifecycle_stage) }}</Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.rating" :label="$t('companies.rating')">
                                    <Tag :color="company.rating === 'hot' ? 'red' : company.rating === 'warm' ? 'orange' : company.rating === 'cold' ? 'blue' : 'default'" :bordered="false">
                                        {{ $t('companies.rating_options.' + company.rating) }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.score != null" :label="$t('companies.score') ?? 'Score'">{{ company.score }}</DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- PRIORIZACIÓN + POST-VENTA -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('companies.section_priority')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem :label="$t('companies.is_vip')">
                                    <Tag :color="company.is_vip ? 'gold' : 'default'" :bordered="false">
                                        {{ company.is_vip ? $t('global.yes') ?? 'Sí' : $t('global.no') ?? 'No' }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.priority" :label="$t('companies.priority')">
                                    <Tag :color="company.priority === 'critical' ? 'red' : company.priority === 'high' ? 'orange' : 'default'" :bordered="false">
                                        {{ $t('companies.priority_options.' + company.priority) }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.account_manager" :label="$t('companies.account_manager')">
                                    {{ company.account_manager.name }}
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.owner" :label="$t('companies.owner')">
                                    {{ company.owner.name }}
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.customer_since" :label="$t('companies.customer_since')">
                                    {{ fmtDate(company.customer_since) }}
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- FINANCIERO -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('companies.section_financial')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="company.preferred_currency_code" :label="$t('companies.preferred_currency_code')">
                                    <code>{{ company.preferred_currency_code }}</code>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.payment_terms_days != null" :label="$t('companies.payment_terms_days')">
                                    {{ company.payment_terms_days }} días
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.credit_limit != null" :label="$t('companies.credit_limit')">
                                    {{ fmtMoney(company.credit_limit) }}
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.discount_default_pct != null" :label="$t('companies.discount_default_pct')">
                                    {{ company.discount_default_pct }}%
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.annual_revenue != null" :label="$t('companies.annual_revenue')">
                                    {{ fmtMoney(company.annual_revenue) }}
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.default_payment_method" :label="$t('companies.default_payment_method')">
                                    {{ company.default_payment_method.name }}
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- FISCAL + LEGAL -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('companies.section_legal')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="company.legal_entity_type" :label="$t('companies.legal_entity_type')">{{ company.legal_entity_type }}</DescriptionsItem>
                                <DescriptionsItem :label="$t('companies.tax_exempt')">
                                    <Tag :color="company.tax_exempt ? 'orange' : 'default'" :bordered="false">
                                        {{ company.tax_exempt ? $t('global.yes') ?? 'Sí' : $t('global.no') ?? 'No' }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.tax_exempt && company.tax_exempt_reason" :label="$t('companies.tax_exempt_reason')">
                                    {{ company.tax_exempt_reason }}
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.tax_status" :label="$t('companies.tax_status')">{{ company.tax_status }}</DescriptionsItem>
                                <DescriptionsItem v-if="company.bank_account_info" :label="$t('companies.bank_account_info')">
                                    <pre class="bank-info">{{ company.bank_account_info }}</pre>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- HEALTH + CHURN -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('companies.section_health')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="company.account_status" :label="$t('companies.account_status')">
                                    <Tag :color="company.account_status === 'active' ? 'success' : company.account_status === 'at_risk' ? 'orange' : company.account_status === 'churned' ? 'red' : 'default'" :bordered="false">
                                        {{ $t('companies.account_status_options.' + company.account_status) }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.health_score != null" :label="$t('companies.health_score')">
                                    <span :class="{ 'text-danger': company.health_score < 40, 'text-warn': company.health_score >= 40 && company.health_score < 70, 'text-success': company.health_score >= 70 }">
                                        <strong>{{ company.health_score }}</strong> / 100
                                    </span>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.churn_risk" :label="$t('companies.churn_risk')">
                                    <Tag :color="company.churn_risk === 'critical' ? 'red' : company.churn_risk === 'high' ? 'orange' : company.churn_risk === 'medium' ? 'gold' : 'success'" :bordered="false">
                                        {{ $t('companies.churn_risk_options.' + company.churn_risk) }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.referrer_company" :label="$t('companies.referrer_company')">
                                    <Link :href="route('crm.companies.show', company.referrer_company.slug)">
                                        {{ company.referrer_company.name }}
                                    </Link>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- CONTACTO + BRANDING -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('companies.section_contact')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="company.billing_email" :label="$t('companies.billing_email')">
                                    <a :href="`mailto:${company.billing_email}`">{{ company.billing_email }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.website" :label="$t('companies.website')">
                                    <a :href="company.website" target="_blank" rel="noopener">{{ company.website }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.domain" :label="$t('companies.domain')">
                                    <code>{{ company.domain }}</code>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.preferred_language" :label="$t('companies.preferred_language_id')">
                                    {{ company.preferred_language.name }} <span v-if="company.preferred_language.code" class="muted">({{ company.preferred_language.code }})</span>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- SOCIAL + BRANDING -->
                    <Col v-if="hasSocial" :xs="24" :md="12">
                        <Card :title="$t('companies.section_social')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="company.linkedin_url" label="LinkedIn">
                                    <a :href="company.linkedin_url" target="_blank" rel="noopener">{{ company.linkedin_url }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.facebook_url" label="Facebook">
                                    <a :href="company.facebook_url" target="_blank" rel="noopener">{{ company.facebook_url }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.twitter_handle" label="Twitter">
                                    <a :href="`https://twitter.com/${company.twitter_handle.replace('@','')}`" target="_blank" rel="noopener">@{{ company.twitter_handle.replace('@','') }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.instagram_url" label="Instagram">
                                    <a :href="company.instagram_url" target="_blank" rel="noopener">{{ company.instagram_url }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="company.logo_url" :label="$t('companies.logo_url')">
                                    <img :src="company.logo_url" :alt="company.name" class="company-logo-preview" />
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- JERARQUIA -->
                    <Col v-if="company.parent_company" :xs="24" :md="12">
                        <Card :title="$t('companies.section_hierarchy') ?? 'Jerarquía'" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem :label="$t('companies.parent_company')">
                                    <Link :href="route('crm.companies.show', company.parent_company.slug)">
                                        {{ company.parent_company.name }}
                                    </Link>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>
                </Row>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(company.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="company.creator" :label="$t('global.created_by')">
                            {{ company.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(company.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(company.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="company.deleter" :label="$t('global.deleted_by')">
                                {{ company.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ company.deleted_description || '—' }}
                            </DescriptionsItem>
                        </template>
                    </Descriptions>
                </Card>

                <Card :bodyStyle="{ padding: 16 }" class="info-card">
                    <template #title>
                        <HistoryOutlined /> {{ $t('global.recent_activity') }}
                    </template>
                    <ActivityTimeline :activity="activity" />
                </Card>
            </template>
        </EntityShowTabs>
    </div>
</template>

<style scoped>
.company-logo-preview {
    max-width: 120px;
    max-height: 60px;
    object-fit: contain;
    border-radius: 4px;
    background: var(--color-surface-alt, #fafafa);
    padding: 4px 8px;
}
.bank-info {
    margin: 0;
    padding: 8px;
    background: var(--color-surface-alt, #fafafa);
    border-radius: 4px;
    font-size: 0.82rem;
    white-space: pre-wrap;
    font-family: var(--font-mono, monospace);
}
.text-success { color: #52c41a; }
.text-warn    { color: #faad14; }
.text-danger  { color: #ff4d4f; }


.show-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 16px; border-radius: 6px; }
.tab-toolbar { display: flex; justify-content: flex-end; margin-bottom: 12px; }

@media (max-width: 767px) {
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
