<script setup>
/**
 * Bulk bar de Invoices. A diferencia de Customer (activate/deactivate),
 * Invoices expone bulk update de status (enum: draft/sent/paid/...).
 * El padre muestra un Select para elegir el status objetivo y emite
 * `set-status` con el valor seleccionado.
 */
import { ref } from 'vue';
import { Button, Space, Select } from 'ant-design-vue';
import { CheckCircleOutlined, DeleteOutlined } from '@ant-design/icons-vue';

defineProps({
    count:          { type: Number,  required: true },
    isMobile:       { type: Boolean, default: false },
    bulkActivating: { type: Boolean, default: false },
    canEdit:        { type: Boolean, default: false },
    canDelete:      { type: Boolean, default: false },
    statusOptions:  { type: Array,   default: () => [] },
});

const emit = defineEmits(['cancel', 'set-status', 'delete']);
const targetStatus = ref('sent');
const applyStatus  = () => emit('set-status', targetStatus.value);
</script>

<template>
    <div class="bulk-bar" :class="{ 'bulk-bar--mobile-sticky': isMobile }">
        <span class="bulk-bar__label">
            <strong>{{ count }}</strong>
            {{ count === 1 ? $t('global.selected') : $t('global.selected_plural') }}
        </span>
        <Space wrap>
            <Button size="small" @click="$emit('cancel')">{{ $t('global.cancel') }}</Button>
            <template v-if="canEdit && statusOptions.length">
                <Select
                    v-model:value="targetStatus"
                    :options="statusOptions"
                    size="small"
                    style="width: 180px"
                />
                <Button size="small" :loading="bulkActivating" @click="applyStatus">
                    <CheckCircleOutlined /> {{ $t('invoices.bulk_set_status') }}
                </Button>
            </template>
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

/* Mobile: posicion fija al pie, oscuro y elevado. Respeta safe-area iOS. */
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
