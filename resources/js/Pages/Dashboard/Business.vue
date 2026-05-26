<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import { Card, Row, Col, Statistic, Table, Tag, Progress, Empty } from 'ant-design-vue';
import {
    DashboardOutlined, TeamOutlined, DollarOutlined, FileTextOutlined,
    ContainerOutlined, ShoppingCartOutlined, FundOutlined, WarningOutlined,
    CheckCircleOutlined, BankOutlined, FunnelPlotOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import DashboardConfig from '@/Components/Dashboard/DashboardConfig.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { ref } from 'vue';

const { formatDate, formatDateTime } = useDateFormat();

defineOptions({ layout: AppLayout });

const props = defineProps({
    kpis:            { type: Object, required: true },
    lowStock:        { type: Array,  default: () => [] },
    recentWonDeals:  { type: Array,  default: () => [] },
    pipelineByStage: { type: Array,  default: () => [] },
    salesTrend:      { type: Array,  default: () => [] },
    myAgenda:        { type: Array,  default: () => [] },
    defaultCurrency: { type: String, default: 'USD' },
});

const fmtMoney = (n) => {
    const v = Number(n) || 0;
    return new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
};
const fmtNum = (n) => new Intl.NumberFormat('es').format(Number(n) || 0);

const maxTrend = computed(() => Math.max(...props.salesTrend.map(p => p.value), 1));
const maxStage = computed(() => Math.max(...props.pipelineByStage.map(p => Number(p.total_value)), 1));

// Catalogo de secciones que se pueden mostrar/ocultar. Cada `key` matchea
// con el v-if del bloque correspondiente abajo.
const sectionCatalog = [
    { key: 'kpis_crm',       label: 'KPIs CRM (Empresas / Contactos / Deals)', group: 'KPIs' },
    { key: 'forecast',       label: 'Forecast de ventas',                       group: 'KPIs' },
    { key: 'kpis_sales',     label: 'KPIs de ventas (Ganado / Facturado / Por cobrar)', group: 'KPIs' },
    { key: 'kpis_ops',       label: 'KPIs de operaciones (OV / OC / Cotizaciones)',     group: 'KPIs' },
    { key: 'chart_trend',    label: 'Ventas ganadas — últimos 6 meses',         group: 'Gráficos' },
    { key: 'chart_pipeline', label: 'Pipeline por etapa',                       group: 'Gráficos' },
    { key: 'agenda',         label: 'Mi agenda',                                 group: 'Operación diaria' },
    { key: 'low_stock',      label: 'Stock bajo',                                group: 'Operación diaria' },
    { key: 'recent_won',     label: 'Últimos deals ganados',                     group: 'Operación diaria' },
];
const visible = ref({});

const lowStockCols = [
    { title: 'Producto', dataIndex: 'product', key: 'product' },
    { title: 'SKU', dataIndex: 'sku', key: 'sku', width: 130 },
    { title: 'Almacén', dataIndex: 'warehouse', key: 'wh', width: 200 },
    { title: 'Disponible', dataIndex: 'available', key: 'available', align: 'right', width: 100 },
    { title: 'Mínimo', dataIndex: 'low_stock_threshold', key: 'min', align: 'right', width: 80 },
];

const recentCols = [
    { title: 'Empresa', dataIndex: ['company','name'], key: 'company' },
    { title: 'Deal', dataIndex: 'name', key: 'name' },
    { title: 'Valor', dataIndex: 'value', key: 'value', align: 'right', width: 140 },
    { title: 'Fecha', dataIndex: 'won_at', key: 'won_at', width: 110 },
];
</script>

<template>
    <Head title="Dashboard" />

    <SectionHeader title="Dashboard de Negocio" subtitle="Vista general de ventas, deals, facturación y operaciones.">
        <template #icon><DashboardOutlined /></template>
        <template #actions>
            <DashboardConfig
                storage-key="business"
                :sections="sectionCatalog"
                v-model:visible="visible"
            />
        </template>
    </SectionHeader>

    <!-- KPI Row 1: CRM -->
    <Row v-if="visible.kpis_crm" :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="12" :md="6">
            <Card><Statistic title="Empresas" :value="kpis.companies_total" :value-style="{ color: '#1677ff' }">
                <template #prefix><BankOutlined /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="Contactos" :value="kpis.contacts_total" :value-style="{ color: '#13c2c2' }">
                <template #prefix><TeamOutlined /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="Deals abiertos" :value="kpis.deals_open" :value-style="{ color: '#722ed1' }">
                <template #prefix><FunnelPlotOutlined /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="Pipeline" :value="fmtMoney(kpis.deals_pipeline_value)" :prefix="defaultCurrency" :value-style="{ color: '#fa8c16' }" /></Card>
        </Col>
    </Row>

    <!-- Forecast: monto esperado pondrado por probabilidad de cierre -->
    <Row v-if="visible.forecast" :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="12" :md="6">
            <Card>
                <Statistic title="Forecast este mes" :value="fmtMoney(kpis.deals_forecast_mtd)" :prefix="defaultCurrency" :value-style="{ color: '#1677ff', fontWeight: 700 }">
                    <template #suffix><FundOutlined style="color:#1677ff" /></template>
                </Statistic>
                <div class="kpi-hint">Suma ponderada de deals open con cierre este mes (value x probabilidad).</div>
            </Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card>
                <Statistic title="Forecast 30 dias" :value="fmtMoney(kpis.deals_forecast_next30d)" :prefix="defaultCurrency" :value-style="{ color: '#1677ff' }">
                    <template #suffix><FundOutlined style="color:#1677ff" /></template>
                </Statistic>
                <div class="kpi-hint">Proximos 30 dias desde hoy.</div>
            </Card>
        </Col>
    </Row>

    <!-- KPI Row 2: Sales/Invoices -->
    <Row v-if="visible.kpis_sales" :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="12" :md="6">
            <Card><Statistic title="Ganado este mes" :value="fmtMoney(kpis.deals_won_value_mtd)" :prefix="defaultCurrency" :value-style="{ color: '#389e0d', fontWeight: 700 }">
                <template #suffix><CheckCircleOutlined style="color:#fadb14" /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="Facturado este mes" :value="fmtMoney(kpis.invoices_paid_mtd)" :prefix="defaultCurrency" :value-style="{ color: '#389e0d' }">
                <template #prefix><FileTextOutlined /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="Por cobrar" :value="fmtMoney(kpis.invoices_balance_due)" :prefix="defaultCurrency" :value-style="{ color: '#d4380d' }">
                <template #prefix><DollarOutlined /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="Facturas vencidas" :value="kpis.invoices_overdue" :value-style="{ color: kpis.invoices_overdue > 0 ? '#d4380d' : '#888' }">
                <template #prefix><WarningOutlined /></template>
            </Statistic></Card>
        </Col>
    </Row>

    <!-- KPI Row 3: Operations -->
    <Row v-if="visible.kpis_ops" :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="12" :md="6">
            <Card><Statistic title="OV pendientes" :value="kpis.sales_orders_pending" :value-style="{ color: '#1677ff' }">
                <template #prefix><ShoppingCartOutlined /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="OC abiertas" :value="kpis.purchase_orders_open" :value-style="{ color: '#722ed1' }">
                <template #prefix><ContainerOutlined /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="Cotizaciones enviadas" :value="kpis.quotes_sent" :value-style="{ color: '#fa8c16' }">
                <template #prefix><FileTextOutlined /></template>
            </Statistic></Card>
        </Col>
        <Col :xs="12" :md="6">
            <Card><Statistic title="Pagos del mes" :value="kpis.payments_count_mtd" :value-style="{ color: '#13c2c2' }">
                <template #prefix><FundOutlined /></template>
            </Statistic></Card>
        </Col>
    </Row>

    <!-- Charts row -->
    <Row v-if="visible.chart_trend || visible.chart_pipeline" :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col v-if="visible.chart_trend" :xs="24" :md="visible.chart_pipeline ? 14 : 24">
            <Card title="Ventas ganadas — últimos 6 meses">
                <div class="bars">
                    <div v-for="p in salesTrend" :key="p.month" class="bar-wrap">
                        <div class="bar-value">{{ defaultCurrency }} {{ fmtMoney(p.value) }}</div>
                        <div class="bar"><div class="bar-fill" :style="{ height: ((p.value / maxTrend) * 100) + '%' }"></div></div>
                        <div class="bar-label">{{ p.label }}</div>
                    </div>
                </div>
            </Card>
        </Col>
        <Col v-if="visible.chart_pipeline" :xs="24" :md="visible.chart_trend ? 10 : 24">
            <Card title="Pipeline por etapa">
                <div v-if="pipelineByStage.length === 0"><Empty description="Sin deals abiertos" /></div>
                <div v-else class="stage-list">
                    <div v-for="s in pipelineByStage" :key="s.id" class="stage-row">
                        <div class="stage-head">
                            <span class="stage-name">
                                <span class="stage-dot" :style="{ background: s.color || '#888' }"></span>
                                {{ s.name }}
                            </span>
                            <span class="stage-meta">{{ s.deal_count }} deals — {{ defaultCurrency }} {{ fmtMoney(s.total_value) }}</span>
                        </div>
                        <Progress :percent="Math.round((Number(s.total_value) / maxStage) * 100)" :show-info="false" :stroke-color="s.color || '#1677ff'" />
                    </div>
                </div>
            </Card>
        </Col>
    </Row>

    <!-- Mi agenda (proximas activities del user) -->
    <Row v-if="visible.agenda" :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24">
            <Card>
                <template #title>
                    <span>{{ $t('activities.widget_title') }}</span>
                </template>
                <template #extra>
                    <Link :href="route('crm.activities.index')">{{ $t('activities.widget_see_all') }}</Link>
                </template>
                <div v-if="myAgenda.length === 0" class="empty-agenda">
                    <Empty :description="$t('activities.widget_empty')" />
                </div>
                <ul v-else class="agenda-list">
                    <li v-for="a in myAgenda" :key="a.id" class="agenda-item">
                        <Tag :bordered="false" class="agenda-type">{{ $t(`activities.types.${a.type}`) }}</Tag>
                        <div class="agenda-body">
                            <div class="agenda-subject">
                                <Link v-if="a.parent_url" :href="a.parent_url">{{ a.subject || '(sin asunto)' }}</Link>
                                <span v-else>{{ a.subject || '(sin asunto)' }}</span>
                                <span v-if="a.parent_label" class="muted"> · {{ a.parent_label }}</span>
                            </div>
                            <div v-if="a.body" class="agenda-body-text">{{ a.body }}</div>
                        </div>
                        <div class="agenda-meta">
                            <span v-if="a.due_at" :class="{ 'overdue': a.is_overdue }">
                                {{ formatDateTime(a.due_at) }}
                            </span>
                            <Tag v-if="a.priority === 'high'" color="red" :bordered="false">{{ $t('activities.priorities.high') }}</Tag>
                        </div>
                    </li>
                </ul>
            </Card>
        </Col>
    </Row>

    <!-- Recent + Low stock -->
    <Row v-if="visible.recent_won || visible.low_stock" :gutter="[16, 16]">
        <Col v-if="visible.recent_won" :xs="24" :md="visible.low_stock ? 14 : 24">
            <Card title="Últimos deals ganados">
                <Table :columns="recentCols" :data-source="recentWonDeals" :pagination="false" size="middle" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'value'"><strong>{{ record.currency_code }} {{ fmtMoney(record.value) }}</strong></template>
                        <template v-else-if="column.key === 'won_at'">{{ formatDate(record.won_at) }}</template>
                        <template v-else-if="column.key === 'name'"><Link :href="route('crm.deals.show', record.slug)">{{ record.name }}</Link></template>
                    </template>
                    <template #emptyText><Empty description="Sin deals ganados aún" /></template>
                </Table>
            </Card>
        </Col>
        <Col v-if="visible.low_stock" :xs="24" :md="visible.recent_won ? 10 : 24">
            <Card>
                <template #title>
                    <span><WarningOutlined style="color:#d4380d" /> Stock bajo o crítico</span>
                </template>
                <Table :columns="lowStockCols" :data-source="lowStock" :pagination="false" size="small" row-key="sku">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'available'">
                            <strong class="text-danger">{{ fmtNum(Number(record.qty_on_hand) - Number(record.qty_reserved)) }}</strong>
                        </template>
                        <template v-else-if="column.key === 'min'">{{ fmtNum(record.low_stock_threshold) }}</template>
                    </template>
                    <template #emptyText><Empty description="✓ Todo el stock está OK" /></template>
                </Table>
            </Card>
        </Col>
    </Row>
</template>

<style scoped>
.kpi-hint {
    font-size: 0.6875rem;
    color: var(--color-text-muted, #888);
    margin-top: 6px;
    line-height: 1.3;
}
.empty-agenda { padding: 16px; }
.agenda-list { list-style: none; padding: 0; margin: 0; }
.agenda-item {
    display: flex; gap: 12px; align-items: flex-start;
    padding: 10px 0;
}
.agenda-item + .agenda-item { border-top: 1px solid var(--color-border-soft, #f0f0f0); }
.agenda-type { flex-shrink: 0; min-width: 70px; text-align: center; }
.agenda-body { flex: 1; min-width: 0; }
.agenda-subject { font-weight: 500; color: var(--color-text-strong, #111); word-break: break-word; }
.agenda-body-text { font-size: 0.82rem; color: var(--color-text-muted, #666); margin-top: 2px; }
.agenda-meta { display: flex; align-items: center; gap: 8px; flex-shrink: 0; font-size: 0.82rem; }
.agenda-meta .overdue { color: var(--color-danger, #dc2626); font-weight: 600; }
.muted { color: var(--color-text-muted, #8c8c8c); }
</style>

<style scoped>
.bars { display: flex; align-items: flex-end; justify-content: space-around; gap: 12px; height: 220px; padding: 16px 8px 0; }
.bar-wrap { display: flex; flex-direction: column; align-items: center; flex: 1; height: 100%; }
.bar-value { font-size: 0.7rem; color: var(--color-text-muted, #666); margin-bottom: 4px; min-height: 14px; }
.bar { width: 100%; max-width: 50px; flex: 1; display: flex; align-items: flex-end; background: rgba(22,119,255,0.06); border-radius: 4px 4px 0 0; overflow: hidden; }
.bar-fill { width: 100%; background: linear-gradient(to top, #1677ff, #69b1ff); border-radius: 4px 4px 0 0; transition: height 0.3s; min-height: 2px; }
.bar-label { font-size: 0.78rem; color: var(--color-text-muted, #666); margin-top: 8px; text-transform: capitalize; }

.stage-list { display: flex; flex-direction: column; gap: 10px; }
.stage-row { display: flex; flex-direction: column; gap: 4px; }
.stage-head { display: flex; justify-content: space-between; font-size: 0.85rem; }
.stage-name { display: inline-flex; align-items: center; gap: 8px; font-weight: 600; }
.stage-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; }
.stage-meta { color: var(--color-text-muted, #666); font-size: 0.78rem; }

.text-danger { color: #d4380d; }
</style>
