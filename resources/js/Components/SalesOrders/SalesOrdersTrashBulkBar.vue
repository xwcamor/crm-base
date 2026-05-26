<script setup>
import { Button, Space } from 'ant-design-vue';
import { UndoOutlined } from '@ant-design/icons-vue';

defineProps({
    count:      { type: Number,  required: true },
    submitting: { type: Boolean, default: false },
});

defineEmits(['cancel', 'restore']);
</script>

<template>
    <div class="bulk-bar">
        <span class="bulk-bar__label">
            <strong>{{ count }}</strong>
            {{ count === 1 ? $t('global.selected') : $t('global.selected_plural') }}
        </span>
        <Space wrap>
            <Button size="small" @click="$emit('cancel')">{{ $t('global.cancel') }}</Button>
            <Button
                size="small"
                type="primary"
                ghost
                :loading="submitting"
                @click="$emit('restore')"
            >
                <UndoOutlined /> {{ $t('global.bulk_restore') }}
            </Button>
        </Space>
    </div>
</template>

<style scoped>
.bulk-bar {
    display: flex; justify-content: space-between; align-items: center;
    padding: 10px 16px;
    background: var(--color-surface-selected); border-bottom: 1px solid var(--color-border-selected);
    font-size: 0.875rem;
}
.bulk-bar__label { color: var(--color-text); }
.bulk-bar__label strong { color: var(--color-primary); font-weight: 600; }
</style>
