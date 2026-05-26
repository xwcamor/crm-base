<script setup>
/**
 * KPITiles — fila de tarjetas KPI uniformes para los Show de cada modulo.
 *
 * Props:
 *   tiles: array de { icon, label, value, hint?, color?, span? }
 *     - icon:  componente Vue (icono Ant Design)
 *     - label: texto pequeno arriba (uppercase)
 *     - value: valor principal a destacar (string o number ya formateado)
 *     - hint:  texto pequeno debajo del value (opcional)
 *     - color: 'primary'|'success'|'warning'|'danger'|'default' (opcional)
 *     - span:  ancho personalizado md (por defecto reparte equitativo)
 */
import { computed } from 'vue';
import { Card, Row, Col } from 'ant-design-vue';

const props = defineProps({
    tiles: { type: Array, required: true },
});

const defaultSpan = computed(() => {
    if (!props.tiles?.length) return 24;
    const n = props.tiles.length;
    if (n === 1) return 24;
    if (n === 2) return 12;
    if (n === 3) return 8;
    return 6;
});
</script>

<template>
    <Row :gutter="[16, 16]" class="kpi-tiles">
        <Col v-for="(t, i) in tiles"
             :key="i"
             :xs="12"
             :md="t.span ?? defaultSpan"
        >
            <Card :bodyStyle="{ padding: '14px 18px' }" class="kpi-tile" :class="t.color ? `kpi-tile--${t.color}` : ''">
                <div class="kpi-tile__head">
                    <component :is="t.icon" v-if="t.icon" />
                    <span>{{ t.label }}</span>
                </div>
                <div class="kpi-tile__value">{{ t.value }}</div>
                <div v-if="t.hint" class="kpi-tile__hint">{{ t.hint }}</div>
            </Card>
        </Col>
    </Row>
</template>

<style scoped>
.kpi-tiles { margin-bottom: 16px; }
.kpi-tile { border-radius: 6px; height: 100%; }
.kpi-tile__head {
    font-size: 0.72rem;
    color: var(--color-text-muted, #8c8c8c);
    text-transform: uppercase;
    letter-spacing: 0.5px;
    display: flex; align-items: center; gap: 6px;
    margin-bottom: 6px;
}
.kpi-tile__head :deep(svg) { font-size: 0.85rem; }
.kpi-tile__value {
    font-size: 1.35rem;
    font-weight: 700;
    color: var(--color-text-strong, #262626);
    line-height: 1.25;
    word-break: break-word;
}
.kpi-tile__hint {
    margin-top: 4px;
    font-size: 0.78rem;
    color: var(--color-text-muted, #8c8c8c);
}

.kpi-tile--primary .kpi-tile__value { color: #1677ff; }
.kpi-tile--success .kpi-tile__value { color: #389e0d; }
.kpi-tile--warning .kpi-tile__value { color: #d48806; }
.kpi-tile--danger  .kpi-tile__value { color: #cf1322; }
</style>
