<script setup>
/**
 * Tabla editable in-line del flujo Edit-All de SalesOrders. Permite editar
 * reference + status + payment_status. NO toca lineas (esos cambios deben ir
 * via Form.vue normal).
 */
import { Input, Select } from 'ant-design-vue';
import { useI18n } from '@/Plugins/i18n';

const props = defineProps({
    isDirty:              { type: Function, required: true },
    duplicateRows:        { type: Set,      required: true },
    statusOptions:        { type: Array,    default: () => [] },
    paymentStatusOptions: { type: Array,    default: () => [] },
});

const draft = defineModel('draft', { type: Array, required: true });
const { t } = useI18n();
</script>

<template>
    <table v-if="draft.length > 0" class="edit-table">
        <thead>
            <tr>
                <th class="col-id">ID</th>
                <th class="col-ref">{{ t('sales_orders.reference') }}</th>
                <th class="col-status">{{ t('sales_orders.status') }}</th>
                <th class="col-pstatus">{{ t('sales_orders.payment_status') }}</th>
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
                <td class="col-pstatus">
                    <Select
                        v-model:value="row.payment_status"
                        :options="paymentStatusOptions"
                        size="small"
                        style="width: 100%"
                    />
                </td>
            </tr>
        </tbody>
    </table>

    <div v-else class="empty">
        {{ t('sales_orders.edit_all_no_results') }}
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
.edit-table .col-ref     { width: 200px; }
.edit-table .col-status  { width: 180px; }
.edit-table .col-pstatus { width: 180px; }
.edit-table tbody tr.is-dirty     { background: var(--tint-dirty); }
.edit-table tbody tr.is-duplicate { background: var(--tint-duplicate); }
.empty { padding: 48px 16px; text-align: center; color: var(--color-text-muted); font-size: 0.9rem; }
@media (max-width: 768px) {
    .edit-table .col-id { display: none; }
    .edit-table thead th:first-child,
    .edit-table tbody td:first-child { display: none; }
}
</style>
