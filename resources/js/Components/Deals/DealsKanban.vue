<script setup>
/**
 * DealsKanban — vista Kanban de Deals con drag-and-drop entre stages.
 *
 * Soporta multiples pipelines: tabs en la parte superior para alternar
 * entre Pipeline de Ventas, Pipeline Marketing, etc. Cada tab muestra
 * sus columnas (stages) con las cards de deals que pertenecen a ese
 * pipeline + ese stage.
 *
 * Drag-and-drop nativo HTML5 (sin libs externas). Mover una card a otra
 * columna dispara POST /crm/deals/{slug}/change-stage con el nuevo
 * stage_id. La UI se actualiza optimisticamente y rolback si el server
 * rechaza (validacion: el stage destino debe ser del mismo pipeline).
 */
import { ref, computed } from 'vue';
import { router, Link } from '@inertiajs/vue3';
import { Card, Tag, Empty, Avatar, message } from 'ant-design-vue';
import { DollarOutlined, UserOutlined, CalendarOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    deals: { type: Array, default: () => [] },
    pipelinesWithStages: { type: Array, default: () => [] },
    canEdit: { type: Boolean, default: false },
});

// El tab activo de pipeline. Default: primer pipeline disponible.
const activePipelineId = ref(props.pipelinesWithStages[0]?.id ?? null);

const activePipeline = computed(() =>
    props.pipelinesWithStages.find(p => p.id === activePipelineId.value)
);

// Cards agrupadas por stage_id. Filtramos solo deals del pipeline activo.
// Excluye los Won/Lost por default — opcional toggle "Mostrar cerrados"
// en futuro. Por ahora solo deals open.
const dealsByStage = computed(() => {
    const result = {};
    if (!activePipeline.value) return result;
    for (const stage of activePipeline.value.stages) {
        result[stage.id] = [];
    }
    for (const deal of props.deals) {
        if (deal.pipeline_id !== activePipelineId.value) continue;
        if (deal.status && deal.status !== 'open') continue;
        if (result[deal.stage_id]) {
            result[deal.stage_id].push(deal);
        }
    }
    return result;
});

const stageTotal = (stageId) => {
    const items = dealsByStage.value[stageId] ?? [];
    return items.reduce((sum, d) => sum + Number(d.value || 0), 0);
};

