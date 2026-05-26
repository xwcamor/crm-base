<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Card, Row, Col, Table, Empty, Progress, Tag } from 'ant-design-vue';
import { PieChartOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ReportFilterBar from '@/Components/Reports/ReportFilterBar.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const { t } = useI18n();

const props = defineProps({
    byOwner:         { type: Array,  default: () => [] },
    bySource:        { type: Array,  default: () => [] },
    byStage:         { type: Array,  default: () => [] },
    topLostReasons:  { type: Array,  default: () => [] },
    defaultCurrency: { type: String, default: 'USD' },
    filters:         { type: Object, default: () => ({}) },
    options:         { type: Object, default: () => ({}) },
});

const fmtMoney = (n) => new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);
const fmtNum   = (n) => new Intl.NumberFormat('es').format(Number(n) || 0);

const ownerCols = [
    { title: t('reports.owner'),       dataIndex: 'owner_name', key: 'owner' },
    { title: t('reports.won_count'),   dataIndex: 'won_count',  key: 'won_count',  align: 'right', width: 90 },
    { title: t('reports.lost_count'),  dataIndex: 'lost_count', key: 'lost_count', align: 'right', width: 90 },
    { title: t('reports.won_value'),   dataIndex: 'won_value',  key: 'won_value',  align: 'right', width: 150 },
    { title: t('reports.win_rate_pct'),dataIndex: 'win_rate',   key: 'rate',       align: 'right', width: 180 },
];

const sourceCols = [
    { title: t('reports.source'),      dataIndex: 'source_name', key: 'source' },
    { title: t('reports.won_count'),   dataIndex: 'won_count',  key: 'won_count',  align: 'right', width: 100 },
    { title: t('reports.lost_count'),  dataIndex: 'lost_count', key: 'lost_count', align: 'right', width: 100 },
    { title: t('reports.win_rate_pct'),dataIndex: 'win_rate',   key: 'rate',       align: 'right', width: 180 },
];

const stageCols = [
    { title: t('reports.stage'),       dataIndex: 'stage_name', key: 'stage' },
    { title: t('reports.lost_count'),  dataIndex: 'lost_count', key: 'cnt',   align: 'right', width: 110 },
    { title: t('reports.lost_value'),  dataIndex: 'lost_value', key: 'value', align: 'right', width: 180 },
];

const reasonCols = [
    { title: t('reports.reason'),     dataIndex: 'reason',     key: 'reason' },
    { title: t('reports.occurrences'),dataIndex: 'cnt',        key: 'cnt',        align: 'right', width: 110 },
    { title: t('reports.lost_value'), dataIndex: 'lost_value', key: 'lost_value', align: 'right', width: 180 },
];

const rateColor = (rate) => {
    const r = Number(rate) || 0;
    if (r >= 60) return '#389e0d';
    if (r >= 30) return '#fa8c16';
    return '#d4380d';
};
</script>

<template>
    <Head :title="t('reports.win_rate_title')" />

    <SectionHeader :title="t('reports.win_rate_title')" :subtitle="t('reports.win_rate_subtitle')">
        <template #icon><PieChartOutlined /></template>
    </SectionHeader>

    <ReportFilterBar
        :available="['date_range', 'pipeline_id', 'owner_id', 'lead_source_id']"
        :initial="filters"
        :pipelines="options.pipelines || []"
        :owners="options.owners || []"
        :lead-sources="options.leadSources || []"
        route-name="reports.win_rate"
        module="report_win_rate"
        export-key="win_rate"
    />

    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24">
            <Card :title="t('reports.win_rate_by_owner')">
                <div v-if="byOwner.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="ownerCols" :data-source="byOwner" :pagination="false" size="middle" :row-key="r => r.owner_email || r.owner_name">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'owner'">
                            <Link v-if="record.owner_id" :href="route('crm.deals.index', { owner_id: record.owner_id })" class="drill-link" title="Ver deals de este vendedor">
                                {{ record.owner_name }}
                            </Link>
                            <span v-else>{{ record.owner_name }}</span>
                        </template>
                        <template v-else-if="column.key === 'won_value'">
                            <strong>{{ defaultCurrency }} {{ fmtMoney(record.won_value) }}</strong>
                        </template>
                        <template v-else-if="column.key === 'rate'">
                            <div class="rate-cell">
                                <Progress
                                    :percent="Number(record.win_rate) || 0"
                                    :stroke-color="rateColor(record.win_rate)"
                                    :show-info="false"
                                    style="flex:1"
                                />
                                <strong class="rate-num">{{ record.win_rate }}%</strong>
                            </div>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>

    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24" :md="12">
            <Card :title="t('reports.win_rate_by_source')">
                <div v-if="bySource.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="sourceCols" :data-source="bySource" :pagination="false" size="small" :row-key="r => r.lead_source_id || r.source_name">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'rate'">
                            <div class="rate-cell">
                                <Progress
                                    :percent="Number(record.win_rate) || 0"
                                    :stroke-color="rateColor(record.win_rate)"
                                    :show-info="false"
                                    style="flex:1"
                                />
                                <strong class="rate-num">{{ record.win_rate }}%</strong>
                            </div>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>

        <Col :xs="24" :md="12">
            <Card :title="t('reports.win_rate_by_stage')">
                <div v-if="byStage.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="stageCols" :data-source="byStage" :pagination="false" size="small" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'stage'">
                            <span class="stage-name">
                                <span class="stage-dot" :style="{ background: record.color || '#888' }"></span>
                                {{ record.stage_name }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'value'">
                            <span class="text-danger">{{ defaultCurrency }} {{ fmtMoney(record.lost_value) }}</span>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>

    <Row :gutter="[16, 16]">
        <Col :xs="24">
            <Card :title="t('reports.top_lost_reasons')">
                <div v-if="topLostReasons.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="reasonCols" :data-source="topLostReasons" :pagination="false" size="middle" :row-key="r => r.reason">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'reason'">
                            <Tag color="red" :bordered="false">{{ record.reason }}</Tag>
                        </template>
                        <template v-else-if="column.key === 'lost_value'">
                            <span class="text-danger">{{ defaultCurrency }} {{ fmtMoney(record.lost_value) }}</span>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>
</template>

<style scoped>
.drill-link { color: inherit; text-decoration: none; }
.drill-link:hover { color: var(--color-primary, #1677ff); text-decoration: underline; }

.rate-cell { display: flex; align-items: center; gap: 10px; }
.rate-num { min-width: 50px; text-align: right; }
.stage-name { display: inline-flex; align-items: center; gap: 8px; }
.stage-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.text-danger { color: #d4380d; }
</style>
