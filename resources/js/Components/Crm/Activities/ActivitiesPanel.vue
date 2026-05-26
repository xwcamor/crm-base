<script setup>
/**
 * ActivitiesPanel — timeline embebido en Show de Deal/Company/Contact.
 *
 * Diseno tipo "feed CRM profesional" (HubSpot/Pipedrive):
 *  - Cards con avatar del actor, tag de tipo coloreado, badge de prioridad
 *  - Body con preview truncado expandible
 *  - Adjunto como chip clickeable
 *  - Acciones (✓ completar / ✎ editar / 🗑 borrar) en hover
 *  - Vista Lista (timeline cronologico) o Kanban (columnas por urgencia)
 */
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import {
    Card, Button, Empty, Tag, Tooltip, Modal, Space, Select, Avatar,
    Segmented, message,
} from 'ant-design-vue';
import {
    PlusOutlined, FileTextOutlined, PhoneOutlined, MailOutlined,
    TeamOutlined, CheckSquareOutlined, EditOutlined, DeleteOutlined,
    CheckOutlined, UndoOutlined, ClockCircleOutlined, PaperClipOutlined,
    UnorderedListOutlined, AppstoreOutlined, WarningOutlined,
} from '@ant-design/icons-vue';
import ActivityFormModal from './ActivityFormModal.vue';
import { useI18n } from '@/Plugins/i18n';
import { useDateFormat } from '@/Composables/useDateFormat';
import dayjs from 'dayjs';

const { t } = useI18n();
const { formatDateTimeFull } = useDateFormat();

const props = defineProps({
    activitable: { type: Object, required: true },
    activities:  { type: Array,  default: () => [] },
    quotes:      { type: Array,  default: () => [] }, // pass through al modal
    canEdit:     { type: Boolean, default: true },
    canDelete:   { type: Boolean, default: true },
});

const viewMode     = ref('list'); // 'list' | 'kanban'
const filterType   = ref('');
const filterStatus = ref('');

const filtered = computed(() => {
    return props.activities.filter(a => {
        if (filterType.value && a.type !== filterType.value) return false;
        if (filterStatus.value === 'pending'   && a.completed_at) return false;
        if (filterStatus.value === 'completed' && !a.completed_at) return false;
        if (filterStatus.value === 'overdue'   && !a.is_overdue) return false;
        return true;
    });
});

// ── Buckets para Kanban view ─────────────────────────────────────────────
const kanbanColumns = computed(() => {
    const today    = dayjs().startOf('day');
    const tomorrow = today.add(1, 'day');
    const endWeek  = today.add(7, 'day');

    const cols = {
        overdue:   { label: t('activities.kanban_overdue'),   icon: WarningOutlined,        color: '#dc2626', items: [] },
        today:     { label: t('activities.kanban_today'),     icon: ClockCircleOutlined, color: '#f59e0b', items: [] },
        this_week: { label: t('activities.kanban_this_week'), icon: ClockCircleOutlined, color: '#0ea5e9', items: [] },
        later:     { label: t('activities.kanban_later'),     icon: ClockCircleOutlined, color: '#6b7280', items: [] },
        completed: { label: t('activities.kanban_completed'), icon: CheckOutlined,       color: '#10b981', items: [] },
    };

    filtered.value.forEach(a => {
        if (a.completed_at) {
            cols.completed.items.push(a);
            return;
        }
        if (!a.due_at) {
            cols.later.items.push(a);
            return;
        }
        const due = dayjs(a.due_at);
        if (due.isBefore(today)) {
            cols.overdue.items.push(a);
        } else if (due.isBefore(tomorrow)) {
            cols.today.items.push(a);
        } else if (due.isBefore(endWeek)) {
            cols.this_week.items.push(a);
        } else {
            cols.later.items.push(a);
        }
    });

    return cols;
});

// ── Modal de form ────────────────────────────────────────────────────────
const formOpen = ref(false);
const editing  = ref(null);

function openCreate() { editing.value = null; formOpen.value = true; }
function openEdit(a) { editing.value = a;    formOpen.value = true; }
function onSaved()   { router.reload({ preserveScroll: true }); }

// ── Acciones ─────────────────────────────────────────────────────────────
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

// ── Visual maps ──────────────────────────────────────────────────────────
const typeIcon = {
    note: FileTextOutlined, call: PhoneOutlined, email: MailOutlined,
    meeting: TeamOutlined, task: CheckSquareOutlined,
};
const typeColor = {
    note: '#6b7280', call: '#0ea5e9', email: '#8b5cf6',
    meeting: '#f59e0b', task: '#10b981',
};
const priorityColor = { low: '#94a3b8', medium: '#0ea5e9', high: '#dc2626' };