const fmtMoney = (n) => {
    if (n == null) return '0';
    const v = Number(n);
    if (!Number.isFinite(v)) return '0';
    return v.toLocaleString('es', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};

// ─── Drag-and-drop state ─────────────────────────────────────────────
const draggedDeal = ref(null);
const dragOverStageId = ref(null);

function onDragStart(deal) {
    if (!props.canEdit) return;
    draggedDeal.value = deal;
}

function onDragOver(event, stageId) {
    if (!draggedDeal.value) return;
    event.preventDefault();
    event.dataTransfer.dropEffect = 'move';
    dragOverStageId.value = stageId;
}

function onDragLeave(stageId) {
    if (dragOverStageId.value === stageId) {
        dragOverStageId.value = null;
    }
}

function onDrop(event, targetStageId) {
    event.preventDefault();
    dragOverStageId.value = null;

    const deal = draggedDeal.value;
    draggedDeal.value = null;
    if (!deal) return;
    if (deal.stage_id === targetStageId) return;

    // Mismo pipeline check (UI-side; el server tambien valida).
    const stage = activePipeline.value?.stages.find(s => s.id === targetStageId);
    if (!stage) {
        message.error('Stage no encontrado en el pipeline activo.');
        return;
    }

    // Optimistic update — mutamos el objeto local. El reload de Inertia
    // sincroniza con el server. Si el server falla, recargamos para
    // revertir (caso raro porque ya validamos cliente-side).
    const oldStageId = deal.stage_id;
    deal.stage_id = targetStageId;

    router.post(
        route('crm.deals.change_stage', deal.slug),
        { stage_id: targetStageId },
        {
            preserveScroll: true,
            preserveState: true,
            onError: () => {
                deal.stage_id = oldStageId; // rollback optimista
                message.error('No se pudo cambiar el stage.');
            },
            onSuccess: () => {
                message.success('Stage actualizado.');
            },
        }
    );
}
</script>

<template>
    <div class="deals-kanban">
        <!-- Tabs de pipeline si hay > 1 -->
        <div v-if="pipelinesWithStages.length > 1" class="pipeline-tabs">
            <button
                v-for="p in pipelinesWithStages"
                :key="p.id"
                :class="['pipeline-tab', { active: p.id === activePipelineId }]"
                @click="activePipelineId = p.id"
            >
                <span class="pipeline-dot" :style="{ background: p.color || '#888' }" />
                {{ p.name }}
            </button>
        </div>

        <Empty
            v-if="!activePipeline || activePipeline.stages.length === 0"
            description="Este pipeline no tiene etapas configuradas."
            style="padding: 64px"
        />

        <div v-else class="kanban-board">
            <div
                v-for="stage in activePipeline.stages"
                :key="stage.id"
                class="kanban-column"
                :class="{ 'drag-over': dragOverStageId === stage.id }"
                @dragover="onDragOver($event, stage.id)"
                @dragleave="onDragLeave(stage.id)"
                @drop="onDrop($event, stage.id)"
            >
                <div class="kanban-column-header" :style="{ borderTopColor: stage.color || '#888' }">
                    <div class="kanban-column-title">{{ stage.name }}</div>
                    <div class="kanban-column-meta">
                        <span class="kanban-column-count">{{ (dealsByStage[stage.id] || []).length }}</span>
                        <span class="kanban-column-total">${{ fmtMoney(stageTotal(stage.id)) }}</span>
                    </div>
                </div>

                <div class="kanban-column-body">
                    <div
                        v-for="deal in (dealsByStage[stage.id] || [])"
                        :key="deal.id"
                        class="kanban-card"
                        :draggable="canEdit"
                        @dragstart="onDragStart(deal)"
                    >
                        <Link :href="route('crm.deals.show', deal.slug)" class="kanban-card-title">
                            {{ deal.name }}
                        </Link>
                        <div v-if="deal.company" class="kanban-card-company">
                            <Link :href="route('crm.companies.show', deal.company.slug ?? deal.company.id)" class="kanban-card-link-secondary">
                                {{ deal.company.name }}
                            </Link>
                        </div>
                        <div class="kanban-card-meta">
                            <span v-if="deal.value" class="kanban-card-value">
                                <DollarOutlined /> {{ deal.currency_code }} {{ fmtMoney(deal.value) }}
                            </span>
                            <span v-if="deal.expected_close_date" class="kanban-card-date">
                                <CalendarOutlined /> {{ deal.expected_close_date }}
                            </span>
                        </div>
                        <div v-if="stage.probability_pct != null" class="kanban-card-prob">
                            <Tag :bordered="false" color="blue">{{ stage.probability_pct }}%</Tag>
                        </div>
                    </div>

                    <Empty
                        v-if="(dealsByStage[stage.id] || []).length === 0"
                        :image-style="{ height: 40 }"
                        description=""
                        class="kanban-column-empty"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<style scoped>
.deals-kanban {
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.pipeline-tabs {
    display: flex;
    gap: 4px;
    padding-bottom: 8px;
    border-bottom: 1px solid var(--color-border, #d9d9d9);
}
.pipeline-tab {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    background: transparent;
    border: 1px solid transparent;
    padding: 6px 12px;
    border-radius: 4px;
    cursor: pointer;
    font-size: 0.875rem;
    color: var(--color-text, #333);
}
.pipeline-tab:hover { background: var(--color-surface-alt, #f0f5ff); }
.pipeline-tab.active {
    background: var(--color-primary-bg, #e6f4ff);
    border-color: var(--color-primary, #0A6ED1);
    color: var(--color-primary, #0A6ED1);
    font-weight: 500;
}
.pipeline-dot { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }

.kanban-board {
    display: flex;
    gap: 12px;
    overflow-x: auto;
    padding-bottom: 12px;
    min-height: 500px;
}

.kanban-column {
    flex: 0 0 280px;
    background: var(--color-surface-alt, #f5f5f5);
    border-radius: 8px;
    padding: 8px;
    transition: background 0.15s;
    display: flex;
    flex-direction: column;
    min-height: 400px;
}
.kanban-column.drag-over {
    background: var(--color-primary-bg, #e6f4ff);
    outline: 2px dashed var(--color-primary, #0A6ED1);
}

.kanban-column-header {
    padding: 8px 4px;
    border-top: 3px solid #888;
    margin-bottom: 8px;
}
.kanban-column-title {
    font-weight: 600;
    font-size: 0.9375rem;
    margin-bottom: 4px;
}
.kanban-column-meta {
    display: flex;
    gap: 8px;
    font-size: 0.75rem;
    color: var(--color-text-muted, #888);
}
.kanban-column-count {
    background: var(--color-surface, #fff);
    padding: 2px 8px;
    border-radius: 10px;
    font-weight: 500;
}
.kanban-column-total {
    color: var(--color-success, #389e0d);
    font-weight: 500;
}

.kanban-column-body {
    display: flex;
    flex-direction: column;
    gap: 8px;
    flex: 1;
}

.kanban-card {
    background: var(--color-surface, #fff);
    border-radius: 6px;
    padding: 10px 12px;
    box-shadow: 0 1px 2px rgba(0, 0, 0, 0.04);
    border: 1px solid var(--color-border-light, #f0f0f0);
    cursor: grab;
    transition: transform 0.1s, box-shadow 0.1s;
}
.kanban-card:hover {
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.08);
}
.kanban-card:active {
    cursor: grabbing;
    transform: scale(0.98);
}

.kanban-card-title {
    font-weight: 500;
    color: var(--color-text, #333);
    display: block;
    margin-bottom: 4px;
    text-decoration: none;
}
.kanban-card-title:hover { color: var(--color-primary, #0A6ED1); }

.kanban-card-company {
    font-size: 0.8125rem;
    margin-bottom: 6px;
}
.kanban-card-link-secondary {
    color: var(--color-text-muted, #666);
    text-decoration: none;
}
.kanban-card-link-secondary:hover { color: var(--color-primary, #0A6ED1); }

.kanban-card-meta {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    font-size: 0.75rem;
    color: var(--color-text-muted, #888);
    margin-top: 6px;
}
.kanban-card-value {
    color: var(--color-success, #389e0d);
    font-weight: 500;
}

.kanban-card-prob {
    margin-top: 6px;
}

.kanban-column-empty {
    margin-top: 24px;
    opacity: 0.5;
}

@media (max-width: 640px) {
    .kanban-column { flex-basis: 260px; }
}
</style>
