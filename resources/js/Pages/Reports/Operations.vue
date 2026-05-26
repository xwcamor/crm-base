<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { Card, Row, Col, Table, Empty, Statistic, Tag } from 'ant-design-vue';
import { FundOutlined, WarningOutlined, ContainerOutlined, DollarOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ReportFilterBar from '@/Components/Reports/ReportFilterBar.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const { t } = useI18n();

const props = defineProps({
    overdueByCompany:      { type: Array,  default: () => [] },
    overdueTotals:         { type: Object, default: () => ({ count: 0, total: 0 }) },
    lowStockByWarehouse:   { type: Array,  default: () => [] },
    lowStockByCategory:    { type: Array,  default: () => [] },
    lowStockDetail:        { type: Array,  default: () => [] },
    pendingPOsBySupplier:  { type: Array,  default: () => [] },
    pendingPOsTotals:      { type: Object, default: () => ({ count: 0, total: 0 }) },
    defaultCurrency:       { type: String, default: 'USD' },
    filters:               { type: Object, default: () => ({}) },
    options:               { type: Object, default: () => ({}) },
});

const fmtMoney = (n) => new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);
const fmtNum   = (n) => new Intl.NumberFormat('es').format(Number(n) || 0);

const overdueCols = [
    { title: t('reports.company'),       dataIndex: 'company_name',  key: 'company' },
    { title: t('reports.invoice_count'), dataIndex: 'invoice_count', key: 'cnt',   align: 'right', width: 110 },
    { title: t('reports.lost_value'),    dataIndex: 'total_overdue', key: 'total', align: 'right', width: 200 },
];

const warehouseCols = [
    { title: t('reports.warehouse'),    dataIndex: 'warehouse_name', key: 'wh' },
    { title: t('reports.product_count'),dataIndex: 'product_count',  key: 'pc',   align: 'right', width: 130 },
];

const categoryCols = [
    { title: t('reports.category'),     dataIndex: 'category_name', key: 'cat' },
    { title: t('reports.product_count'),dataIndex: 'product_count', key: 'pc',  align: 'right', width: 130 },
];

const detailCols = [
    { title: t('reports.product'),    dataIndex: 'product_name',   key: 'product' },
    { title: t('reports.sku'),        dataIndex: 'sku',            key: 'sku',       width: 140 },
    { title: t('reports.warehouse'),  dataIndex: 'warehouse_name', key: 'wh',        width: 180 },
    { title: t('reports.available'),  key: 'avail',                align: 'right',   width: 110 },
    { title: t('reports.threshold'),  dataIndex: 'low_stock_threshold', key: 'min',  align: 'right', width: 100 },
];

const supplierCols = [
    { title: t('reports.supplier'), dataIndex: 'supplier_name', key: 'supplier' },
    { title: t('reports.po_count'), dataIndex: 'po_count',      key: 'cnt',   align: 'right', width: 100 },
    { title: t('reports.po_value'), dataIndex: 'total_value',   key: 'total', align: 'right', width: 200 },
];
</script>

<template>
    <Head :title="t('reports.operations_title')" />

    <SectionHeader :title="t('reports.operations_title')" :subtitle="t('reports.operations_subtitle')">
        <template #icon><FundOutlined /></template>
    </SectionHeader>

    <ReportFilterBar
        :available="['currency_code']"
        :initial="filters"
        :currencies="options.currencies || []"
        route-name="reports.operations"
        module="report_operations"
        export-key="operations"
    />

    <!-- KPIs resumen -->
    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24" :md="8">
            <Card>
                <Statistic :title="t('reports.overdue_invoices')" :value="overdueTotals.count" :value-style="{ color: overdueTotals.count > 0 ? '#d4380d' : '#888' }">
                    <template #prefix><WarningOutlined /></template>
                </Statistic>
            </Card>
        </Col>
        <Col :xs="24" :md="8">
            <Card>
                <Statistic :title="t('reports.overdue_invoices_total')" :value="fmtMoney(overdueTotals.total)" :prefix="defaultCurrency" :value-style="{ color: overdueTotals.total > 0 ? '#d4380d' : '#888' }">
                    <template #suffix><DollarOutlined /></template>
                </Statistic>
            </Card>
        </Col>
        <Col :xs="24" :md="8">
            <Card>
                <Statistic :title="t('reports.pending_pos_total')" :value="fmtMoney(pendingPOsTotals.total)" :prefix="defaultCurrency" :value-style="{ color: '#722ed1' }">
                    <template #suffix><ContainerOutlined /></template>
                </Statistic>
            </Card>
        </Col>
    </Row>

    <!-- Overdue invoices -->
    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24">
            <Card :title="t('reports.overdue_by_company')">
                <div v-if="overdueByCompany.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="overdueCols" :data-source="overdueByCompany" :pagination="false" size="middle" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'company'">
                            <Link :href="route('business_management.invoices.index', { company_id: record.id, status: 'overdue' })" class="drill-link" title="Ver facturas vencidas de esta empresa">
                                {{ record.company_name }}
                            </Link>
                        </template>
                        <template v-else-if="column.key === 'total'">
                            <strong class="text-danger">{{ defaultCurrency }} {{ fmtMoney(record.total_overdue) }}</strong>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>

    <!-- Low stock dashboards -->
    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24" :md="12">
            <Card :title="t('reports.low_stock_by_warehouse')">
                <div v-if="lowStockByWarehouse.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="warehouseCols" :data-source="lowStockByWarehouse" :pagination="false" size="small" row-key="id" />
            </Card>
        </Col>
        <Col :xs="24" :md="12">
            <Card :title="t('reports.low_stock_by_category')">
                <div v-if="lowStockByCategory.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="categoryCols" :data-source="lowStockByCategory" :pagination="false" size="small" :row-key="r => r.id || r.category_name" />
            </Card>
        </Col>
    </Row>

    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24">
            <Card :title="t('reports.low_stock_detail')">
                <div v-if="lowStockDetail.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="detailCols" :data-source="lowStockDetail" :pagination="false" size="middle" :row-key="r => r.id + '-' + r.warehouse_name">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'avail'">
                            <strong class="text-danger">{{ fmtNum(Number(record.qty_on_hand) - Number(record.qty_reserved)) }}</strong>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>

    <!-- Pending POs -->
    <Row :gutter="[16, 16]">
        <Col :xs="24">
            <Card :title="t('reports.pending_pos_by_supplier')">
                <div v-if="pendingPOsBySupplier.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="supplierCols" :data-source="pendingPOsBySupplier" :pagination="false" size="middle" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'total'">
                            <strong>{{ defaultCurrency }} {{ fmtMoney(record.total_value) }}</strong>
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

.text-danger { color: #d4380d; }
</style>
