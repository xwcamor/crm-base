<script setup>
/**
 * DocumentFlow — barra horizontal del flujo de estados de un documento.
 *
 * A diferencia del checklist vertical del Deal (que muestra etapas de un
 * pipeline CRM), este componente sirve para documentos con flujo lineal:
 * Quote, SalesOrder, Invoice, PurchaseOrder, Delivery.
 *
 * Props:
 *   currentStatus: string  - estado actual del documento
 *   steps: array de { value, label, isTerminal?, isError?, hint? }
 *     - los steps se renderizan en orden
 *     - el current se destaca
 *     - los anteriores quedan en done (gris/check)
 *     - los siguientes en pending (muted)
 *   title?: opcional titulo de la card
 */
import { computed } from 'vue';
import { Card, Tag } from 'ant-design-vue';
import {
    CheckCircleFilled, ClockCircleFilled, MinusCircleOutlined, StopOutlined,
} from '@ant-design/icons-vue';

const props = defineProps({
    currentStatus: { type: String, required: true },
    steps:         { type: Array,  required: true },
    title:         { type: String, default: '' },
});

const currentIndex = computed(() => {
    return props.steps.findIndex(s => s.value === props.currentStatus);
});

const isTerminalError = computed(() => {
    const cur = props.steps[currentIndex.value];
    return cur?.isError === true;
});

const isTerminalSuccess = computed(() => {
    const cur = props.steps[currentIndex.value];
    return cur?.isTerminal === true && !cur?.isError;
});

const progressPct = computed(() => {
    if (currentIndex.value < 0 || props.steps.length === 0) return 0;
    if (isTerminalError.value) return 100;
    const totalFlow = props.steps.filter(s => !s.isError).length;
    const includeCurrent = isTerminalSuccess.value ? 1 : 0;
    const baseIdx = props.steps.slice(0, currentIndex.value).filter(s => !s.isError).length;
    return Math.min(100, Math.round(((baseIdx + includeCurrent) / totalFlow) * 100));
});

const stepState = (step, idx) => {
    if (idx === currentIndex.value) {
        if (step.isError)    return 'error';
        if (step.isTerminal) return 'success';
        return 'current';
    }
    if (idx < currentIndex.value) return 'done';
    return 'future';
};
</script>

<template>
    <Card class="doc-flow" :bodyStyle="{ padding: 0 }">
        <div class="doc-flow__header">
            <h3 v-if="title" class="doc-flow__title">{{ title }}</h3>
            <div class="doc-flow__progress">
                <span class="doc-flow__progress-label">{{ progressPct }}%</span>
                <div class="doc-flow__bar">
                    <div class="doc-flow__bar-fill"
                         :class="{
                             'doc-flow__bar-fill--success': isTerminalSuccess,
                             'doc-flow__bar-fill--error':   isTerminalError,
                         }"
                         :style="{ width: progressPct + '%' }"></div>
                </div>
            </div>
        </div>

        <ol class="doc-flow__steps">
            <li v-for="(s, i) in steps" :key="s.value" class="doc-flow__step" :class="`doc-flow__step--${stepState(s, i)}`">
                <div class="doc-flow__bullet">
                    <CheckCircleFilled v-if="stepState(s, i) === 'done' || stepState(s, i) === 'success'" />
                    <StopOutlined v-else-if="stepState(s, i) === 'error'" />
                    <ClockCircleFilled v-else-if="stepState(s, i) === 'current'" />
                    <MinusCircleOutlined v-else />
                </div>
                <div class="doc-flow__step-body">
                    <div class="doc-flow__step-label">{{ s.label }}</div>
                    <div v-if="s.hint" class="doc-flow__step-hint">{{ s.hint }}</div>
                </div>
            </li>
        </ol>
    </Card>
</template>

<style scoped>
.doc-flow { margin-bottom: 16px; border-radius: 6px; }
.doc-flow__header {
    display: flex; justify-content: space-between; align-items: center;
    padding: 14px 20px;
    gap: 16px;
    border-bottom: 1px solid var(--color-border-soft, #f0f0f0);
}
.doc-flow__title { margin: 0; font-size: 1rem; font-weight: 600; }
.doc-flow__progress { display: flex; align-items: center; gap: 12px; min-width: 200px; flex: 1; max-width: 320px; }
.doc-flow__progress-label {
    font-weight: 700; font-size: 0.9rem; color: var(--color-text-strong);
    min-width: 36px; text-align: right;
}
.doc-flow__bar {
    flex: 1; height: 6px;
    background: var(--color-border-soft, #f0f0f0);
    border-radius: 3px; overflow: hidden;
}
.doc-flow__bar-fill {
    height: 100%; background: var(--color-primary, #1677ff);
    transition: width 0.3s ease;
}
.doc-flow__bar-fill--success { background: #52c41a; }
.doc-flow__bar-fill--error   { background: #ff4d4f; }

.doc-flow__steps {
    list-style: none; margin: 0; padding: 0;
    display: flex;
    flex-wrap: wrap;
    gap: 0;
}
.doc-flow__step {
    flex: 1 1 0;
    min-width: 130px;
    display: flex; align-items: center; gap: 10px;
    padding: 14px 16px;
    border-right: 1px solid var(--color-border-soft, #f0f0f0);
    position: relative;
}
.doc-flow__step:last-child { border-right: 0; }
.doc-flow__bullet {
    font-size: 1.15rem; line-height: 1;
    flex-shrink: 0;
}
.doc-flow__step-body { flex: 1; min-width: 0; }
.doc-flow__step-label { font-size: 0.85rem; font-weight: 500; color: var(--color-text-strong); }
.doc-flow__step-hint { font-size: 0.72rem; color: var(--color-text-muted, #8c8c8c); margin-top: 2px; }

.doc-flow__step--done .doc-flow__bullet :deep(svg) { color: #b8b8b8; }
.doc-flow__step--done .doc-flow__step-label { color: var(--color-text-muted); }

.doc-flow__step--current {
    background: rgba(22, 119, 255, 0.06);
}
.doc-flow__step--current .doc-flow__bullet :deep(svg) { color: #1677ff; }
.doc-flow__step--current .doc-flow__step-label { color: #1677ff; font-weight: 600; }

.doc-flow__step--success {
    background: rgba(82, 196, 26, 0.08);
}
.doc-flow__step--success .doc-flow__bullet :deep(svg) { color: #52c41a; }
.doc-flow__step--success .doc-flow__step-label { color: #389e0d; font-weight: 600; }

.doc-flow__step--error {
    background: rgba(255, 77, 79, 0.06);
}
.doc-flow__step--error .doc-flow__bullet :deep(svg) { color: #ff4d4f; }
.doc-flow__step--error .doc-flow__step-label { color: #cf1322; font-weight: 600; }

.doc-flow__step--future .doc-flow__bullet :deep(svg) { color: #d9d9d9; }
.doc-flow__step--future .doc-flow__step-label { color: var(--color-text-muted); }

@media (max-width: 767px) {
    .doc-flow__header { flex-direction: column; align-items: flex-start; }
    .doc-flow__progress { width: 100%; max-width: none; }
    .doc-flow__step { min-width: 100%; border-right: 0; border-bottom: 1px solid var(--color-border-soft, #f0f0f0); }
    .doc-flow__step:last-child { border-bottom: 0; }
}
</style>
