<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert, Button, Row, Col,
} from 'ant-design-vue';
import {
    HistoryOutlined, TeamOutlined, DollarOutlined, FunnelPlotOutlined,
    CheckCircleOutlined, FileTextOutlined, FileDoneOutlined, PlusOutlined,
    CheckCircleFilled, ClockCircleFilled, MinusCircleOutlined, StopOutlined,
    PercentageOutlined, CalendarOutlined, LineChartOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import ActivitiesPanel from '@/Components/Crm/Activities/ActivitiesPanel.vue';
import QuickNoteWidget from '@/Components/Crm/Activities/QuickNoteWidget.vue';
import TagPicker from '@/Components/Common/TagPicker.vue';
import DocumentFlow from '@/Components/Common/DocumentFlow.vue';
import { useI18n } from '@/Plugins/i18n';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

const { t: $t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    deal: { type: Object, required: true },
    activity:   { type: Array,  default: () => [] },
    activities: { type: Array,  default: () => [] },
    quotes:     { type: Array,  default: () => [] },
    canManageActivities: { type: Boolean, default: false },
    canCreateQuote:      { type: Boolean, default: false },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDate, formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.deal.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

const fmt = (d) => formatDateTimeFull(d);
const fmtDate = (d) => d ? formatDate(d) : '—';
const fmtMoney = (n, currency) => {
    if (n == null || n === '') return '—';
    const num = Number(n);
    if (Number.isNaN(num)) return '—';
    const formatted = new Intl.NumberFormat(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(num);
    return currency ? `${currency} ${formatted}` : formatted;
};

const statusColor = (s) => {
    switch (s) {
        case 'won':     return 'success';
        case 'lost':    return 'error';
        case 'dormant': return 'warning';
        default:        return 'processing';
    }
};

const stages = computed(() => props.deal.pipeline?.stages ?? []);
const currentStageIndex = computed(() => {
    if (!props.deal.stage) return -1;
    return stages.value.findIndex(s => s.id === props.deal.stage.id);
});

// Para el DocumentFlow horizontal: convertir los stages del pipeline a la
// estructura { value, label, isTerminal?, isError? } que el componente espera.
// Si el deal esta won/lost, ese es el terminal — sino, el current es el
// stage_id actual.
const flowSteps = computed(() => {
    const steps = stages.value.map(s => ({
        value: String(s.id),
        label: s.name,
        hint: s.probability_pct != null ? `${s.probability_pct}%` : null,
    }));
    if (props.deal.status === 'won') {
        steps.push({ value: '__won', label: $t('deals.status_options.won') ?? 'Ganado', isTerminal: true });
    } else if (props.deal.status === 'lost') {
        steps.push({ value: '__lost', label: $t('deals.status_options.lost') ?? 'Perdido', isError: true });
    }
    return steps;
});

const currentFlowStatus = computed(() => {
    if (props.deal.status === 'won') return '__won';
    if (props.deal.status === 'lost') return '__lost';
    return props.deal.stage ? String(props.deal.stage.id) : '';
});

const stageState = (stage, idx) => {
    const cur = currentStageIndex.value;
    if (cur < 0) return 'future';
    if (idx < cur) return 'done';
    if (idx === cur) {
        if (stage.is_won)  return 'won';
        if (stage.is_lost) return 'lost';
        return 'current';
    }
    return 'future';
};

const stageItemClass = (stage, idx) => {
    const st = stageState(stage, idx);
    return `stage-item stage-item--${st}`;
};

const completedCount = computed(() => {
    if (currentStageIndex.value < 0) return 0;
    const includeCurrent = props.deal.status === 'won' ? 1 : 0;
    return currentStageIndex.value + includeCurrent;
});

const totalStagesForProgress = computed(() => {
    return stages.value.filter(s => !s.is_lost).length || stages.value.length;
});

const progressPct = computed(() => {
    if (!totalStagesForProgress.value) return 0;
    return Math.round((completedCount.value / totalStagesForProgress.value) * 100);
});
</script>

<template>
    <Head :title="deal.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('crm.deals.index')"
            :title="deal.name"
            :icon-bg="iconBg"
        >
            <template #icon><TeamOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="statusColor(deal.status)" :bordered="false">
                        {{ $t(`deals.status_options.${deal.status}`) }}
                    </Tag>
                    <Tag v-if="deal.pipeline"
                        :bordered="false"
                        :style="{ background: deal.pipeline.color || '#888', color: '#fff', borderColor: 'transparent' }"
                    >
                        {{ deal.pipeline.name }}
                    </Tag>
                    <span class="muted">ID #{{ deal.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="deals"
                    route-prefix="crm"
                    :slug="deal.slug"
                    :id="deal.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('deals.edit')"
                    :can-delete="can('deals.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(deal.deleted_at) }}</div>
                <div v-if="deal.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ deal.deleter.name }}
                </div>
                <div v-if="deal.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ deal.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="deals" route-prefix="crm" />
            </template>
        </Alert>

        <div style="margin-bottom: 16px;">
            <TagPicker
                :taggable="{ type: 'App\\Models\\Deal', id: deal.id }"
                :initial-tags="deal.tags ?? []"
                :can-edit="can('deals.edit')"
            />
        </div>

        <Row :gutter="[16, 16]" class="kpi-row">
            <Col :xs="12" :md="6">
                <Card :bodyStyle="{ padding: '16px 20px' }" class="kpi-card kpi-card--money">
                    <div class="kpi-card__head"><DollarOutlined /> {{ $t('deals.value') }}</div>
                    <div class="kpi-card__value">{{ fmtMoney(deal.value, deal.currency_code) }}</div>
                </Card>
            </Col>
            <Col :xs="12" :md="6">
                <Card :bodyStyle="{ padding: '16px 20px' }" class="kpi-card kpi-card--prob">
                    <div class="kpi-card__head"><PercentageOutlined /> {{ $t('deals.probability_pct') }}</div>
                    <div class="kpi-card__value">{{ deal.probability_pct ?? 0 }}%</div>
                </Card>
            </Col>
            <Col :xs="12" :md="6">
                <Card :bodyStyle="{ padding: '16px 20px' }" class="kpi-card kpi-card--weighted">
                    <div class="kpi-card__head"><LineChartOutlined /> {{ $t('deals.weighted_value') }}</div>
                    <div class="kpi-card__value">{{ fmtMoney(deal.weighted_value, deal.currency_code) }}</div>
                </Card>
            </Col>
            <Col :xs="12" :md="6">
                <Card :bodyStyle="{ padding: '16px 20px' }" class="kpi-card kpi-card--date">
                    <div class="kpi-card__head"><CalendarOutlined /> {{ $t('deals.expected_close_date') }}</div>
                    <div class="kpi-card__value">{{ fmtDate(deal.expected_close_date) }}</div>
                </Card>
            </Col>
        </Row>

        <DocumentFlow v-if="stages.length > 0"
            :current-status="currentFlowStatus"
            :steps="flowSteps"
            :title="deal.pipeline?.name ?? ''"
        />


        <QuickNoteWidget
            :activitable="{ type: 'App\\Models\\Deal', id: deal.id }"
            :can-create="canManageActivities"
        />

        <EntityShowTabs
            :show-history="canSeeAudit"
            :history-count="activity.length"
            :show-activities="true"
            :activities-count="activities.length"
            :show-quotes="true"
            :quotes-count="quotes.length"
        
        :record="deal"
        :activity="activity"
    >
            <template #activities>
                <ActivitiesPanel
                    :activitable="{ type: 'App\\Models\\Deal', id: deal.id }"
                    :activities="activities"
                    :quotes="quotes"
                    :can-edit="canManageActivities"
                    :can-delete="canManageActivities"
                />
            </template>

            <template #quotes>
                <Card :bodyStyle="{ padding: 16 }" class="info-card">
                    <template #title>
                        <span><FileDoneOutlined /> {{ $t('quotes.plural') }}</span>
                    </template>
                    <template #extra>
                        <Link v-if="canCreateQuote" :href="route('business_management.quotes.create', { deal_id: deal.id })">
                            <Button type="primary" size="small">
                                <PlusOutlined /> {{ $t('deals.create_quote') }}
                            </Button>
                        </Link>
                    </template>

                    <div v-if="quotes.length === 0" class="empty-quotes">
                        <p class="muted">{{ $t('deals.no_quotes_yet') }}</p>
                        <Link v-if="canCreateQuote" :href="route('business_management.quotes.create', { deal_id: deal.id })">
                            <Button type="primary"><PlusOutlined /> {{ $t('deals.create_quote') }}</Button>
                        </Link>
                    </div>

                    <ul v-else class="quotes-list">
                        <li v-for="q in quotes" :key="q.id" class="quote-row">
                            <div class="quote-row-left">
                                <strong>{{ q.reference || '(sin ref)' }}</strong>
                                <span class="muted">— {{ q.name }}</span>
                            </div>
                            <div class="quote-row-meta">
                                <Tag :bordered="false" :color="q.status === 'accepted' ? 'success' : q.status === 'rejected' ? 'error' : q.status === 'sent' ? 'processing' : 'default'">
                                    {{ q.status }}
                                </Tag>
                                <span class="quote-total">{{ q.currency }} {{ q.total }}</span>
                                <Link :href="route('business_management.quotes.show', q.slug)">
                                    <Button size="small">{{ $t('global.view') }}</Button>
                                </Link>
                            </div>
                        </li>
                    </ul>
                </Card>
            </template>
            <template #general>
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title>
                        <FileTextOutlined /> {{ $t('deals.section_general') }}
                    </template>
                    <Descriptions :column="1" bordered :labelStyle="{ width: '200px' }">
                        <DescriptionsItem label="ID">{{ deal.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ deal.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('deals.name')">{{ deal.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('deals.description')">{{ deal.description ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem v-if="deal.external_id" :label="$t('deals.external_id')">
                            <code class="muted">{{ deal.external_id }}</code>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>

                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title>
                        <TeamOutlined /> {{ $t('deals.section_relations') }}
                    </template>
                    <Descriptions :column="1" bordered :labelStyle="{ width: '200px' }">
                        <DescriptionsItem :label="$t('deals.company')">
                            {{ deal.company?.name ?? '—' }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('deals.contact')">
                            <div v-if="deal.contact">
                                <div>{{ deal.contact.name }}</div>
                                <div v-if="deal.contact.primary_email" class="muted">{{ deal.contact.primary_email }}</div>
                                <div v-if="deal.contact.primary_phone" class="muted">{{ deal.contact.primary_phone }}</div>
                            </div>
                            <span v-else class="muted">—</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('deals.owner')">
                            <div v-if="deal.owner">
                                <div>{{ deal.owner.name }}</div>
                                <div class="muted">{{ deal.owner.email }}</div>
                            </div>
                            <span v-else class="muted">—</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('deals.lead_source')">
                            {{ deal.lead_source?.name ?? '—' }}
                        </DescriptionsItem>
                    </Descriptions>
                </Card>

                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title>
                        <CheckCircleOutlined /> {{ $t('deals.section_closing') }}
                    </template>
                    <Descriptions :column="1" bordered :labelStyle="{ width: '200px' }">
                        <DescriptionsItem v-if="deal.won_at" :label="$t('deals.won_at')">
                            <Tag color="success" :bordered="false">{{ fmtDate(deal.won_at) }}</Tag>
                        </DescriptionsItem>
                        <DescriptionsItem v-if="deal.lost_at" :label="$t('deals.lost_at')">
                            <Tag color="error" :bordered="false">{{ fmtDate(deal.lost_at) }}</Tag>
                        </DescriptionsItem>
                        <DescriptionsItem v-if="deal.lost_reason_note" :label="$t('deals.lost_reason_note')">
                            {{ deal.lost_reason_note }}
                        </DescriptionsItem>
                        <DescriptionsItem v-if="!deal.won_at && !deal.lost_at" :label="$t('deals.status')">
                            <Tag :color="statusColor(deal.status)" :bordered="false">
                                {{ $t(`deals.status_options.${deal.status}`) }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(deal.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="deal.creator" :label="$t('global.created_by')">
                            {{ deal.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(deal.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(deal.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="deal.deleter" :label="$t('global.deleted_by')">
                                {{ deal.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ deal.deleted_description || '—' }}
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
.show-page {}
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }

.empty-quotes { padding: 24px; text-align: center; }
.empty-quotes p { margin-bottom: 16px; }
.quotes-list { list-style: none; margin: 0; padding: 0; }
.quote-row {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 12px; gap: 12px;
    border: 1px solid var(--color-border-soft, #f0f0f0);
    border-radius: 6px;
    margin-bottom: 6px;
}
.quote-row:hover { background: var(--color-surface-alt, #fafafa); }
.quote-row-left { display: flex; align-items: center; gap: 6px; min-width: 0; flex: 1; }
.quote-row-meta { display: inline-flex; align-items: center; gap: 12px; flex-shrink: 0; }
.quote-total { font-weight: 600; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 16px; border-radius: 6px; }

/* KPI tiles */
.kpi-row { margin-bottom: 16px; }
.kpi-card { border-radius: 6px; }
.kpi-card__head {
    font-size: 0.75rem;
    color: var(--color-text-muted, #8c8c8c);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex; align-items: center; gap: 6px;
    margin-bottom: 6px;
}
.kpi-card__value {
    font-size: 1.4rem;
    font-weight: 700;
    color: var(--color-text-strong, #262626);
    line-height: 1.2;
}

/* Pipeline card */
.pipeline-card { margin-bottom: 16px; border-radius: 6px; }
.pipeline-card__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 16px 24px 8px;
    gap: 16px;
}
.pipeline-card__title { display: flex; align-items: center; gap: 10px; }
.pipeline-card__title h3 { margin: 0; font-size: 1.05rem; font-weight: 600; }
.pipeline-card__title :deep(svg) { font-size: 1.2rem; }
.pipeline-card__progress { display: flex; align-items: center; gap: 12px; min-width: 220px; }
.pipeline-card__progress-label {
    font-weight: 700; font-size: 0.95rem; color: var(--color-text-strong);
    min-width: 40px; text-align: right;
}
.pipeline-card__bar {
    flex: 1; height: 8px; background: var(--color-border-soft, #f0f0f0);
    border-radius: 4px; overflow: hidden;
}
.pipeline-card__bar-fill {
    height: 100%; background: var(--color-primary, #1677ff);
    transition: width 0.3s ease;
}
.pipeline-card__bar-fill--won  { background: #52c41a; }
.pipeline-card__bar-fill--lost { background: #ff4d4f; }
.pipeline-card__desc {
    padding: 0 24px 12px;
    color: var(--color-text-muted);
    font-size: 0.85rem;
}

.stage-checklist {
    list-style: none; margin: 0;
    padding: 8px 0 16px;
    border-top: 1px solid var(--color-border-soft, #f0f0f0);
}
.stage-item {
    display: flex; align-items: flex-start; gap: 14px;
    padding: 10px 24px;
    border-left: 3px solid transparent;
    transition: background 0.15s ease;
}
.stage-item__bullet {
    font-size: 1.25rem;
    line-height: 1;
    flex-shrink: 0;
    padding-top: 2px;
    width: 24px;
    display: flex; justify-content: center;
}
.stage-item__body { flex: 1; min-width: 0; }
.stage-item__row1 {
    display: flex; align-items: center; gap: 10px;
    flex-wrap: wrap;
}
.stage-item__name { font-size: 0.95rem; font-weight: 500; color: var(--color-text-strong); }
.stage-item__row2 {
    display: flex; gap: 10px; flex-wrap: wrap;
    font-size: 0.8125rem;
    color: var(--color-text-muted);
    margin-top: 2px;
}
.stage-item__prob {
    background: var(--color-surface-alt, #fafafa);
    padding: 1px 8px;
    border-radius: 10px;
}

.stage-item--done .stage-item__bullet :deep(svg) { color: #b8b8b8; }
.stage-item--done .stage-item__name { color: var(--color-text-muted); text-decoration: line-through; opacity: 0.75; }

.stage-item--current {
    background: rgba(22, 119, 255, 0.06);
    border-left-color: var(--color-primary, #1677ff);
}
.stage-item--current .stage-item__bullet :deep(svg) { color: var(--color-primary, #1677ff); }
.stage-item--current .stage-item__name { color: var(--color-primary, #1677ff); font-weight: 600; }

.stage-item--won {
    background: rgba(82, 196, 26, 0.08);
    border-left-color: #52c41a;
}
.stage-item--won .stage-item__bullet :deep(svg) { color: #52c41a; }
.stage-item--won .stage-item__name { color: #389e0d; font-weight: 600; }

.stage-item--lost {
    background: rgba(255, 77, 79, 0.06);
    border-left-color: #ff4d4f;
}
.stage-item--lost .stage-item__bullet :deep(svg) { color: #ff4d4f; }
.stage-item--lost .stage-item__name { color: #cf1322; font-weight: 600; }

.stage-item--future .stage-item__bullet :deep(svg) { color: #d9d9d9; }
.stage-item--future .stage-item__name { color: var(--color-text-muted); }
.stage-item--future .stage-item__future-tag { opacity: 0.7; }

@media (max-width: 767px) {
    .pipeline-card__header { flex-direction: column; align-items: flex-start; }
    .pipeline-card__progress { width: 100%; }
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
