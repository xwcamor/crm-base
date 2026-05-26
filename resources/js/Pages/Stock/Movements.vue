<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Card, Table, Tag, Empty } from 'ant-design-vue';
import { HistoryOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
defineOptions({ layout: AppLayout });

const props = defineProps({
    movements:   { type: Object, required: true },
    typeOptions: { type: Array, default: () => [] },
});

const typeColor = { receipt: 'green', issue: 'red', transfer_in: 'blue', transfer_out: 'blue', adjustment: 'orange', return_in: 'cyan', return_out: 'magenta' };

const columns = [
    { title: 'Fecha', dataIndex: 'moved_at', key: 'moved_at', width: 170 },
    { title: 'Tipo', dataIndex: 'type', key: 'type', width: 130 },
    { title: 'Producto', dataIndex: ['product','name'], key: 'product' },
    { title: 'Almacén', dataIndex: ['warehouse','name'], key: 'warehouse', width: 180 },
    { title: 'Cantidad', dataIndex: 'quantity', key: 'qty', align: 'right', width: 110 },
    { title: 'Costo unit.', dataIndex: 'unit_cost', key: 'cost', align: 'right', width: 110 },
    { title: 'Total', dataIndex: 'total_cost', key: 'total', align: 'right', width: 130 },
    { title: 'Ref. doc', dataIndex: 'source_reference', key: 'ref', width: 150 },
    { title: 'Nota', dataIndex: 'note', key: 'note' },
];

const fmt = (n) => n == null ? '—' : new Intl.NumberFormat('es',{minimumFractionDigits:2,maximumFractionDigits:2}).format(Number(n));
</script>
<template>
    <Head title="Movimientos de stock" />
    <SectionHeader title="Kardex — Movimientos de stock" subtitle="Historial completo de entradas/salidas/ajustes/transferencias.">
        <template #icon><HistoryOutlined /></template>
    </SectionHeader>
    <Card>
        <Table :columns="columns" :data-source="movements.data" :pagination="{ current: movements.current_page, pageSize: movements.per_page, total: movements.total, showSizeChanger: false }"
            @change="(p) => router.get(route('business_management.stock.movements'), { page: p.current }, { preserveState: true })" row-key="id" size="middle">
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'type'">
                    <Tag :color="typeColor[record.type]||'default'" :bordered="false">{{ typeOptions.find(o => o.value===record.type)?.label ?? record.type }}</Tag>
                </template>
                <template v-else-if="column.key === 'qty'">
                    <strong :class="record.type === 'issue' || record.type.endsWith('_out') ? 'text-danger' : 'text-success'">
                        {{ record.type === 'issue' || record.type.endsWith('_out') ? '−' : '+' }} {{ fmt(record.quantity) }}
                    </strong>
                </template>
                <template v-else-if="column.key === 'cost'">{{ fmt(record.unit_cost) }}</template>
                <template v-else-if="column.key === 'total'">{{ fmt(record.total_cost) }}</template>
            </template>
            <template #emptyText><Empty description="Sin movimientos registrados" /></template>
        </Table>
    </Card>
</template>
<style scoped>.text-danger{color:#d4380d}.text-success{color:#389e0d}</style>
