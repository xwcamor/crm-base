<script setup>
/**
 * Global Activities Index — /crm/activities
 *
 * Vista cross-entidad de TODAS las activities del tenant. Soporta dos vistas:
 *   - Lista (tabla con paginacion server-side)
 *   - Kanban (columnas por urgencia, client-side a partir de los items recibidos)
 *
 * El parent label/url ya viene desde el server en cada item (controller).
 */
import { ref, computed, watch } from 'vue';
import { Head, router, Link } from '@inertiajs/vue3';
import {
    Card, Table, Tag, Input, Select, Button, Tooltip, Space, Avatar,
    message, Modal, Segmented,
} from 'ant-design-vue';
import {
    PlusOutlined, FileTextOutlined, PhoneOutlined, MailOutlined,
    TeamOutlined, CheckSquareOutlined, EditOutlined, DeleteOutlined,
    CheckOutlined, UndoOutlined, SearchOutlined, ClockCircleOutlined,
    PaperClipOutlined, DeleteFilled, UnorderedListOutlined, AppstoreOutlined,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ActivityFormModal from '@/Components/Crm/Activities/ActivityFormModal.vue';
import { useI18n } from '@/Plugins/i18n';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useAuth } from '@/Composables/useAuth';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { formatDateTimeFull } = useDateFormat();
const { can } = useAuth();

const props = defineProps({
    activities: { type: Object, required: true },
    filters:    { type: Object, default: () => ({}) },
    meta:       { type: Object, default: () => ({}) },
});

const viewMode = ref('list'); // 'list' | 'kanban'
const filters  = ref({
    type:        props.filters.type ?? '',
    status:      props.filters.status ?? '',
    priority:    props.filters.priority ?? '',
    scope:       props.filters.scope ?? '',
    pipeline_id: props.filters.pipeline_id ?? null,
    stage_id:    props.filters.stage_id ?? null,
    deal_status: props.filters.deal_status ?? '',
    q:           props.filters.q ?? '',
});

function applyFilters() {
    router.get(route('crm.activities.index'), {
        ...filters.value,
        per_page: props.filters.per_page ?? 25,
    }, { preserveState: true, preserveScroll: true });
}

let qTimer = null;
watch(() => filters.value.q, () => {
    clearTimeout(qTimer);
    qTimer = setTimeout(applyFilters, 300);
});
watch([
    () => filters.value.type,
    () => filters.value.status,
    () => filters.value.priority,
    () => filters.value.scope,
    () => filters.value.pipeline_id,
    () => filters.value.stage_id,
    () => filters.value.deal_status,
], applyFilters);

// Si cambia pipeline_id, resetear stage_id (las stages dependen del pipeline)
watch(() => filters.value.pipeline_id, () => {
    filters.value.stage_id = null;
});

// ── Visual maps ──
const typeIcon = {
    note: FileTextOutlined, call: PhoneOutlined, email: MailOutlined,
    meeting: TeamOutlined, task: CheckSquareOutlined,
};
const typeColor = {
    note: '#6b7280', call: '#0ea5e9', email: '#8b5cf6',
    meeting: '#f59e0b', task: '#10b981',
};
const priorityColor = { low: '#94a3b8', medium: '#0ea5e9', high: '#dc2626' };

