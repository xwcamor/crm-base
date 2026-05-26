<script setup>
/**
 * Stage Editor — drawer con CRUD inline de las etapas del pipeline.
 *
 * Modelo de UX (estilo Pipedrive):
 *   - Lista de stages con drag handle (HTML5 drag-and-drop nativo, sin libs).
 *   - Botones edit / delete por stage.
 *   - "Add stage" abre un Modal con el form completo (mismo form para edit).
 *   - Submit usa Inertia router → back-redirect refresca props sin SPA reload.
 *
 * Reordenar usa el endpoint `/stages/reorder` con array de IDs.
 * Borrar valida server-side que la stage no tenga deals (bloqueo amigable).
 */
import { ref, reactive, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Drawer, Button, Modal, Form, FormItem, Input, InputNumber, Switch,
    Tag, Tooltip, Empty, message,
} from 'ant-design-vue';
import {
    PlusOutlined, EditOutlined, DeleteOutlined, HolderOutlined,
} from '@ant-design/icons-vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

const props = defineProps({
    pipelineSlug: { type: String, required: true },
    stages:       { type: Array,  default: () => [] },
});

const open      = ref(false);
const formOpen  = ref(false);
const editingId = ref(null);
const submitting= ref(false);

const blank = () => ({
    name: '', description: '', color: '#888888',
    probability_pct: 0, is_won: false, is_lost: false,
    rot_days: 0, is_active: true,
});

const form = reactive(blank());
const errors = ref({});

const isEditing = computed(() => editingId.value !== null);

function openDrawer() { open.value = true; }
function closeDrawer() { open.value = false; }
defineExpose({ openDrawer });

function openCreate() {
    Object.assign(form, blank());
    editingId.value = null;
    errors.value = {};
    formOpen.value = true;
}

function openEdit(stage) {
    Object.assign(form, {
        name: stage.name,
        description: stage.description ?? '',
        color: stage.color ?? '#888888',
        probability_pct: stage.probability_pct ?? 0,
        is_won: !!stage.is_won,
        is_lost: !!stage.is_lost,
        rot_days: stage.rot_days ?? 0,
        is_active: stage.is_active !== false,
    });
    editingId.value = stage.slug;
    errors.value = {};
    formOpen.value = true;
}

function submit() {
    submitting.value = true;
    errors.value = {};
    const onError = (e) => { errors.value = e; submitting.value = false; };
    const onSuccess = () => {
        formOpen.value = false;
        submitting.value = false;
        message.success(isEditing.value
            ? window.$t?.('pipeline_stages.saved') ?? 'Saved'
            : window.$t?.('pipeline_stages.created') ?? 'Created');
    };

    if (isEditing.value) {
        router.put(route('crm.pipelines.stages.update', [props.pipelineSlug, editingId.value]),
            { ...form }, { preserveScroll: true, onError, onSuccess });
    } else {
        router.post(route('crm.pipelines.stages.store', props.pipelineSlug),
            { ...form }, { preserveScroll: true, onError, onSuccess });
    }
}

function confirmDelete(stage) {
    Modal.confirm({
        title: window.$t?.('pipeline_stages.delete') ?? 'Delete stage',
        content: (window.$t?.('pipeline_stages.delete_confirm', { name: stage.name }) ?? `Delete "${stage.name}"?`),
        okText: window.$t?.('pipeline_stages.delete') ?? 'Delete',
        okType: 'danger',
        cancelText: window.$t?.('pipeline_stages.cancel') ?? 'Cancel',
        onOk() {
            router.delete(route('crm.pipelines.stages.destroy', [props.pipelineSlug, stage.slug]),
                { preserveScroll: true });
        },
    });
}

// ─── Drag-and-drop (HTML5 nativo) ────────────────────────────────────────
// Mantiene una copia local de la lista durante el drag para mostrar feedback
// inmediato sin esperar al round-trip al server. Cuando se suelta, mandamos
// el order al endpoint /reorder y el back-redirect refresca props.
const dragSourceIdx = ref(null);
const dragHoverIdx  = ref(null);
const localOrder    = ref(null);

const visibleStages = computed(() => localOrder.value ?? props.stages);

