<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, Row, Col, Table, Empty, Tag } from 'ant-design-vue';
import { LineChartOutlined, ArrowUpOutlined, ArrowDownOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ReportFilterBar from '@/Components/Reports/ReportFilterBar.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const { t } = useI18n();

const props = defineProps({
    monthly:         { type: Array,  default: () => [] },
    byCompany:       { type: Array,  default: () => [] },
    byCategory:      { type: Array,  default: () => [] },
    defaultCurrency: { type: String, default: 'USD' },
    filters:         { type: Object, default: () => ({}) },
    options:         { type: Object, default: () => ({}) },
});

const fmtMoney = (n) => new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

const maxMonthly = computed(() => Math.max(...props.monthly.map(p => Math.max(Number(p.value), Number(p.value_prev))), 1));

const companyCols = [
    { title: t('reports.company'),       dataIndex: 'company_name',   key: 'name' },
    { title: t('reports.invoice_count'), dataIndex: 'invoice_count',  key: 'inv_count', align: 'right', width: 110 },
    { title: t('reports.total_revenue'), dataIndex: 'total_revenue',  key: 'revenue',   align: 'right', width: 200 },
];

const categoryCols = [
    { title: t('reports.category'),      dataIndex: 'category_name', key: 'cat' },
    { title: t('reports.invoice_count'), dataIndex: 'invoice_count', key: 'inv_count', align: 'right', width: 110 },
    { title: t('reports.total_revenue'), dataIndex: 'total_revenue', key: 'revenue',   align: 'right', width: 200 },
];
</script>

<template>
    <Head :title="t('reports.revenue_title')" />

    <SectionHeader :title="t('reports.revenue_title')" :subtitle="t('reports.revenue_subtitle')">
        <template #icon><LineChartOutlined /></template>
    </SectionHeader>

    <ReportFilterBar
        :available="['date_range', 'currency_code']"
        :initial="filters"
        :currencies="options.currencies || []"
        route-name="reports.revenue"
        module="report_revenue"
        export-key="revenue"
    />

    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24">
            <Card :title="t('reports.monthly_revenue')">
                <div v-if="monthly.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <div v-else class="bars">
                    <div v-for="p in monthly" :key="p.month" class="bar-wrap">
                        <div class="bar-value">{{ defaultCurrency }} {{ fmtMoney(p.value) }}</div>
                        <div class="bar-group">
                            <div class="bar bar-current"
                                :style="{ height: ((Number(p.value) / maxMonthly) * 100) + '%' }"
                                :title="'Actual: ' + fmtMoney(p.value)"></div>
                            <div class="bar bar-prev"
                                :style="{ height: ((Number(p.value_prev) / maxMonthly) * 100) + '%' }"
                                :title="'Año anterior: ' + fmtMoney(p.value_prev)"></div>
                        </div>
                        <div class="bar-label">{{ p.label }}</div>
                        <div v-if="p.yoy_pct !== null" class="bar-yoy" :class="{ pos: p.yoy_pct >= 0, neg: p.yoy_pct < 0 }">
                            <ArrowUpOutlined v-if="p.yoy_pct >= 0" />
                            <ArrowDownOutlined v-else />
                            {{ Math.abs(p.yoy_pct) }}%
                        </div>
                        <div v-else class="bar-yoy muted">—</div>
                    </div>
                </div>
                <div class="legend">
                    <span class="legend-item"><span class="dot dot-current"></span> {{ t('reports.this_month') }}</span>
                    <span class="legend-item"><span class="dot dot-prev"></span> {{ t('reports.value_prev_year') }}</span>
                </div>
            </Card>
        </Col>
    </Row>

    <Row :gutter="[16, 16]">
        <Col :xs="24" :md="12">
            <Card :title="t('reports.top_companies')">
                <div v-if="byCompany.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="companyCols" :data-source="byCompany" :pagination="false" size="middle" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'name'">
                            <Link :href="route('business_management.invoices.index', { company_id: record.id, status: 'paid' })" class="drill-link" title="Ver facturas pagadas de esta empresa">
                                {{ record.company_name }}
                            </Link>
                        </template>
                        <template v-else-if="column.key === 'revenue'">
                            <strong>{{ defaultCurrency }} {{ fmtMoney(record.total_revenue) }}</strong>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>

        <Col :xs="24" :md="12">
            <Card :title="t('reports.by_category')">
                <div v-if="byCategory.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="categoryCols" :data-source="byCategory" :pagination="false" size="middle" :row-key="r => r.category_id || r.category_name">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'cat'">
                            <Tag :bordered="false">{{ record.category_name }}</Tag>
                        </template>
                        <template v-else-if="column.key === 'revenue'">
                            <strong>{{ defaultCurrency }} {{ fmtMoney(record.total_revenue) }}</strong>
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

.bars { display: flex; align-items: flex-end; justify-content: space-around; gap: 10px; height: 280px; padding: 16px 8px 0; }
.bar-wrap { display: flex; flex-direction: column; align-items: center; flex: 1; height: 100%; }
.bar-value { font-size: 0.7rem; color: var(--color-text-muted, #666); margin-bottom: 4px; min-height: 14px; }
.bar-group { width: 100%; max-width: 56px; flex: 1; display: flex; align-items: flex-end; gap: 2px; }
.bar { flex: 1; min-height: 2px; border-radius: 3px 3px 0 0; transition: height 0.3s; }
.bar-current { background: linear-gradient(to top, #1677ff, #69b1ff); }
.bar-prev    { background: linear-gradient(to top, #d9d9d9, #f0f0f0); }
.bar-label { font-size: 0.78rem; color: var(--color-text-muted, #666); margin-top: 6px; }
.bar-yoy { font-size: 0.72rem; margin-top: 2px; display: inline-flex; gap: 2px; align-items: center; }
.bar-yoy.pos { color: #389e0d; }
.bar-yoy.neg { color: #d4380d; }
.bar-yoy.muted { color: #aaa; }

.legend { display: flex; gap: 16px; justify-content: center; margin-top: 12px; font-size: 0.82rem; color: var(--color-text-muted, #666); }
.legend-item { display: inline-flex; align-items: center; gap: 6px; }
.dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.dot-current { background: #1677ff; }
.dot-prev    { background: #d9d9d9; }
</style>
