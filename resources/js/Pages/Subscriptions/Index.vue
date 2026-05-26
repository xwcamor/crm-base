<script setup>
import { Head, router } from '@inertiajs/vue3';
import { Card, Table, Tag, Select, Space, Empty } from 'ant-design-vue';
import { SafetyCertificateOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import { useDateFormat } from '@/Composables/useDateFormat';
defineOptions({ layout: AppLayout });

const { formatDate } = useDateFormat();

const props = defineProps({ subscriptions: { type: Object, required: true }, filters: { type: Object, required: true }, statusOptions: { type: Array, default: () => [] } });

const statusColor = { trial: 'gold', active: 'green', expired: 'default', cancelled: 'red', suspended: 'orange' };

const columns = [
    { title: 'Tenant', dataIndex: ['tenant','name'], key: 'tenant' },
    { title: 'Plan', dataIndex: 'plan', key: 'plan', width: 110 },
    { title: 'Estado', dataIndex: 'status', key: 'status', width: 120 },
    { title: 'Inicio', dataIndex: 'starts_at', key: 'starts', width: 140 },
    { title: 'Vence', dataIndex: 'ends_at', key: 'ends', width: 140 },
    { title: 'Monto', dataIndex: 'amount_paid', key: 'amount', align: 'right', width: 140 },
    { title: 'Método', dataIndex: 'payment_method', key: 'method', width: 130 },
];
const fmt = (d) => d ? formatDate(d) : '—';
</script>
<template>
    <Head title="Suscripciones" />
    <SectionHeader title="Suscripciones" subtitle="Historial de planes contratados por cada tenant.">
        <template #icon><SafetyCertificateOutlined /></template>
    </SectionHeader>
    <Card>
        <Space style="margin-bottom: 16px">
            <Select :value="filters.status || undefined" @change="v=>router.get(route('business_management.subscriptions.index'), { status: v ?? '' }, { preserveState: true })" :options="statusOptions" placeholder="Estado" allow-clear style="width:200px" />
        </Space>
        <Table :columns="columns" :data-source="subscriptions.data" :pagination="{ current: subscriptions.current_page, pageSize: subscriptions.per_page, total: subscriptions.total, showSizeChanger: false }"
            @change="(p) => router.get(route('business_management.subscriptions.index'), { ...filters, page: p.current }, { preserveState: true })" row-key="id" size="middle">
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'status'"><Tag :color="statusColor[record.status] || 'default'" :bordered="false">{{ statusOptions.find(o => o.value === record.status)?.label ?? record.status }}</Tag></template>
                <template v-else-if="column.key === 'plan'"><Tag color="blue" :bordered="false">{{ record.plan?.toUpperCase() }}</Tag></template>
                <template v-else-if="column.key === 'starts'">{{ fmt(record.starts_at) }}</template>
                <template v-else-if="column.key === 'ends'">{{ fmt(record.ends_at) }}</template>
                <template v-else-if="column.key === 'amount'">{{ record.amount_paid ? record.currency + ' ' + Number(record.amount_paid).toFixed(2) : '—' }}</template>
            </template>
            <template #emptyText><Empty description="Sin suscripciones" /></template>
        </Table>
    </Card>
</template>
