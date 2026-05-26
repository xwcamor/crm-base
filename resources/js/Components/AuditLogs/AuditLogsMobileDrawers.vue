<script setup>
/**
 * Drawer mobile de filtros para AuditLogs. Replica los controles del card
 * `filters-card` desktop pero apilados verticalmente para pantallas chicas.
 *
 * El drawer de Columnas no vive aca — el ColumnSelector ya tiene su propio
 * trigger Button + Drawer interno (compartido con desktop). El padre lo
 * monta como sibling y este componente solo cubre filtros.
 */
import { Drawer, Button, Select, SelectOption, Input, DatePicker } from 'ant-design-vue';
import { ReloadOutlined } from '@ant-design/icons-vue';

defineProps({
    filtersOpen: { type: Boolean, required: true },
    modules:     { type: Array,   required: true },
    events:      { type: Array,   required: true },
    eventLabel:  { type: Function, required: true },
});

const emit = defineEmits([
    'update:filtersOpen',
    'clear-all',
]);

const filters = defineModel('filters', { type: Object, required: true });
</script>

<template>
    <Drawer
        :open="filtersOpen"
        :title="$t('global.filters')"
        placement="bottom"
        height="auto"
        :body-style="{ paddingBottom: '24px' }"
        @update:open="emit('update:filtersOpen', $event)"
    >
        <div class="mobile-filters">
            <Select
                v-model:value="filters.module"
                :placeholder="$t('audit_logs.filter_module')"
                allow-clear
                size="large"
                style="width: 100%"
            >
                <SelectOption v-for="m in modules" :key="m" :value="m">{{ m }}</SelectOption>
            </Select>

            <Select
                v-model:value="filters.event"
                :placeholder="$t('audit_logs.filter_event')"
                allow-clear
                size="large"
                style="width: 100%"
            >
                <SelectOption v-for="e in events" :key="e" :value="e">
                    {{ eventLabel(e) }}
                </SelectOption>
            </Select>

            <Input
                v-model:value="filters.user_id"
                :placeholder="$t('audit_logs.filter_user_id')"
                allow-clear
                size="large"
                type="number"
            />

            <Input
                v-model:value="filters.auditable_id"
                :placeholder="$t('audit_logs.filter_record_id')"
                allow-clear
                size="large"
                type="number"
            />

            <DatePicker.RangePicker
                v-model:value="filters.date_range"
                size="large"
                style="width: 100%"
                :placeholder="[$t('audit_logs.filter_from'), $t('audit_logs.filter_to')]"
            />

            <Button block size="large" type="text" danger @click="emit('clear-all')">
                <ReloadOutlined /> {{ $t('audit_logs.clear_filters') }}
            </Button>
        </div>
    </Drawer>
</template>

<style scoped>
.mobile-filters {
    display: flex;
    flex-direction: column;
    gap: 12px;
}
</style>