// ── Options ──
const typeOpts = computed(() => [
    { value: '', label: t('activities.filter_type_all') },
    ...(['note','call','email','meeting','task'].map(k => ({ value: k, label: t(`activities.types.${k}`) }))),
]);
const statusOpts = computed(() => [
    { value: '',          label: t('activities.filter_status_all') },
    { value: 'pending',   label: t('activities.filter_status_pending') },
    { value: 'completed', label: t('activities.filter_status_completed') },
    { value: 'overdue',   label: t('activities.filter_status_overdue') },
]);
const scopeOpts = computed(() => [
    { value: '',     label: t('activities.filter_scope_all') },
    { value: 'mine', label: t('activities.filter_scope_mine') },
]);
const priorityOpts = computed(() => [
    { value: '',       label: t('activities.filter_priority_all') },
    { value: 'high',   label: t('activities.priorities.high') },
    { value: 'medium', label: t('activities.priorities.medium') },
    { value: 'low',    label: t('activities.priorities.low') },
]);
const pipelineOpts = computed(() => [
    { value: null, label: t('activities.filter_pipeline_all') },
    ...(props.meta?.pipelineOptions ?? []).map(p => ({ value: p.value, label: p.label, color: p.color })),
]);
const stageOpts = computed(() => {
    const stages = props.meta?.stageOptions ?? [];
    const filtered = filters.value.pipeline_id
        ? stages.filter(s => s.pipeline_id === filters.value.pipeline_id)
        : stages;
    return [
        { value: null, label: t('activities.filter_stage_all') },
        ...filtered.map(s => ({ value: s.value, label: s.label })),
    ];
});
const dealStatusOpts = computed(() => [
    { value: '', label: t('activities.filter_deal_status_all') },
    ...(props.meta?.dealStatuses ?? []).map(s => ({ value: s, label: t(`deals.status_options.${s}`) })),
]);
const viewOptions = computed(() => [
    { value: 'list',   icon: UnorderedListOutlined, label: t('activities.view_list') },
    { value: 'kanban', icon: AppstoreOutlined,      label: t('activities.view_kanban') },
]);

// ── Kanban buckets ──
const kanbanColumns = computed(() => {
    const today    = dayjs().startOf('day');
    const tomorrow = today.add(1, 'day');
    const endWeek  = today.add(7, 'day');

    const cols = {
        overdue:   { label: t('activities.kanban_overdue'),   icon: DeleteFilled,        color: '#dc2626', items: [] },
        today:     { label: t('activities.kanban_today'),     icon: ClockCircleOutlined, color: '#f59e0b', items: [] },
        this_week: { label: t('activities.kanban_this_week'), icon: ClockCircleOutlined, color: '#0ea5e9', items: [] },
        later:     { label: t('activities.kanban_later'),     icon: ClockCircleOutlined, color: '#6b7280', items: [] },
        completed: { label: t('activities.kanban_completed'), icon: CheckOutlined,       color: '#10b981', items: [] },
    };
    (props.activities?.data ?? []).forEach(a => {
        if (a.completed_at) { cols.completed.items.push(a); return; }
        if (!a.due_at)      { cols.later.items.push(a); return; }
        const due = dayjs(a.due_at);
        if      (due.isBefore(today))    cols.overdue.items.push(a);
        else if (due.isBefore(tomorrow)) cols.today.items.push(a);
        else if (due.isBefore(endWeek))  cols.this_week.items.push(a);
        else                             cols.later.items.push(a);
    });
    return cols;
});

const fmt = (d) => d ? formatDateTimeFull(d) : '—';
const initials = (name) => name?.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('') || '?';

function complete(a) {
    router.post(route('crm.activities.complete', a.slug), {}, {
        preserveScroll: true,
        onSuccess: () => message.success(t('activities.completed')),
    });
}
function reopen(a) {
    router.post(route('crm.activities.reopen', a.slug), {}, {
        preserveScroll: true,
        onSuccess: () => message.success(t('activities.reopened')),
    });
}
function confirmDelete(a) {
    Modal.confirm({
        title: t('activities.delete'),
        content: t('activities.delete_confirm'),
        okText: t('activities.delete'),
        okType: 'danger',
        cancelText: t('activities.cancel'),
        onOk() { router.delete(route('crm.activities.destroy', a.slug), { preserveScroll: true }); },
    });
}

const formOpen = ref(false);
const editing  = ref(null);
function openEdit(a) { editing.value = a; formOpen.value = true; }
function onSaved() { router.reload({ preserveScroll: true }); }

const tablePagination = computed(() => ({
    current:  props.activities.current_page,
    pageSize: props.activities.per_page,
    total:    props.activities.total,
    showSizeChanger: false,
}));

function onTableChange(pag) {
    router.get(route('crm.activities.index'), {
        ...filters.value,
        page: pag.current,
        per_page: pag.pageSize,
    }, { preserveState: true, preserveScroll: true });
}
</script>