const fmt = (d) => d ? formatDateTimeFull(d) : '—';
const initials = (name) => name?.split(' ').slice(0, 2).map(w => w[0]?.toUpperCase() ?? '').join('') || '?';

const typeOptions = computed(() => [
    { value: '', label: t('activities.filter_type_all') },
    ...(['note','call','email','meeting','task'].map(k => ({ value: k, label: t(`activities.types.${k}`) }))),
]);
const statusOptions = computed(() => [
    { value: '',           label: t('activities.filter_status_all') },
    { value: 'pending',    label: t('activities.filter_status_pending') },
    { value: 'completed',  label: t('activities.filter_status_completed') },
    { value: 'overdue',    label: t('activities.filter_status_overdue') },
]);
const viewOptions = computed(() => [
    { value: 'list',   icon: UnorderedListOutlined, label: t('activities.view_list') },
    { value: 'kanban', icon: AppstoreOutlined,      label: t('activities.view_kanban') },
]);
</script>

<template>
    <Card :bodyStyle="{ padding: 16 }" class="activities-panel">
        <template #title>
            <span class="panel-title"><CheckSquareOutlined /> {{ $t('activities.panel_title') }}</span>
        </template>
        <template #extra>
            <Space :size="8" wrap>
                <Segmented v-model:value="viewMode" :options="viewOptions" size="small">
                    <template #label="{ payload }">
                        <span><component :is="payload.icon" /> {{ payload.label }}</span>
                    </template>
                </Segmented>
                <Select v-model:value="filterType" :options="typeOptions" size="small" style="width: 140px" />
                <Select v-model:value="filterStatus" :options="statusOptions" size="small" style="width: 140px" />
                <Button v-if="canEdit" type="primary" size="small" @click="openCreate">
                    <PlusOutlined /> {{ $t('activities.add') }}
                </Button>
            </Space>
        </template>

        <div v-if="filtered.length === 0" class="empty-state">
            <Empty :description="$t('activities.panel_empty')">
                <Button v-if="canEdit" type="primary" @click="openCreate">
                    <PlusOutlined /> {{ $t('activities.add') }}
                </Button>
            </Empty>
        </div>

        <!-- ─────────── VISTA LISTA (timeline) ─────────── -->
        <ul v-else-if="viewMode === 'list'" class="timeline">
            <li v-for="a in filtered" :key="a.id" class="timeline-item"
                :class="{ 'is-completed': !!a.completed_at, 'is-overdue': a.is_overdue }"
            >
                <Avatar :style="{ background: typeColor[a.type] }" class="actor-avatar">
                    <component :is="typeIcon[a.type]" />
                </Avatar>

                <div class="card-body">
                    <div class="card-head">
                        <Tag :bordered="false" class="type-tag" :style="{ background: typeColor[a.type], color: '#fff' }">
                            {{ $t(`activities.types.${a.type}`) }}
                        </Tag>
                        <strong v-if="a.subject" class="card-subject">{{ a.subject }}</strong>
                        <span v-if="a.priority && a.type === 'task'"
                            class="priority-dot"
                            :style="{ background: priorityColor[a.priority] }"
                            :title="$t(`activities.priorities.${a.priority}`)"
                        />
                        <Tag v-if="a.is_overdue" color="red" :bordered="false" class="status-tag">
                            <WarningOutlined /> {{ $t('activities.overdue_label') }}
                        </Tag>
                        <Tag v-else-if="a.completed_at" color="success" :bordered="false" class="status-tag">
                            <CheckOutlined /> {{ $t('activities.status_completed') }}
                        </Tag>

                        <div class="card-actions">
                            <Tooltip v-if="canEdit && !a.completed_at" :title="$t('activities.mark_complete')">
                                <Button size="small" type="text" @click="complete(a)"><CheckOutlined /></Button>
                            </Tooltip>
                            <Tooltip v-if="canEdit && a.completed_at" :title="$t('activities.mark_pending')">
                                <Button size="small" type="text" @click="reopen(a)"><UndoOutlined /></Button>
                            </Tooltip>
                            <Tooltip v-if="canEdit" :title="$t('activities.edit')">
                                <Button size="small" type="text" @click="openEdit(a)"><EditOutlined /></Button>
                            </Tooltip>
                            <Tooltip v-if="canDelete" :title="$t('activities.delete')">
                                <Button size="small" type="text" danger @click="confirmDelete(a)"><DeleteOutlined /></Button>
                            </Tooltip>
                        </div>
                    </div>

                    <p v-if="a.body" class="card-body-text">{{ a.body }}</p>

                    <div class="card-meta">
                        <span v-if="a.actor" class="meta-chip actor-chip">
                            <Avatar :size="18">{{ initials(a.actor.name) }}</Avatar>
                            {{ a.actor.name }}
                        </span>
                        <span v-if="a.due_at" class="meta-chip" :class="{ 'overdue-text': a.is_overdue }">
                            <ClockCircleOutlined />
                            {{ a.completed_at
                                ? $t('activities.completed_on', { date: fmt(a.completed_at) })
                                : $t('activities.due_on', { date: fmt(a.due_at) }) }}
                        </span>
                        <span v-if="a.duration_min" class="meta-chip">{{ $t('activities.duration_label', { min: a.duration_min }) }}</span>
                        <span v-if="a.outcome" class="meta-chip">{{ $t(`activities.outcomes.${a.outcome}`) }}</span>
                        <span v-if="a.location" class="meta-chip"><TeamOutlined /> {{ a.location }}</span>
                        <a v-if="a.attachment_path" :href="`/storage/${a.attachment_path}`" target="_blank" class="meta-chip attachment-chip">
                            <PaperClipOutlined /> {{ a.attachment_name || 'Adjunto' }}
                        </a>
                        <Link v-if="a.related_quote?.url" :href="a.related_quote.url" class="meta-chip quote-chip">
                            📄 {{ $t('activities.quote_link_label') }} {{ a.related_quote.reference || a.related_quote.name }}
                        </Link>
                    </div>
                </div>
            </li>
        </ul>

        <!-- ─────────── VISTA KANBAN (columnas por urgencia) ─────────── -->
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
                        <div v-if="a.related_quote" class="kanban-card-quote">
                            📄 {{ a.related_quote.reference || a.related_quote.name }}
                        </div>
                        <div class="kanban-card-meta">
                            <span v-if="a.due_at"><ClockCircleOutlined /> {{ fmt(a.due_at) }}</span>
                            <span v-if="a.attachment_path"><PaperClipOutlined /></span>
                            <span v-if="a.actor" class="kanban-actor">
                                <Avatar :size="20">{{ initials(a.actor.name) }}</Avatar>
                            </span>
                        </div>
                        <div class="kanban-card-actions">
                            <Tooltip v-if="canEdit && !a.completed_at" :title="$t('activities.mark_complete')">
                                <Button size="small" type="text" @click="complete(a)"><CheckOutlined /></Button>
                            </Tooltip>
                            <Tooltip v-if="canEdit && a.completed_at" :title="$t('activities.mark_pending')">
                                <Button size="small" type="text" @click="reopen(a)"><UndoOutlined /></Button>
                            </Tooltip>
                            <Tooltip v-if="canEdit" :title="$t('activities.edit')">
                                <Button size="small" type="text" @click="openEdit(a)"><EditOutlined /></Button>
                            </Tooltip>
                            <Tooltip v-if="canDelete" :title="$t('activities.delete')">
                                <Button size="small" type="text" danger @click="confirmDelete(a)"><DeleteOutlined /></Button>
                            </Tooltip>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <ActivityFormModal
            v-model:open="formOpen"
            :activitable="activitable"
            :editing="editing"
            :quotes="quotes"
            @saved="onSaved"
        />
    </Card>
