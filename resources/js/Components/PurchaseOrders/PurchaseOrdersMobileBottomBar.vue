<script setup>
/** Bottom bar mobile [Filtros][Crear][Otros]. Oculta si hay selección. */
import { Button } from 'ant-design-vue';
import { FilterOutlined, PlusOutlined, MoreOutlined } from '@ant-design/icons-vue';

defineProps({
    canCreate:        { type: Boolean, default: false },
    hasActiveFilters: { type: Boolean, default: false },
});

defineEmits(['open-filters', 'create', 'open-more']);
</script>

<template>
    <div class="mobile-bottom-bar">
        <Button class="mbb-btn" @click="$emit('open-filters')">
            <FilterOutlined />
            <span>{{ $t('global.filters') }}</span>
            <span v-if="hasActiveFilters" class="mbb-btn__dot" />
        </Button>
        <Button
            v-if="canCreate"
            type="primary"
            class="mbb-btn mbb-btn--primary"
            @click="$emit('create')"
        >
            <PlusOutlined />
            <span>{{ $t('global.create') }}</span>
        </Button>
        <Button class="mbb-btn" @click="$emit('open-more')">
            <MoreOutlined />
            <span>{{ $t('global.more') }}</span>
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
    position: relative;
}
.mbb-btn span:not(.mbb-btn__dot) { font-size: 0.875rem; }
.mbb-btn__dot {
    position: absolute;
    top: 8px;
    right: 12px;
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: var(--color-danger-bright);
}
.mbb-btn--primary :deep(.anticon) { color: var(--color-text-on-dark); }
</style>
