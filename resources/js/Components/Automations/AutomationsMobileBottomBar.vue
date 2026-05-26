<script setup>
/**
 * Bottom bar mobile de Automations. Más simple que el de Regions: el módulo
 * no tiene filtros ni export/import, solo Crear (+ Ver eliminados para
 * super). Mismo patrón visual app-like.
 */
import { Button } from 'ant-design-vue';
import { PlusOutlined, InboxOutlined } from '@ant-design/icons-vue';

defineProps({
    isSuper: { type: Boolean, default: false },
});

defineEmits(['create', 'go-trash']);
</script>

<template>
    <div class="mobile-bottom-bar">
        <Button
            v-if="isSuper"
            class="mbb-btn"
            @click="$emit('go-trash')"
        >
            <InboxOutlined />
            <span>{{ $t('global.view_deleted') }}</span>
        </Button>
        <Button
            type="primary"
            class="mbb-btn mbb-btn--primary"
            @click="$emit('create')"
        >
            <PlusOutlined />
            <span>{{ $t('global.create') }}</span>
        </Button>
    </div>
</template>

<style scoped>
.mobile-bottom-bar {
    position: fixed;
    bottom: 0;
    left: 0;
    right: 0;
    z-index: 1000;
    display: flex;
    gap: 8px;
    padding: 10px 12px calc(env(safe-area-inset-bottom, 0px) + 10px);
    background: var(--color-surface);
    border-top: 1px solid var(--color-border-strong);
    box-shadow: 0 -4px 16px rgba(15, 23, 42, 0.06);
}
.mbb-btn {
    flex: 1;
    height: 44px !important;
    display: inline-flex !important;
    align-items: center;
    justify-content: center;
    gap: 6px;
    font-weight: 500;
}
.mbb-btn span { font-size: 0.875rem; }
.mbb-btn--primary { flex: 2; }
.mbb-btn--primary :deep(.anticon) { color: var(--color-text-on-dark); }
</style>
