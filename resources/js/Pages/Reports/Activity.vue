<script setup>
import { Head } from '@inertiajs/vue3';
import { Card, Row, Col, Table, Empty, Tag } from 'ant-design-vue';
import { BarChartOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ReportFilterBar from '@/Components/Reports/ReportFilterBar.vue';
import { useI18n } from '@/Plugins/i18n';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const props = defineProps({
    byOwner:     { type: Array, default: () => [] },
    byOwnerType: { type: Array, default: () => [] },
    overdue:     { type: Array, default: () => [] },
    filters:     { type: Object, default: () => ({}) },
    options:     { type: Object, default: () => ({}) },
});

const fmtNum = (n) => new Intl.NumberFormat('es').format(Number(n) || 0);

const ownerCols = [
    { title: t('reports.owner'),            dataIndex: 'owner_name',      key: 'owner' },
    { title: t('reports.count_this_week'),  dataIndex: 'count_this_week', key: 'week',    align: 'right', width: 120 },
    { title: t('reports.count_this_month'), dataIndex: 'count_this_month',key: 'month',   align: 'right', width: 120 },
    { title: t('reports.total_activities'), dataIndex: 'total',           key: 'total',   align: 'right', width: 100 },
    { title: t('reports.overdue_count'),    dataIndex: 'overdue_count',   key: 'overdue', align: 'right', width: 110 },
];

const matrixCols = [
    { title: t('reports.owner'),     dataIndex: 'owner_name', key: 'owner' },
    { title: t('reports.type_call'), dataIndex: 'call',       key: 'call',    align: 'right', width: 90 },
    { title: t('reports.type_email'),dataIndex: 'email',      key: 'email',   align: 'right', width: 90 },
    { title: t('reports.type_meeting'),dataIndex: 'meeting',  key: 'meeting', align: 'right', width: 100 },
    { title: t('reports.type_task'), dataIndex: 'task',       key: 'task',    align: 'right', width: 90 },
    { title: t('reports.type_note'), dataIndex: 'note',       key: 'note',    align: 'right', width: 90 },
];

const overdueCols = [
    { title: t('reports.subject'),   dataIndex: 'subject',    key: 'subject' },
    { title: t('reports.type'),      dataIndex: 'type',       key: 'type',      width: 110 },
    { title: t('reports.owner'),     dataIndex: 'owner_name', key: 'owner',     width: 180 },
    { title: t('reports.due_at'),    dataIndex: 'due_at',     key: 'due_at',    width: 150 },
    { title: t('reports.days_late'), dataIndex: 'days_late',  key: 'days_late', align: 'right', width: 110 },
    { title: t('reports.priority'),  dataIndex: 'priority',   key: 'priority',  width: 110 },
];

const priorityColor = (p) => ({ low: 'blue', medium: 'orange', high: 'red' }[p] || 'default');
</script>

<template>
    <Head :title="t('reports.activity_title')" />

    <SectionHeader :title="t('reports.activity_title')" :subtitle="t('reports.activity_subtitle')">
        <template #icon><BarChartOutlined /></template>
    </SectionHeader>

    <ReportFilterBar
        :available="['date_range', 'owner_id', 'activity_type']"
        :initial="filters"
        :owners="options.owners || []"
        :activity-types="options.activityTypes || []"
        route-name="reports.activity"
        module="report_activity"
        export-key="activity"
    />

    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24">
            <Card :title="t('reports.activities_by_owner')">
                <div v-if="byOwner.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="ownerCols" :data-source="byOwner" :pagination="false" size="middle" :row-key="r => r.actor_user_id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'overdue'">
                            <span :class="{ 'text-danger': Number(record.overdue_count) > 0 }">
                                <strong>{{ fmtNum(record.overdue_count) }}</strong>
                            </span>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>

    <Row :gutter="[16, 16]" style="margin-bottom: 16px">
        <Col :xs="24">
            <Card :title="t('reports.activities_matrix')">
                <div v-if="byOwnerType.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="matrixCols" :data-source="byOwnerType" :pagination="false" size="middle" :row-key="r => r.actor_user_id" />
            </Card>
        </Col>
    </Row>

    <Row :gutter="[16, 16]">
        <Col :xs="24">
            <Card :title="t('reports.overdue_activities')">
                <div v-if="overdue.length === 0"><Empty :description="t('reports.no_data')" /></div>
                <Table v-else :columns="overdueCols" :data-source="overdue" :pagination="false" size="middle" row-key="id">
                    <template #bodyCell="{ column, record }">
                        <template v-if="column.key === 'type'">
                            <Tag :bordered="false">{{ record.type }}</Tag>
                        </template>
                        <template v-else-if="column.key === 'due_at'">
                            <span class="text-danger">{{ formatDateTime(record.due_at) }}</span>
                        </template>
                        <template v-else-if="column.key === 'days_late'">
                            <strong class="text-danger">{{ fmtNum(record.days_late) }}</strong>
                        </template>
                        <template v-else-if="column.key === 'priority'">
                            <Tag v-if="record.priority" :color="priorityColor(record.priority)" :bordered="false">{{ record.priority }}</Tag>
                            <span v-else>—</span>
                        </template>
                    </template>
                </Table>
            </Card>
        </Col>
    </Row>
</template>

<style scoped>
.text-danger { color: #d4380d; }
</style>
