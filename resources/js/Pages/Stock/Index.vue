<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, Table, Tag, Input, Select, Space, Empty } from 'ant-design-vue';
import { ContainerOutlined, HistoryOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
defineOptions({ layout: AppLayout });

const props = defineProps({
    levels:           { type: Object, required: true },
    warehouseOptions: { type: Array, default: () => [] },
    filters:          { type: Object, required: true },
});

const columns = [
    { title: 'Producto', dataIndex: 'product_name', key: 'name' },
    { title: 'SKU', dataIndex: 'product_sku', key: 'sku', width: 140 },
    { title: 'Almacén', dataIndex: 'warehouse_name', key: 'wh', width: 200 },
    { title: 'On hand', dataIndex: 'qty_on_hand', key: 'on_hand', align: 'right', width: 100 },
    { title: 'Reservado', dataIndex: 'qty_reserved', key: 'reserved', align: 'right', width: 100 },
    { title: 'Disponible', dataIndex: 'available', key: 'available', align: 'right', width: 110 },
    { title: 'Min', dataIndex: 'low_stock_threshold', key: 'min', align: 'right', width: 70 },
    { title: 'Costo prom.', dataIndex: 'average_cost', key: 'cost', align: 'right', width: 130 },
    { title: 'Último mov.', dataIndex: 'last_movement_at', key: 'last', width: 170 },
];
const fmt = (n) => n == null ? '—' : new Intl.NumberFormat('es',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(n));
const onF = (f, v) => router.get(route('business_management.stock.index'), {...props.filters, [f]: v}, {preserveState:true, replace:true});
</script>
<template>
    <Head title="Stock" />
    <SectionHeader title="Stock por almacén" subtitle="Niveles actuales (qty on hand, reservado, disponible) por producto × almacén.">
        <template #icon><ContainerOutlined /></template>
        <template #actions>
            <Link :href="route('business_management.stock.movements')">
                <a><HistoryOutlined /> Movimientos (kardex)</a>
            </Link>
        </template>
    </SectionHeader>
    <Card>
        <Space style="margin-bottom: 16px" :size="12" wrap>
            <Input :value="filters.product" @change="e=>onF('product', e.target.value)" placeholder="Buscar producto" allow-clear style="width:240px" />
            <Select :value="filters.warehouse_id ? Number(filters.warehouse_id) : undefined" @change="v=>onF('warehouse_id', v ?? '')" :options="warehouseOptions" placeholder="Filtrar por almacén" allow-clear style="width:240px" />
        </Space>
        <Table :columns="columns" :data-source="levels.data" :pagination="{ current: levels.current_page, pageSize: levels.per_page, total: levels.total, showSizeChanger: false }"
            @change="(p) => router.get(route('business_management.stock.index'), { ...filters, page: p.current }, { preserveState: true })" row-key="id" size="middle">
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'on_hand'">{{ fmt(record.qty_on_hand) }}</template>
                <template v-else-if="column.key === 'reserved'">
                    <span :class="Number(record.qty_reserved) > 0 ? 'text-warn' : ''">{{ fmt(record.qty_reserved) }}</span>
                </template>
                <template v-else-if="column.key === 'available'">
                    <strong :class="(Number(record.qty_on_hand) - Number(record.qty_reserved)) <= Number(record.low_stock_threshold || 0) ? 'text-danger' : 'text-success'">
                        {{ fmt(Number(record.qty_on_hand) - Number(record.qty_reserved)) }}
                    </strong>
                </template>
                <template v-else-if="column.key === 'min'">{{ record.low_stock_threshold ?? 0 }}</template>
                <template v-else-if="column.key === 'cost'">{{ fmt(record.average_cost) }}</template>
            </template>
            <template #emptyText><Empty description="Sin stock registrado" /></template>
        </Table>
    </Card>
</template>
<style scoped>.text-danger{color:#d4380d}.text-success{color:#389e0d}.text-warn{color:#d48806}</style>