<template>
    <Head :title="$t('activities.index_title')" />

    <div class="activities-index">
        <SectionHeader
            :title="$t('activities.index_title')"
            :subtitle="$t('activities.index_subtitle')"
            icon-bg="var(--color-primary)"
        >
            <template #icon><CheckSquareOutlined /></template>
        </SectionHeader>

        <Card :bodyStyle="{ padding: 16 }">
            <div class="toolbar">
                <Segmented v-model:value="viewMode" :options="viewOptions">
                    <template #label="{ payload }">
                        <span><component :is="payload.icon" /> {{ payload.label }}</span>
                    </template>
                </Segmented>
                <div class="filters">
                    <Input
                        v-model:value="filters.q"
                        :placeholder="$t('activities.filter_search')"
                        allow-clear
                        style="max-width: 240px"
                    >
                        <template #prefix><SearchOutlined /></template>
                    </Input>
                    <Select v-model:value="filters.type"        :options="typeOpts"        style="width: 140px" />
                    <Select v-model:value="filters.status"      :options="statusOpts"      style="width: 140px" />
                    <Select v-model:value="filters.priority"    :options="priorityOpts"    style="width: 140px" />
                    <Select v-model:value="filters.scope"       :options="scopeOpts"       style="width: 140px" />
                </div>
            </div>

            <!-- Filtros avanzados de Deal/Pipeline (siempre visibles, son la clave del CRM) -->
            <div class="filters-pipeline">
                <span class="filters-label">{{ $t('activities.filter_section_pipeline') }}:</span>
                <Select v-model:value="filters.pipeline_id" :options="pipelineOpts" style="width: 180px" :placeholder="$t('activities.filter_pipeline_placeholder')" />
                <Select v-model:value="filters.stage_id"    :options="stageOpts"    style="width: 180px" :placeholder="$t('activities.filter_stage_placeholder')" :disabled="!filters.pipeline_id" />
                <Select v-model:value="filters.deal_status" :options="dealStatusOpts" style="width: 160px" :placeholder="$t('activities.filter_deal_status_placeholder')" />
            </div>

            <!-- ─── LISTA ─── -->
            <Table v-if="viewMode === 'list'"
                :dataSource="activities.data"
                :pagination="tablePagination"
                @change="onTableChange"
                rowKey="id"
                size="middle"
                class="activities-table"
            >
                <Table.Column key="type" :title="$t('activities.type')" :width="130">
                        <template #default="{ record }">
                            <Tag :bordered="false" :style="{ background: typeColor[record.type], color: '#fff', borderColor: 'transparent' }">
                                <component :is="typeIcon[record.type]" /> {{ $t(`activities.types.${record.type}`) }}
                            </Tag>
                        </template>
                    </Table.Column>
                    <Table.Column key="subject" :title="$t('activities.subject')">
                        <template #default="{ record }">
                            <div>
                                <strong v-if="record.subject" :class="{ 'completed-line': !!record.completed_at }">
                                    {{ record.subject }}
                                </strong>
                                <div v-if="record.body" class="body-preview">
                                    {{ record.body.length > 100 ? record.body.slice(0, 100) + '…' : record.body }}
                                </div>
                                <a v-if="record.attachment_path" :href="`/storage/${record.attachment_path}`" target="_blank" class="attachment-link">
                                    <PaperClipOutlined /> {{ record.attachment_name || 'Adjunto' }}
                                </a>
                            </div>
                        </template>
                    </Table.Column>
                    <Table.Column key="parent" :title="$t('activities.col_parent')" :width="220">
                        <template #default="{ record }">
                            <div v-if="record.parent_label">
                                <Tag :bordered="false">{{ $t(`activities.parent_${record.activitable_type}`) }}</Tag>
                                <Link v-if="record.parent_url" :href="record.parent_url" class="parent-link">
                                    {{ record.parent_label }}
                                </Link>
                                <span v-else>{{ record.parent_label }}</span>
                            </div>
                        </template>
                    </Table.Column>
                    <Table.Column key="pipeline" :title="$t('activities.col_pipeline')" :width="200">
                        <template #default="{ record }">
                            <div v-if="record.parent_extra">
                                <Tag v-if="record.parent_extra.pipeline"
                                    :bordered="false"
                                    :style="{ background: record.parent_extra.pipeline.color || '#888', color: '#fff', borderColor: 'transparent', fontSize: '0.72rem' }"
                                >
                                    {{ record.parent_extra.pipeline.name }}
                                </Tag>
                                <Tag v-if="record.parent_extra.stage"
                                    :bordered="false"
                                    :style="{ background: record.parent_extra.stage.color || '#888', color: '#fff', borderColor: 'transparent', fontSize: '0.72rem', marginLeft: '4px' }"
                                >
                                    {{ record.parent_extra.stage.name }}
                                </Tag>
                                <Tag v-if="record.parent_extra.deal_status === 'won'" color="success" :bordered="false">WON</Tag>
                                <Tag v-else-if="record.parent_extra.deal_status === 'lost'" color="error" :bordered="false">LOST</Tag>
                            </div>
                            <span v-else class="muted">—</span>
                        </template>
                    </Table.Column>
                    <Table.Column key="quote" :title="$t('activities.col_quote')" :width="160">
                        <template #default="{ record }">
                            <Link v-if="record.related_quote?.url" :href="record.related_quote.url" class="quote-link">
                                📄 {{ record.related_quote.reference || record.related_quote.name }}
                            </Link>
                            <span v-else class="muted">—</span>
                        </template>
                    </Table.Column>
                    <Table.Column key="priority" :title="$t('activities.priority')" :width="100">
                        <template #default="{ record }">
                            <Tag v-if="record.priority"
                                :bordered="false"
                                :color="record.priority === 'high' ? 'red' : record.priority === 'medium' ? 'blue' : 'default'"
                            >
                                {{ $t(`activities.priorities.${record.priority}`) }}
                            </Tag>
                            <span v-else class="muted">—</span>
                        </template>
                    </Table.Column>
                    <Table.Column key="due" :title="$t('activities.due_at')" :width="200">
                        <template #default="{ record }">
                            <span v-if="record.due_at" :class="{ 'overdue-text': record.is_overdue }">
                                <ClockCircleOutlined /> {{ fmt(record.due_at) }}
                            </span>
                            <span v-else class="muted">—</span>
                        </template>
                    </Table.Column>
                    <Table.Column key="status" :title="$t('activities.status')" :width="140">
                        <template #default="{ record }">
                            <Tag v-if="record.completed_at" color="success" :bordered="false">
                                <CheckOutlined /> {{ $t('activities.status_completed') }}
                            </Tag>
                            <Tag v-else-if="record.is_overdue" color="red" :bordered="false">
                                <DeleteFilled /> {{ $t('activities.status_overdue') }}
                            </Tag>
                            <Tag v-else color="processing" :bordered="false">
                                {{ $t('activities.status_pending') }}
                            </Tag>
                        </template>
                    </Table.Column>
                    <Table.Column key="actor" :title="$t('activities.logged_by')" :width="170">
                        <template #default="{ record }">
                            <Space v-if="record.actor" :size="6">
                                <Avatar :size="22">{{ initials(record.actor.name) }}</Avatar>
                                <span>{{ record.actor.name }}</span>
                            </Space>
                            <span v-else>—</span>
                        </template>
                    </Table.Column>
                    <Table.Column key="actions" :title="''" :width="160">
                        <template #default="{ record }">
                            <Space :size="4">
                                <Tooltip v-if="can('activities.edit') && !record.completed_at" :title="$t('activities.mark_complete')">
                                    <Button size="small" type="text" @click="complete(record)"><CheckOutlined /></Button>
                                </Tooltip>
                                <Tooltip v-if="can('activities.edit') && record.completed_at" :title="$t('activities.mark_pending')">
                                    <Button size="small" type="text" @click="reopen(record)"><UndoOutlined /></Button>
                                </Tooltip>
                                <Tooltip v-if="can('activities.edit')" :title="$t('activities.edit')">
                                    <Button size="small" type="text" @click="openEdit(record)"><EditOutlined /></Button>
                                </Tooltip>
                                <Tooltip v-if="can('activities.delete')" :title="$t('activities.delete')">
                                    <Button size="small" type="text" danger @click="confirmDelete(record)"><DeleteOutlined /></Button>
                                </Tooltip>
                            </Space>
                        </template>
                    </Table.Column>
            </Table>

            <!-- ─── KANBAN ─── -->
            <div v-else class="kanban">
                <div v-for="(col, key) in kanbanColumns" :key="key" class="kanban-col">
                    <div class="kanban-col-head" :style="{ borderTopColor: col.color }">
                        <span class="kanban-col-title">
                            <component :is="col.icon" :style="{ color: col.color }" /> {{ col.label }}
                        </span>
                        <Tag :bordered="false" class="kanban-col-count">{{ col.items.length }}</Tag>
                    </div>
                    <div class="kanban-col-body">
                        <div v-if="col.items.length === 0" class="kanban-empty">
                            {{ $t('activities.kanban_empty_column') }}
                        </div>
                        <div v-else v-for="a in col.items" :key="a.id" class="kanban-card"
                            :class="{ 'is-completed': !!a.completed_at }"
                            :style="{ borderLeftColor: typeColor[a.type] }"
                        >
                            <div class="kanban-card-head">
                                <Tag :bordered="false" :style="{ background: typeColor[a.type], color: '#fff' }">
                                    <component :is="typeIcon[a.type]" /> {{ $t(`activities.types.${a.type}`) }}
                                </Tag>
                                <span v-if="a.priority && a.type === 'task'"
                                    class="priority-dot"
                                    :style="{ background: priorityColor[a.priority] }"
                                />
                            </div>
                            <div v-if="a.subject" class="kanban-card-subject">{{ a.subject }}</div>
                            <div v-if="a.body" class="kanban-card-body">{{ a.body }}</div>
                            <div v-if="a.parent_label" class="kanban-card-parent">
                                <Tag :bordered="false">{{ $t(`activities.parent_${a.activitable_type}`) }}</Tag>
                                <Link v-if="a.parent_url" :href="a.parent_url">{{ a.parent_label }}</Link>
                                <span v-else>{{ a.parent_label }}</span>
                            </div>
                            <div class="kanban-card-meta">
                                <span v-if="a.due_at"><ClockCircleOutlined /> {{ fmt(a.due_at) }}</span>
                                <a v-if="a.attachment_path" :href="`/storage/${a.attachment_path}`" target="_blank" class="attachment-mini">
                                    <PaperClipOutlined />
                                </a>
                                <span v-if="a.actor" class="kanban-actor">
                                    <Tooltip :title="a.actor.name">
                                        <Avatar :size="20">{{ initials(a.actor.name) }}</Avatar>
                                    </Tooltip>
                                </span>
                            </div>
                            <div class="kanban-card-actions">
                                <Tooltip v-if="can('activities.edit') && !a.completed_at" :title="$t('activities.mark_complete')">
                                    <Button size="small" type="text" @click="complete(a)"><CheckOutlined /></Button>
                                </Tooltip>
                                <Tooltip v-if="can('activities.edit') && a.completed_at" :title="$t('activities.mark_pending')">
                                    <Button size="small" type="text" @click="reopen(a)"><UndoOutlined /></Button>
                                </Tooltip>
                                <Tooltip v-if="can('activities.edit')" :title="$t('activities.edit')">
                                    <Button size="small" type="text" @click="openEdit(a)"><EditOutlined /></Button>
                                </Tooltip>
                                <Tooltip v-if="can('activities.delete')" :title="$t('activities.delete')">
                                    <Button size="small" type="text" danger @click="confirmDelete(a)"><DeleteOutlined /></Button>
                                </Tooltip>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </Card>

        <ActivityFormModal
            v-model:open="formOpen"
            :editing="editing"
            @saved="onSaved"
        />
    </div>
