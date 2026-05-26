<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import { Card, Table, Tag, Descriptions, DescriptionsItem, Row, Col, Empty, Space, Alert } from 'ant-design-vue';
import { CheckSquareOutlined, HistoryOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const props = defineProps({
    take:     { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { t } = useI18n();
const { can, canSeeAudit, isSuper } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.take.deleted_at);

const statusColor = (s) => ({
    draft: 'default', in_progress: 'gold', completed: 'green', cancelled: 'red',
}[s] || 'default');

// StockTakes uses canEdit prop with extra business rule: cannot edit if status=completed.
const canEditTake = computed(() => can('stock_takes.edit') && props.take.status !== 'completed');

const fmt = (n) => n == null ? '—' : Number(n).toFixed(2);
const fmtDate = (d) => formatDateTimeFull(d);

const lineCols = [
    { title: t('stock_takes.line_product'),     dataIndex: ['product','name'], key: 'product' },
    { title: t('stock_takes.line_sku'),         dataIndex: ['product','sku'],  key: 'sku',     width: 130 },
    { title: t('stock_takes.line_qty_system'),  dataIndex: 'qty_system',       key: 'sys',     align: 'right', width: 100 },
    { title: t('stock_takes.line_qty_counted'), dataIndex: 'qty_counted',      key: 'counted', align: 'right', width: 110 },
    { title: t('stock_takes.line_variance'),    dataIndex: 'variance',         key: 'variance',align: 'right', width: 110 },
    { title: t('stock_takes.line_note'),        dataIndex: 'note',             key: 'note' },
];
</script>

<template>
    <Head :title="take.reference + ' — ' + $t('stock_takes.singular')" />

    <SectionHeader
        :back-href="route('business_management.stock_takes.index')"
        :title="take.reference"
        :icon-bg="isDeleted ? 'var(--color-danger)' : 'var(--color-primary)'"
    >
        <template #icon><CheckSquareOutlined /></template>
        <template #subtitle>
            <Space :size="6">
                <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                <Tag :color="statusColor(take.status)" :bordered="false">
                    {{ $t('stock_takes.status_options.' + take.status) }}
                </Tag>
                <span v-if="take.warehouse?.name" class="muted">{{ take.warehouse.name }}</span>
            </Space>
        </template>
        <template #actions>
            <EntityShowActions
                module="stock_takes"
                route-prefix="business_management"
                :slug="take.slug"
                :id="take.id"
                :is-deleted="isDeleted"
                :can-edit="canEditTake"
                :can-delete="can('stock_takes.delete')"
                :can-see-audit="canSeeAudit"
            />
        </template>
    </SectionHeader>

    <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
        <template #message>{{ $t('global.record_is_deleted') }}</template>
        <template #description>
            <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmtDate(take.deleted_at) }}</div>
            <div v-if="take.deleter">
                <strong>{{ $t('global.deleted_by') }}:</strong> {{ take.deleter.name }}
            </div>
            <div v-if="take.deleted_description">
                <strong>{{ $t('global.delete_description') }}:</strong> {{ take.deleted_description }}
            </div>
        </template>
    </Alert>

    <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="take"
        :activity="activity"
    >
        <template #general>
            <Row :gutter="[16,16]">
                <Col :xs="24" :md="16">
                    <Card :title="$t('stock_takes.lines_title')" :bodyStyle="{padding:0}">
                        <Table :columns="lineCols" :data-source="take.lines || []" :pagination="false" size="middle" row-key="id">
                            <template #bodyCell="{ column, record }">
                                <template v-if="column.key === 'sys'">{{ fmt(record.qty_system) }}</template>
                                <template v-else-if="column.key === 'counted'">{{ fmt(record.qty_counted) }}</template>
                                <template v-else-if="column.key === 'variance'">
                                    <strong :class="Number(record.variance) === 0 ? '' : (Number(record.variance) > 0 ? 'text-success' : 'text-danger')">
                                        {{ Number(record.variance) > 0 ? '+' : '' }}{{ fmt(record.variance) }}
                                    </strong>
                                </template>
                            </template>
                            <template #emptyText><Empty :description="$t('stock_takes.lines_empty')" /></template>
                        </Table>
                    </Card>
                </Col>

                <Col :xs="24" :md="8">
                    <Card :title="$t('stock_takes.warehouse')">
                        <Descriptions :column="1" size="small">
                            <DescriptionsItem :label="$t('stock_takes.warehouse')">
                                {{ take.warehouse?.name }} <span v-if="take.warehouse?.code">({{ take.warehouse.code }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('stock_takes.started_at')">
                                {{ take.started_at ? fmtDate(take.started_at) : '—' }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('stock_takes.completed_at')">
                                {{ take.completed_at ? fmtDate(take.completed_at) : '—' }}
                            </DescriptionsItem>
                            <DescriptionsItem v-if="take.note" :label="$t('stock_takes.note')">
                                {{ take.note }}
                            </DescriptionsItem>
                        </Descriptions>
                    </Card>

                    <Card style="margin-top:16px" :title="$t('global.record_audit')">
                        <Descriptions :column="1" size="small">
                            <DescriptionsItem v-if="isSuper" label="Slug">
                                <code class="muted">{{ take.slug }}</code>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.created_at')">{{ fmtDate(take.created_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="take.creator" :label="$t('global.created_by')">{{ take.creator.name }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('global.updated_at')">{{ fmtDate(take.updated_at) }}</DescriptionsItem>
                        </Descriptions>
                    </Card>
                </Col>
            </Row>
        </template>

        <template #history>
            <Card :bodyStyle="{padding:16}">
                <template #title>
                    <HistoryOutlined /> {{ $t('global.recent_activity') }}
                </template>
                <ActivityTimeline :activity="activity" />
            </Card>
        </template>
    </EntityShowTabs>
</template>

<style scoped>
.text-success { color: #389e0d; }
.text-danger { color: #d4380d; }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
</style>
