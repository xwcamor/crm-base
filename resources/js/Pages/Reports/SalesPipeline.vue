<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, Row, Col, Table, Empty, Tag } from 'ant-design-vue';
import { FunnelPlotOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ReportFilterBar from '@/Components/Reports/ReportFilterBar.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const { t } = useI18n();

const props = defineProps({
    funnel:          { type: Array,  default: () => [] },
    valueByStage:    { type: Array,  default: () => [] },
    avgTimeByStage:  { type: Array,  default: () => [] },
    defaultCurrency: { type: String, default: 'USD' },
    filters:         { type: Object, default: () => ({}) },
    options:         { type: Object, default: () => ({}) },
});

const fmtMoney = (n) => new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);
const fmtNum   = (n) => new Intl.NumberFormat('es').format(Number(n) || 0);

const funnelMax = computed(() => Math.max(...props.funnel.map(f => Number(f.count) || 0), 1));
const stageMax  = computed(() => Math.max(...props.valueByStage.map(s => Number(s.total_value) || 0), 1));

const stageCols = [
    { title: t('reports.stage'),          dataIndex: 'name',            key: 'name' },
    { title: t('reports.deal_count'),     dataIndex: 'deal_count',      key: 'deal_count',     align: 'right', width: 100 },
    { title: t('reports.total_value'),    dataIndex: 'total_value',     key: 'total_value',    align: 'right', width: 160 },
    { title: t('reports.weighted_value'), dataIndex: 'weighted_value',  key: 'weighted_value', align: 'right', width: 160 },
    { title: t('reports.probability'),    dataIndex: 'probability_pct', key: 'prob',           align: 'right', width: 110 },
];

const timeCols = [
    { title: t('reports.stage'),       dataIndex: 'name',        key: 'name' },
    { title: t('reports.avg_days'),    dataIndex: 'avg_days',    key: 'avg_days',    align: 'right', width: 130 },
    { title: t('reports.transitions'), dataIndex: 'transitions', key: 'transitions', align: 'right', width: 130 },
];

const lifecycleLabel = (stage) => t('reports.lifecycle_' + stage) || stage;
</script>

<template>
    <Head :title="t('reports.pipeline_title')" />

    <SectionHeader :title="t('reports.pipeline_title')" :subtitle="t('reports.pipeline_subtitle')">
        <template #icon><FunnelPlotOutlined /></template>
    </SectionHeader>

    <ReportFilterBar
        :available="['date_range', 'pipeline_id', 'owner_id']"
        :initial="filters"
        :pipelines="options.pipelines || []"
        :owners="options.owners || []"
        route-name="reports.sales_pipeline"
        module="report_sales_pipeline"
        export-key="sales_pipeline"
    />

    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24" :md="10">
            <Card :title="t('reports.funnel_title')">
                <div v-if="funnel.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <div v-else class="funnel">
                    <div v-for="f in funnel" :key="f.stage" class="funnel-row">
                        <div class="funnel-label">{{ lifecycleLabel(f.stage) }}</div>
                        <div class="funnel-bar">
                            <div class="funnel-fill" :style="{ width: ((Number(f.count) / funnelMax) * 100) + '%' }">
                                <span class="funnel-count">{{ fmtNum(f.count) }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Card>
        </Col>

        <Col :xs="24" :md="14">
            <Card :title="t('reports.value_by_stage')">
                <div v-if="valueByStage.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="stageCols" :data-source="valueByStage" :pagination="false" size="middle" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'name'">
                            <Link :href="route('crm.deals.index', { stage_id: record.id, status: 'open' })" class="drill-link" title="Ver deals de esta etapa">
                                <span class="stage-name">
                                    <span class="stage-dot" :style="{ background: record.color || '#888' }"></span>
                                    {{ record.name }}
                                </span>
                            </Link>
                        </template>
                        <template v-else-if="column.key === 'total_value'">
                            <strong>{{ defaultCurrency }} {{ fmtMoney(record.total_value) }}</strong>
                            <div class="bar-mini">
                                <div class="bar-mini-fill" :style="{ width: ((Number(record.total_value) / stageMax) * 100) + '%', background: record.color || '#1677ff' }"></div>
                            </div>
                        </template>
                        <template v-else-if="column.key === 'weighted_value'">
                            {{ defaultCurrency }} {{ fmtMoney(record.weighted_value) }}
                        </template>
                        <template v-else-if="column.key === 'prob'">
                            <Tag :bordered="false">{{ record.probability_pct }}%</Tag>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>

    <Row :gutter="[16, 16]">
        <Col :xs="24">
            <Card :title="t('reports.avg_time_by_stage')">
                <div v-if="avgTimeByStage.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="timeCols" :data-source="avgTimeByStage" :pagination="false" size="middle" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'name'">
                            <span class="stage-name">
                                <span class="stage-dot" :style="{ background: record.color || '#888' }"></span>
                                {{ record.name }}
                            </span>
                        </template>
                        <template v-else-if="column.key === 'avg_days'">
                            <strong>{{ fmtNum(record.avg_days) }}</strong>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>
</template>

<style scoped>
.funnel { display: flex; flex-direction: column; gap: 10px; padding: 8px 0; }
.funnel-row { display: flex; gap: 12px; align-items: center; }
.funnel-label { width: 110px; font-weight: 600; font-size: 0.85rem; text-transform: capitalize; }
.funnel-bar { flex: 1; background: rgba(22,119,255,0.06); border-radius: 4px; height: 28px; overflow: hidden; }
.funnel-fill {
    height: 100%;
    background: linear-gradient(to right, #1677ff, #69b1ff);
    border-radius: 4px;
    display: flex; align-items: center; justify-content: flex-end;
    padding-right: 8px; color: white; font-weight: 600; font-size: 0.82rem;
    min-width: 36px; transition: width 0.4s;
}
.funnel-count { white-space: nowrap; }
.drill-link { color: inherit; text-decoration: none; }
.drill-link:hover { color: var(--color-primary, #1677ff); text-decoration: underline; }
.stage-name { display: inline-flex; align-items: center; gap: 8px; font-weight: 500; }
.stage-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.bar-mini { height: 4px; background: rgba(0,0,0,0.04); border-radius: 2px; margin-top: 4px; overflow: hidden; }
.bar-mini-fill { height: 100%; border-radius: 2px; transition: width 0.3s; }
</style>