</template>

<style scoped>
.activities-index { padding: 0; }

.toolbar {
    display: flex; justify-content: space-between; gap: 16px;
    flex-wrap: wrap; margin-bottom: 16px; align-items: center;
}
.filters {
    display: flex; gap: 8px; flex-wrap: wrap; align-items: center;
}

.body-preview {
    font-size: 0.78rem; color: var(--color-text-muted, #8c8c8c);
    margin-top: 2px; word-break: break-word;
}
.attachment-link {
    display: inline-flex; align-items: center; gap: 4px;
    font-size: 0.78rem; color: var(--color-primary, #1677ff);
    margin-top: 4px;
}
.attachment-link:hover { text-decoration: underline; }

.filters-pipeline {
    display: flex; align-items: center; gap: 8px; flex-wrap: wrap;
    padding: 10px 12px;
    background: var(--color-surface-alt, #f0f5ff);
    border-radius: 6px; margin-bottom: 16px;
}
.filters-label { font-size: 0.82rem; color: var(--color-text-muted); font-weight: 500; }
.quote-link {
    display: inline-flex; align-items: center; gap: 4px;
    color: #1e40af; font-weight: 500; font-size: 0.82rem;
}
.quote-link:hover { text-decoration: underline; }
.completed-line { text-decoration: line-through; opacity: 0.7; }
.overdue-text { color: var(--color-danger, #dc2626); font-weight: 600; }
.muted { color: var(--color-text-muted, #8c8c8c); }
.parent-link { margin-left: 6px; color: var(--color-primary, #1677ff); }
.parent-link:hover { text-decoration: underline; }

/* ── Kanban ── */
.kanban {
    display: grid; grid-template-columns: repeat(5, 1fr); gap: 12px;
}
.kanban-col {
    background: var(--color-surface-alt, #f5f7fa);
    border-radius: 8px;
    min-width: 0;
    display: flex; flex-direction: column;
}
.kanban-col-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 12px;
    border-top: 3px solid;
    border-radius: 8px 8px 0 0;
    background: var(--color-bg, #fff);
    font-size: 0.875rem;
}
.kanban-col-title { font-weight: 600; display: flex; align-items: center; gap: 6px; }
.kanban-col-count { font-size: 0.72rem; padding: 0 8px; }
.kanban-col-body {
    padding: 10px;
    display: flex; flex-direction: column; gap: 10px;
    min-height: 100px;
    max-height: 700px;
    overflow-y: auto;
}
.kanban-empty {
    padding: 20px 8px; text-align: center;
    font-size: 0.82rem; color: var(--color-text-muted, #8c8c8c);
    font-style: italic;
}
.kanban-card {
    background: var(--color-bg, #fff);
    border: 1px solid var(--color-border-soft, #e5e7eb);
    border-left: 3px solid;
    border-radius: 8px;
    padding: 12px;
    font-size: 0.85rem;
    transition: all 0.15s;
}
.kanban-card:hover { box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1); transform: translateY(-1px); }
.kanban-card.is-completed { opacity: 0.7; }
.kanban-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px; }
.kanban-card-subject { font-weight: 600; color: var(--color-text-strong); margin-bottom: 4px; word-break: break-word; }
.kanban-card-body {
    font-size: 0.78rem; color: var(--color-text-muted, #6b7280); margin-bottom: 8px;
    display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden; word-break: break-word;
}
.kanban-card-parent {
    font-size: 0.75rem; margin-bottom: 8px;
    padding-bottom: 6px; border-bottom: 1px dashed var(--color-border-soft, #e5e7eb);
}
.kanban-card-parent a { color: var(--color-primary, #1677ff); margin-left: 4px; }
.kanban-card-meta {
    display: flex; align-items: center; justify-content: space-between; gap: 6px;
    font-size: 0.72rem; color: var(--color-text-muted, #8c8c8c);
}
.kanban-card-meta > span:first-child { flex: 1; }
.attachment-mini { color: var(--color-primary, #1677ff); font-size: 0.9rem; }
.priority-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }
.kanban-card-actions {
    display: flex; justify-content: flex-end; gap: 2px;
    margin-top: 8px;
    padding-top: 6px;
    border-top: 1px dashed var(--color-border-soft, #f0f0f0);
    opacity: 0; transition: opacity 0.15s;
}
.kanban-card:hover .kanban-card-actions { opacity: 1; }

@media (max-width: 1100px) {
    .kanban { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 700px) {
    .kanban { grid-template-columns: 1fr; }
    .toolbar { flex-direction: column; align-items: stretch; }
}
</style>