function onDragStart(idx, event) {
    dragSourceIdx.value = idx;
    localOrder.value = [...props.stages];
    event.dataTransfer.effectAllowed = 'move';
    event.dataTransfer.setData('text/plain', String(idx));
}
function onDragOver(idx, event) {
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    if (dragSourceIdx.value === null || dragSourceIdx.value === idx) return;
    if (dragHoverIdx.value === idx) return;
    dragHoverIdx.value = idx;
    const list = [...(localOrder.value ?? props.stages)];
    const [moved] = list.splice(dragSourceIdx.value, 1);
    list.splice(idx, 0, moved);
    dragSourceIdx.value = idx;
    localOrder.value = list;
}
function onDragEnd() {
    if (localOrder.value && dragSourceIdx.value !== null) {
        const order = localOrder.value.map(s => s.id);
        const original = props.stages.map(s => s.id).join(',');
        if (order.join(',') !== original) {
            router.post(route('crm.pipelines.stages.reorder', props.pipelineSlug),
                { order },
                { preserveScroll: true, onFinish: () => { localOrder.value = null; } });
        } else {
            localOrder.value = null;
        }
    }
    dragSourceIdx.value = null;
    dragHoverIdx.value  = null;
}

// Toggle won/lost mutuamente excluyente — al activar uno, desactivar el otro
// y autoset probability (won=100, lost=0).
function onToggleWon(v) {
    form.is_won = v;
    if (v) {
        form.is_lost = false;
        form.probability_pct = 100;
    }
}
function onToggleLost(v) {
    form.is_lost = v;
    if (v) {
        form.is_won = false;
        form.probability_pct = 0;
    }
}
</script>

<template>
    <Drawer
        :open="open"
        :width="560"
        :title="$t('pipeline_stages.manage_title')"
        @close="closeDrawer"
        :bodyStyle="{ padding: 16 }"
    >
        <template #extra>
            <Button type="primary" @click="openCreate">
                <PlusOutlined /> {{ $t('pipeline_stages.add') }}
            </Button>
        </template>

        <p class="muted" style="margin-bottom: 12px">
            {{ $t('pipeline_stages.manage_subtitle') }}
        </p>

        <div v-if="stages.length === 0" class="empty-wrap">
            <Empty :description="$t('pipeline_stages.empty')">
                <Button type="primary" @click="openCreate">
                    <PlusOutlined /> {{ $t('pipeline_stages.add_first') }}
                </Button>
            </Empty>
        </div>

        <div v-else class="stage-list">
            <div
                v-for="(s, idx) in visibleStages"
                :key="s.id"
                class="stage-row"
                :class="{ 'is-dragging': dragSourceIdx === idx }"
                :style="{ borderLeftColor: s.color || '#888' }"
                draggable="true"
                @dragstart="onDragStart(idx, $event)"
                @dragover="onDragOver(idx, $event)"
                @dragend="onDragEnd"
                @drop.prevent
            >
                <div class="stage-row-left">
                    <Tooltip :title="$t('pipeline_stages.drag_to_reorder')">
                        <span class="drag-handle" aria-label="drag handle">
                            <HolderOutlined />
                        </span>
                    </Tooltip>
                    <div class="stage-info">
                        <div class="stage-name-line">
                            <strong>{{ s.name }}</strong>
                            <Tag v-if="s.is_won" color="green" :bordered="false">{{ $t('pipeline_stages.tag_won') }}</Tag>
                            <Tag v-else-if="s.is_lost" color="red" :bordered="false">{{ $t('pipeline_stages.tag_lost') }}</Tag>
                            <Tag v-else color="blue" :bordered="false">{{ s.probability_pct }}%</Tag>
                            <Tag v-if="!s.is_active" :bordered="false">{{ $t('global.inactive') }}</Tag>
                        </div>
                        <div v-if="s.description" class="muted small">{{ s.description }}</div>
                        <div v-if="s.rot_days > 0" class="muted small">
                            {{ $t('pipelines.stage_rot_after') }}: {{ s.rot_days }}d
                        </div>
                    </div>
                </div>
                <div class="stage-row-actions">
                    <Tooltip :title="$t('pipeline_stages.edit')">
                        <Button size="small" @click="openEdit(s)"><EditOutlined /></Button>
                    </Tooltip>
                    <Tooltip :title="$t('pipeline_stages.delete')">
                        <Button size="small" danger @click="confirmDelete(s)"><DeleteOutlined /></Button>
                    </Tooltip>
                </div>
            </div>
        </div>
    </Drawer>

    <Modal
        :open="formOpen"
        :title="isEditing ? $t('pipeline_stages.edit') : $t('pipeline_stages.add')"
        :confirm-loading="submitting"
        :ok-text="$t('pipeline_stages.save')"
        :cancel-text="$t('pipeline_stages.cancel')"
        @ok="submit"
        @cancel="formOpen = false"
        :width="520"
    >
        <Form layout="vertical">
            <FormItem required :validate-status="errors.name ? 'error' : ''" :help="errors.name?.[0] ?? errors.name">
                <template #label><LabelWithHelp :label="$t('pipeline_stages.name')" :help="$t('pipeline_stages.name_hint')" /></template>
                <Input v-model:value="form.name" :maxlength="120" showCount autofocus
                    :placeholder="$t('pipeline_stages.name_placeholder')" />
            </FormItem>

            <FormItem :validate-status="errors.description ? 'error' : ''" :help="errors.description?.[0] ?? errors.description">
                <template #label><LabelWithHelp :label="$t('pipeline_stages.description')" :help="$t('pipeline_stages.description_hint')" /></template>
                <Input.TextArea v-model:value="form.description" :rows="2" :maxlength="500" showCount />
            </FormItem>

            <div class="row-two">
                <FormItem :validate-status="errors.color ? 'error' : ''" :help="errors.color?.[0] ?? errors.color">
                    <template #label><LabelWithHelp :label="$t('pipeline_stages.color')" :help="$t('pipeline_stages.color_hint')" /></template>
                    <div class="color-input">
                        <input type="color" v-model="form.color" class="color-swatch" />
                        <Input v-model:value="form.color" :maxlength="16" style="max-width: 110px" />
                    </div>
                </FormItem>

                <FormItem :validate-status="errors.probability_pct ? 'error' : ''" :help="errors.probability_pct?.[0] ?? errors.probability_pct">
                    <template #label><LabelWithHelp :label="$t('pipeline_stages.probability_pct')" :help="$t('pipeline_stages.probability_hint')" /></template>
                    <InputNumber v-model:value="form.probability_pct" :min="0" :max="100" :step="5" style="width: 100%" />
                </FormItem>
            </div>

            <FormItem :validate-status="errors.rot_days ? 'error' : ''" :help="errors.rot_days?.[0] ?? errors.rot_days">
                <template #label><LabelWithHelp :label="$t('pipeline_stages.rot_days')" :help="$t('pipeline_stages.rot_days_hint')" /></template>
                <InputNumber v-model:value="form.rot_days" :min="0" :step="1" style="width: 100%" />
            </FormItem>

            <div class="row-three">
                <FormItem :validate-status="errors.is_won ? 'error' : ''" :help="errors.is_won?.[0] ?? errors.is_won">
                    <template #label><LabelWithHelp :label="$t('pipeline_stages.is_won')" :help="$t('pipeline_stages.is_won_hint')" /></template>
                    <Switch :checked="form.is_won" @update:checked="onToggleWon" />
                </FormItem>

                <FormItem>
                    <template #label><LabelWithHelp :label="$t('pipeline_stages.is_lost')" :help="$t('pipeline_stages.is_lost_hint')" /></template>
                    <Switch :checked="form.is_lost" @update:checked="onToggleLost" />
                </FormItem>

                <FormItem>
                    <template #label><LabelWithHelp :label="$t('pipeline_stages.is_active')" :help="$t('pipeline_stages.is_active_hint')" /></template>
                    <Switch v-model:checked="form.is_active" />
                </FormItem>
            </div>
        </Form>
    </Modal>
