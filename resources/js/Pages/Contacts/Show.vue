<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert, Table, Empty, Button, Row, Col,
} from 'ant-design-vue';
import {
    HistoryOutlined, TeamOutlined, PlusOutlined,
    FunnelPlotOutlined, CheckCircleOutlined, DollarOutlined, MailOutlined,
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
    contact: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
    activities: { type: Array,  default: () => [] },
    deals:      { type: Array,  default: () => [] },
    canManageActivities: { type: Boolean, default: false },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDate, formatDateTimeFull } = useDateFormat();
const fmtDate = (d) => d ? formatDate(d) : '—';

const isDeleted = computed(() => !!props.contact.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);

const fmtMoney = (n) => {
    if (n == null || n === '') return '—';
    const v = Number(n);
    if (!Number.isFinite(v)) return '—';
    return v.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const dealsWonCount = computed(() => (props.deals ?? []).filter(d => d.status === 'won').length);
const totalDealsValue = computed(() => (props.deals ?? []).reduce((acc, d) => acc + Number(d.value || 0), 0));

const hasWork = computed(() => !!(props.contact.job_title || props.contact.department || props.contact.seniority_level || props.contact.decision_role));
const hasClassification = computed(() => !!(props.contact.lifecycle_stage || props.contact.lead_source || props.contact.rating || props.contact.score != null || props.contact.relationship_strength || props.contact.last_engagement_at));
const hasAssistant = computed(() => !!(props.contact.assistant_name || props.contact.assistant_email || props.contact.assistant_phone));
const hasSocial = computed(() => !!(props.contact.linkedin_url || props.contact.twitter_handle || props.contact.photo_url || props.contact.external_id));

const kpiTiles = computed(() => [
    { icon: FunnelPlotOutlined,    label: 'Deals',         value: (props.deals?.length ?? 0).toString() },
    { icon: CheckCircleOutlined,   label: 'Deals ganados', value: dealsWonCount.value.toString(), color: dealsWonCount.value > 0 ? 'success' : 'default' },
    { icon: DollarOutlined,        label: 'Valor total',   value: fmtMoney(totalDealsValue.value), color: 'primary' },
    { icon: MailOutlined,          label: 'Actividades',   value: (props.activities?.length ?? 0).toString() },
]);

const dealStatusColor = (s) => ({ open: 'blue', won: 'success', lost: 'red', dormant: 'default' }[s] ?? 'default');
</script>

<template>
    <Head :title="contact.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('crm.contacts.index')"
            :title="contact.name"
            :icon-bg="iconBg"
        >
            <template #icon><TeamOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="contact.is_active ? 'success' : 'default'" :bordered="false">
                        {{ contact.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="muted">ID #{{ contact.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="contacts"
                    route-prefix="crm"
                    :slug="contact.slug"
                    :id="contact.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('contacts.edit')"
                    :can-delete="can('contacts.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(contact.deleted_at) }}</div>
                <div v-if="contact.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ contact.deleter.name }}
                </div>
                <div v-if="contact.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ contact.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="contacts" route-prefix="crm" />
            </template>
        </Alert>

        <div style="margin-bottom: 16px;">
            <TagPicker
                :taggable="{ type: 'App\\Models\\Contact', id: contact.id }"
                :initial-tags="contact.tags ?? []"
                :can-edit="can('contacts.edit')"
            />
        </div>

        <KPITiles :tiles="kpiTiles" />

        <QuickNoteWidget
            :activitable="{ type: 'App\\Models\\Contact', id: contact.id }"
            :can-create="canManageActivities"
        />

        <EntityShowTabs
            :show-history="canSeeAudit"
            :history-count="activity.length"
            :show-activities="true"
            :activities-count="activities.length"
            :show-deals="can('deals.view')"
            :deals-count="deals.length"
        
        :record="contact"
        :activity="activity"
    >
            <template #activities>
                <ActivitiesPanel
                    :activitable="{ type: 'App\\Models\\Contact', id: contact.id }"
                    :activities="activities"
                    :can-edit="canManageActivities"
                    :can-delete="canManageActivities"
                />
            </template>

            <template #deals>
                <div v-if="can('deals.create') && contact.company_id" class="tab-toolbar">
                    <Link :href="route('crm.deals.create', { company_id: contact.company_id, contact_id: contact.id })">
                        <Button type="primary"><PlusOutlined /> {{ $t('deals.new') ?? 'Nueva oportunidad' }}</Button>
                    </Link>
                </div>
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <Empty v-if="deals.length === 0" :description="$t('deals.empty_for_contact') ?? 'Este contacto todavía no tiene oportunidades asociadas.'" style="padding: 32px" />
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

            <template #general>
                <Row :gutter="[16, 16]">
                    <!-- DATOS PERSONALES -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('contacts.section_general')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="contact.salutation" :label="$t('contacts.salutation')">{{ contact.salutation }}</DescriptionsItem>
                                <DescriptionsItem :label="$t('contacts.name')">{{ contact.name }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.first_name" :label="$t('contacts.first_name')">{{ contact.first_name }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.middle_name" :label="$t('contacts.middle_name')">{{ contact.middle_name }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.last_name" :label="$t('contacts.last_name')">{{ contact.last_name }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.nickname" :label="$t('contacts.nickname')">{{ contact.nickname }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.gender" :label="$t('contacts.gender')">{{ contact.gender }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.date_of_birth" :label="$t('contacts.date_of_birth')">{{ fmtDate(contact.date_of_birth) }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.description" :label="$t('contacts.description')">{{ contact.description }}</DescriptionsItem>
                                <DescriptionsItem :label="$t('contacts.is_active')">
                                    <Tag :color="contact.is_active ? 'success' : 'default'" :bordered="false">
                                        {{ contact.is_active ? $t('global.active') : $t('global.inactive') }}
                                    </Tag>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- CONTACTO -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('contacts.section_contact')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="contact.primary_email" :label="$t('contacts.primary_email')">
                                    <a :href="`mailto:${contact.primary_email}`">{{ contact.primary_email }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.primary_phone" :label="$t('contacts.primary_phone')">
                                    <a :href="`tel:${contact.primary_phone}`">{{ contact.primary_phone }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.mobile_phone" :label="$t('contacts.mobile_phone')">
                                    <a :href="`tel:${contact.mobile_phone}`">{{ contact.mobile_phone }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.timezone" :label="$t('contacts.timezone')">{{ contact.timezone }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.preferred_language" :label="$t('contacts.preferred_language')">
                                    {{ contact.preferred_language.name }}
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.preferred_channel" :label="$t('contacts.preferred_channel')">
                                    <Tag :bordered="false">{{ contact.preferred_channel }}</Tag>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- TRABAJO -->
                    <Col v-if="hasWork" :xs="24" :md="12">
                        <Card :title="$t('contacts.section_work')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="contact.job_title" :label="$t('contacts.job_title')">{{ contact.job_title }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.department" :label="$t('contacts.department')">{{ contact.department }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.seniority_level" :label="$t('contacts.seniority_level')">
                                    <Tag :bordered="false">{{ contact.seniority_level }}</Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.decision_role" :label="$t('contacts.decision_role')">{{ contact.decision_role }}</DescriptionsItem>
                                <DescriptionsItem :label="$t('contacts.is_decision_maker')">
                                    <Tag :color="contact.is_decision_maker ? 'purple' : 'default'" :bordered="false">
                                        {{ contact.is_decision_maker ? $t('global.yes') ?? 'Sí' : $t('global.no') ?? 'No' }}
                                    </Tag>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- EMPRESA -->
                    <Col v-if="contact.company || contact.reports_to" :xs="24" :md="12">
                        <Card :title="$t('contacts.section_company')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="contact.company" :label="$t('contacts.company')">
                                    <Link :href="route('crm.companies.show', contact.company.slug)">
                                        {{ contact.company.name }}
                                    </Link>
                                </DescriptionsItem>
                                <DescriptionsItem :label="$t('contacts.is_primary_for_company')">
                                    <Tag :color="contact.is_primary_for_company ? 'success' : 'default'" :bordered="false">
                                        {{ contact.is_primary_for_company ? $t('global.yes') ?? 'Sí' : $t('global.no') ?? 'No' }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.reports_to" :label="$t('contacts.reports_to')">
                                    <Link :href="route('crm.contacts.show', contact.reports_to.slug)">
                                        {{ contact.reports_to.name }}
                                    </Link>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.owner" :label="$t('contacts.owner')">
                                    {{ contact.owner.name }}
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- CLASIFICACION CRM -->
                    <Col v-if="hasClassification" :xs="24" :md="12">
                        <Card :title="$t('contacts.section_classification')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="contact.lifecycle_stage" :label="$t('contacts.lifecycle_stage')">
                                    <Tag :bordered="false">{{ contact.lifecycle_stage }}</Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.lead_source" :label="$t('contacts.lead_source')">{{ contact.lead_source }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.rating" :label="$t('contacts.rating')">
                                    <Tag :color="contact.rating === 'hot' ? 'red' : contact.rating === 'warm' ? 'orange' : 'blue'" :bordered="false">
                                        {{ contact.rating }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.score != null" :label="$t('contacts.score') ?? 'Score'">{{ contact.score }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.relationship_strength" :label="$t('contacts.relationship_strength')">
                                    <Tag :color="contact.relationship_strength === 'champion' ? 'gold' : 'default'" :bordered="false">
                                        {{ contact.relationship_strength }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.last_engagement_at" :label="$t('contacts.last_engagement_at')">
                                    {{ fmt(contact.last_engagement_at) }}
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- COMPLIANCE -->
                    <Col :xs="24" :md="12">
                        <Card :title="$t('contacts.section_communication')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem :label="$t('contacts.do_not_contact')">
                                    <Tag :color="contact.do_not_contact ? 'red' : 'success'" :bordered="false">
                                        {{ contact.do_not_contact ? $t('global.yes') ?? 'Sí' : $t('global.no') ?? 'No' }}
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem :label="$t('contacts.email_opt_in')">
                                    <Tag :color="contact.email_opt_in ? 'success' : 'default'" :bordered="false">
                                        {{ contact.email_opt_in ? '✓' : '✗' }} Email
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem :label="$t('contacts.sms_opt_in')">
                                    <Tag :color="contact.sms_opt_in ? 'success' : 'default'" :bordered="false">
                                        {{ contact.sms_opt_in ? '✓' : '✗' }} SMS
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem :label="$t('contacts.whatsapp_opt_in')">
                                    <Tag :color="contact.whatsapp_opt_in ? 'success' : 'default'" :bordered="false">
                                        {{ contact.whatsapp_opt_in ? '✓' : '✗' }} WhatsApp
                                    </Tag>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.gdpr_consent_at" :label="$t('contacts.gdpr_consent_at')">
                                    {{ fmt(contact.gdpr_consent_at) }}
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.marketing_opt_in_at" :label="$t('contacts.marketing_opt_in_at')">
                                    {{ fmt(contact.marketing_opt_in_at) }}
                                    <span v-if="contact.marketing_opt_in_source" class="muted">· {{ contact.marketing_opt_in_source }}</span>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.unsubscribed_at" :label="$t('contacts.unsubscribed_at')">
                                    {{ fmt(contact.unsubscribed_at) }}
                                    <div v-if="contact.unsubscribed_reason" class="muted" style="font-size: 0.82rem">{{ contact.unsubscribed_reason }}</div>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- ASISTENTE -->
                    <Col v-if="hasAssistant" :xs="24" :md="12">
                        <Card :title="$t('contacts.section_assistant')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="contact.assistant_name" :label="$t('contacts.assistant_name')">{{ contact.assistant_name }}</DescriptionsItem>
                                <DescriptionsItem v-if="contact.assistant_email" :label="$t('contacts.assistant_email')">
                                    <a :href="`mailto:${contact.assistant_email}`">{{ contact.assistant_email }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.assistant_phone" :label="$t('contacts.assistant_phone')">
                                    <a :href="`tel:${contact.assistant_phone}`">{{ contact.assistant_phone }}</a>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>

                    <!-- SOCIAL -->
                    <Col v-if="hasSocial" :xs="24" :md="12">
                        <Card :title="$t('contacts.section_social')" :bodyStyle="{ padding: 0 }" class="info-card">
                            <Descriptions :column="1" bordered :labelStyle="{ width: '170px' }" size="small">
                                <DescriptionsItem v-if="contact.linkedin_url" label="LinkedIn">
                                    <a :href="contact.linkedin_url" target="_blank" rel="noopener">{{ contact.linkedin_url }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.twitter_handle" label="Twitter">
                                    <a :href="`https://twitter.com/${contact.twitter_handle.replace('@','')}`" target="_blank" rel="noopener">@{{ contact.twitter_handle.replace('@','') }}</a>
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.photo_url" :label="$t('contacts.photo_url')">
                                    <img :src="contact.photo_url" :alt="contact.name" class="contact-photo-preview" />
                                </DescriptionsItem>
                                <DescriptionsItem v-if="contact.external_id" :label="$t('contacts.external_id')">
                                    <code>{{ contact.external_id }}</code>
                                </DescriptionsItem>
                            </Descriptions>
                        </Card>
                    </Col>
                </Row>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(contact.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="contact.creator" :label="$t('global.created_by')">
                            {{ contact.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(contact.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(contact.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="contact.deleter" :label="$t('global.deleted_by')">
                                {{ contact.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ contact.deleted_description || '—' }}
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
.contact-photo-preview {
    width: 80px;
    height: 80px;
    object-fit: cover;
    border-radius: 50%;
    border: 2px solid var(--color-border-soft, #f0f0f0);
}


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
