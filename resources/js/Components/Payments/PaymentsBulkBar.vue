<script setup>
/** Barra de acciones masivas para Payments (seleccion multiple). Mobile: sticky al pie.
 *  Pagos no tiene `is_active` (el estado es un enum: pending/completed/failed/refunded/disputed),
 *  asi que omitimos activar/desactivar. Solo bulk delete. */
import { Button, Space } from 'ant-design-vue';
import { DeleteOutlined } from '@ant-design/icons-vue';

defineProps({
    count:          { type: Number,  required: true },
    isMobile:       { type: Boolean, default: false },
    canDelete:      { type: Boolean, default: false },
});

defineEmits(['cancel', 'delete']);
</script>

<template>
    <div
        class="bulk-bar"
        :class="{ 'bulk-bar--mobile-sticky': isMobile }"
    >
        <span class="bulk-bar__label">
            <strong>{{ count }}</strong>
            {{ count === 1 ? $t('global.selected') : $t('global.selected_plural') }}
        </span>
        <Space wrap>
            <Button size="small" @click="$emit('cancel')">{{ $t('global.cancel') }}</Button>
            <Button
                v-if="canDelete"
                size="small"
                danger
                type="primary"
                @click="$emit('delete')"
            >
                <DeleteOutlined /> {{ $t('global.delete') }}
            </Button>
        </Space>
    </div>
</template>

<style scoped>
.bulk-bar {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    padding: 10px 14px;
    background: var(--color-surface-selected);
    border-bottom: 1px solid var(--color-border-selected);
    font-size: 0.875rem;
    flex-wrap: wrap;
}
.bulk-bar__label { color: var(--color-text); }
.bulk-bar__label strong { color: var(--color-primary); font-weight: 600; }

/* Mobile: posición fija al pie, oscuro y elevado. Respeta safe-area iOS. */
.bulk-bar--mobile-sticky {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1100;
    border: 0;
    border-top: 1px solid var(--color-border-overlay);
    background: var(--color-surface-overlay);
    color: var(--color-text-on-dark);
    padding: 12px 16px calc(env(safe-area-inset-bottom, 0px) + 12px);
    box-shadow: 0 -8px 24px rgba(0, 0, 0, 0.18);
    flex-wrap: nowrap;
}
.bulk-bar--mobile-sticky .bulk-bar__label { color: var(--color-text-on-dark); }
.bulk-bar--mobile-sticky .bulk-bar__label strong { color: var(--color-primary-accent); font-size: 1rem; }
</style>