</template>

<style scoped>
.muted { color: var(--color-text-muted, #8c8c8c); font-size: 0.85rem; }
.small { font-size: 0.78rem; }
.empty-wrap { padding: 32px 16px; }

.stage-list { display: flex; flex-direction: column; gap: 8px; }
.stage-row {
    display: flex; justify-content: space-between; align-items: center;
    background: var(--color-surface-alt, #fafafa);
    border: 1px solid var(--color-border, #e8e8e8);
    border-left: 4px solid;
    border-radius: 6px;
    padding: 10px 12px;
    transition: opacity 0.15s, background 0.15s;
}
.stage-row-left { display: flex; align-items: center; gap: 8px; flex: 1; min-width: 0; }
.drag-handle {
    display: inline-flex; align-items: center; justify-content: center;
    width: 24px; height: 24px;
    color: var(--color-text-muted, #8c8c8c);
    cursor: grab;
    border-radius: 4px;
    flex-shrink: 0;
}
.drag-handle:hover { background: var(--color-surface-hover, #f0f0f0); color: var(--color-text); }
.drag-handle:active { cursor: grabbing; }
.stage-row.is-dragging { opacity: 0.4; }
.stage-info { flex: 1; min-width: 0; }
.stage-name-line { display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
.stage-row-actions { display: flex; gap: 6px; flex-shrink: 0; }

.row-two { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
.row-three { display: grid; grid-template-columns: repeat(3, 1fr); gap: 16px; }

.color-input { display: flex; align-items: center; gap: 8px; }
.color-swatch {
    width: 38px; height: 32px; border-radius: 4px;
    border: 1px solid var(--color-border, #d9d9d9); cursor: pointer;
    padding: 0; background: transparent;
}

@media (max-width: 600px) {
    .row-two, .row-three { grid-template-columns: 1fr; }
}
</style>
