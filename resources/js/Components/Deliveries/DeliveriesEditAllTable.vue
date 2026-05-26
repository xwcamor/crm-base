<script setup>
/**
 * Tabla editable in-line del flujo Edit-All de Deliveries. Permite editar
 * reference + status + carrier. NO toca lineas/items (esos cambios deben ir
 * via Form.vue normal).
 */
import { Input, Select } from 'ant-design-vue';
import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    isDirty:       { type: Function, required: true },
    duplicateRows: { type: Set,      required: true },
    statusOptions: { type: Array,    default: () => [] },
});

const draft = defineModel('draft', { type: Array, required: true });
const { t } = useI18n();
</script>

<template>
    <table v-if="draft.length > 0" class="edit-table">
        <thead>
            <tr>
                <th class="col-id">ID</th>
                <th class="col-ref">{{ t('deliveries.reference') }}</th>
                <th class="col-status">{{ t('deliveries.status') }}</th>
                <th class="col-carrier">{{ t('deliveries.carrier') }}</th>
            </tr>
        </thead>
        <tbody>
            <tr
                v-for="(row, i) in draft"
                :key="row.id"
                :class="{
                    'is-dirty':     props.isDirty(i),
                    'is-duplicate': duplicateRows.has(i),
                }"
            >
                <td class="col-id">{{ row.id }}</td>
                <td class="col-ref">
                    <Input
                        v-model:value="row.reference"
                        :status="duplicateRows.has(i) ? 'error' : (props.isDirty(i) ? 'warning' : '')"
                        size="small"
                        :maxlength="30"
                    />
                </td>
                <td class="col-status">
                    <Select
                        v-model:value="row.status"
                        :options="statusOptions"
                        size="small"
                        style="width: 100%"
                    />
                </td>
                <td class="col-carrier">
                    <Input
                        v-model:value="row.carrier"
                        size="small"
                        :maxlength="100"
                    />
                </td>
            </tr>
        </tbody>
    </table>

    <div v-else class="empty">
        {{ t('deliveries.edit_all_no_results') }}
    </div>
</template>

<style scoped>
.edit-table { width: 100%; border-collapse: collapse; font-size: 0.875rem; }
.edit-table thead th {
    background: var(--color-surface-alt); color: var(--color-text-strong);
    font-weight: 600; font-size: 0.78rem; text-transform: uppercase;
    letter-spacing: 0.04em; text-align: left; padding: 12px 14px;
    border-bottom: 1px solid var(--color-border);
}
.edit-table tbody td {
    padding: 8px 14px; border-bottom: 1px solid var(--color-border-soft);
    vertical-align: middle;
}
.edit-table tbody tr:last-child td { border-bottom: 0; }
.edit-table .col-id      { width: 80px;  color: var(--color-text-muted); }
.edit-table .col-ref     { width: 220px; }
.edit-table .col-status  { width: 180px; }
.edit-table .col-carrier { width: 180px; }
.edit-table tbody tr.is-dirty     { background: var(--tint-dirty); }
.edit-table tbody tr.is-duplicate { background: var(--tint-duplicate); }
.empty { padding: 48px 16px; text-align: center; color: var(--color-text-muted); font-size: 0.9rem; }
@media (max-width: 768px) {
    .edit-table .col-id { display: none; }
    .edit-table thead th:first-child,
    .edit-table tbody td:first-child { display: none; }
}
</style>
