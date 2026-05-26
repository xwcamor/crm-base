<script setup>
/**
 * Bottom bar mobile [Filtros][Columnas]. Patron app-real (Yape/Glovo),
 * coherente con el resto de módulos (Customers, Regions, etc.).
 *
 * AuditLogs NO tiene acción de "Crear" — los logs los genera el sistema, no
 * los humanos. Por eso solo hay 2 botones (no 3 como Customers).
 */
import { Button } from 'ant-design-vue';
import { FilterOutlined, TableOutlined } from '@ant-design/icons-vue';

defineProps({
    hasActiveFilters: { type: Boolean, default: false },
});

defineEmits(['open-filters', 'open-columns']);
</script>

<template>
    <div class="mobile-bottom-bar">
        <Button class="mbb-btn" @click="$emit('open-filters')">
            <FilterOutlined />
            <span>{{ $t('global.filters') }}</span>
            <span v-if="hasActiveFilters" class="mbb-btn__dot" />
        </Button>
        <Button class="mbb-btn" @click="$emit('open-columns')">
            <TableOutlined />
            <span>{{ $t('global.columns') }}</span>
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
</style>