</template>

<style scoped>
.activities-panel { border-radius: 8px; margin-bottom: 16px; }
.panel-title { font-weight: 600; }
.empty-state { padding: 40px 16px; }

/* ─── Timeline ─── */
.timeline { list-style: none; padding: 0; margin: 0; display: flex; flex-direction: column; gap: 12px; }
.timeline-item {
    display: flex; gap: 14px;
    padding: 12px;
    background: var(--color-bg, #fff);
    border: 1px solid var(--color-border-soft, #f0f0f0);
    border-radius: 8px;
    transition: all 0.15s;
}
.timeline-item:hover {
    border-color: var(--color-border, #d9d9d9);
    box-shadow: 0 1px 4px rgba(0, 0, 0, 0.05);
}
.timeline-item.is-completed { opacity: 0.65; background: var(--color-surface-alt, #fafafa); }
.timeline-item.is-completed .card-subject { text-decoration: line-through; }
.timeline-item.is-overdue { border-left: 3px solid var(--color-danger, #dc2626); }

.actor-avatar { flex-shrink: 0; align-self: flex-start; }
.card-body { flex: 1; min-width: 0; }
.card-head { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; margin-bottom: 6px; }
.type-tag { font-weight: 600; padding: 2px 8px; }
.card-subject { font-weight: 600; color: var(--color-text-strong, #111); flex: 1; min-width: 0; word-break: break-word; }
.status-tag { font-size: 0.7rem; padding: 0 6px; }
.priority-dot { display: inline-block; width: 10px; height: 10px; border-radius: 50%; }

.card-actions {
    display: inline-flex; gap: 2px; margin-left: auto;
    opacity: 0; transition: opacity 0.15s;
}
.timeline-item:hover .card-actions { opacity: 1; }
.timeline-item.is-completed .card-actions { opacity: 0.5; }

.card-body-text {
    margin: 6px 0 8px 0;
    color: var(--color-text, #333);
    font-size: 0.875rem; line-height: 1.5;
    white-space: pre-wrap; word-break: break-word;
}

.card-meta { display: flex; flex-wrap: wrap; gap: 8px; font-size: 0.78rem; color: var(--color-text-muted, #6b7280); }
.meta-chip {
    display: inline-flex; align-items: center; gap: 4px;
    padding: 2px 8px;
    background: var(--color-surface-alt, #f8f9fa);
    border-radius: 12px;
}
.actor-chip { font-weight: 500; color: var(--color-text, #333); }
.overdue-text { color: var(--color-danger, #dc2626); font-weight: 600; }
.attachment-chip {
    background: #fef3c7; color: #92400e !important;
    border: 1px solid #fcd34d;
}
.attachment-chip:hover { background: #fde68a; }
.quote-chip {
    background: #dbeafe; color: #1e40af !important;
    border: 1px solid #93c5fd;
    font-weight: 500;
}
.quote-chip:hover { background: #bfdbfe; }
.kanban-card-quote {
    background: #dbeafe; color: #1e40af;
    border-radius: 4px;
    padding: 2px 6px;
    font-size: 0.72rem;
    margin-bottom: 6px;
    display: inline-block;
}

/* ─── Kanban ─── */
.kanban {
    display: grid; grid-template-columns: repeat(5, 1fr); gap: 10px;
}
.kanban-col {
    background: var(--color-surface-alt, #f5f7fa);
    border-radius: 8px;
    min-width: 0;
    display: flex; flex-direction: column;
}
.kanban-col-head {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 12px;
    border-top: 3px solid;
    border-radius: 8px 8px 0 0;
    background: var(--color-bg, #fff);
    font-size: 0.85rem;
}
.kanban-col-title { font-weight: 600; display: flex; align-items: center; gap: 6px; }
.kanban-col-count { font-size: 0.7rem; padding: 0 6px; }
.kanban-col-body {
    padding: 8px;
    display: flex; flex-direction: column; gap: 8px;
    min-height: 80px;
    max-height: 600px;
    overflow-y: auto;
}
.kanban-empty {
    padding: 16px 8px; text-align: center;
    font-size: 0.78rem; color: var(--color-text-muted, #8c8c8c);
    font-style: italic;
}
.kanban-card {
    background: var(--color-bg, #fff);
    border: 1px solid var(--color-border-soft, #e5e7eb);
    border-left: 3px solid;
    border-radius: 6px;
    padding: 10px;
    font-size: 0.82rem;
    position: relative;
    transition: all 0.15s;
}
.kanban-card:hover { box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08); }
.kanban-card.is-completed { opacity: 0.7; }
.kanban-card-head { display: flex; justify-content: space-between; align-items: center; margin-bottom: 6px; }
.kanban-card-subject { font-weight: 600; color: var(--color-text-strong); margin-bottom: 4px; word-break: break-word; }
.kanban-card-body {
    font-size: 0.78rem; color: var(--color-text-muted, #6b7280); margin-bottom: 6px;
    display: -webkit-box; -webkit-line-clamp: 2; line-clamp: 2; -webkit-box-orient: vertical;
    overflow: hidden; word-break: break-word;
}
.kanban-card-meta {
    display: flex; align-items: center; justify-content: space-between; gap: 6px;
    font-size: 0.7rem; color: var(--color-text-muted, #8c8c8c);
}
.kanban-card-meta > span:first-child { flex: 1; }
.kanban-card-actions {
    display: flex; justify-content: flex-end; gap: 2px;
    margin-top: 6px;
    opacity: 0; transition: opacity 0.15s;
}
.kanban-card:hover .kanban-card-actions { opacity: 1; }

@media (max-width: 1100px) {
    .kanban { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 700px) {
    .kanban { grid-template-columns: 1fr; }
}
</style>
