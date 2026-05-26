<script setup>
/**
 * Drawer lateral: preview rapido de la factura sin salir del listado.
 * Resalta `balance_due` — la metrica clave para tracking de AR.
 */
import { computed } from 'vue';
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { FileTextOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

const { formatDateTime } = useDateFormat();
const { t } = useI18n();

const props = defineProps({
    open:          { type: Boolean, required: true },
    invoice:       { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);

const statusColor = (s) => ({
    draft: 'default', sent: 'blue', partial: 'orange', paid: 'green',
    overdue: 'red', cancelled: 'default', refunded: 'purple',
}[s] || 'default');

const fmtAmount = (n, code) => {
    if (n == null) return '—';
    const v = Number(n);
    if (Number.isNaN(v)) return '—';
    return (code || '') + ' ' + new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
};

const balanceClass = computed(() => {
    if (!props.invoice) return '';
    return Number(props.invoice.balance_due) > 0 ? 'amount amount--danger' : 'amount amount--success';
});
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('invoices.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="invoice">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <FileTextOutlined />
                </div>
                <div class="drawer-hero__heading">
                    <h2 class="mono">{{ invoice.number }}</h2>
                    <Tag :color="statusColor(invoice.status)" :bordered="false">
                        {{ $t('invoices.status_options.' + invoice.status) }}
                    </Tag>
                </div>
            </div>

            <!-- Balance due destacado: metrica clave para AR. -->
            <div class="balance-card" :class="balanceClass">
                <div class="balance-card__label">{{ $t('invoices.balance_due') }}</div>
                <div class="balance-card__value">
                    {{ fmtAmount(invoice.balance_due, invoice.currency_code) }}
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4" size="small">
                <DescriptionsItem :label="$t('invoices.company')">
                    <span v-if="invoice.company">{{ invoice.company.name }}</span>
                    <span v-else class="muted">—</span>
                </DescriptionsItem>
                <DescriptionsItem v-if="invoice.contact" :label="$t('invoices.contact')">
                    {{ invoice.contact.name }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('invoices.issue_date')">
                    {{ invoice.issue_date ?? '—' }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('invoices.due_date')">
                    {{ invoice.due_date ?? '—' }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('invoices.grand_total')">
                    <strong>{{ fmtAmount(invoice.grand_total, invoice.currency_code) }}</strong>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('invoices.amount_paid')">
                    {{ fmtAmount(invoice.amount_paid, invoice.currency_code) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="invoice.reference" :label="$t('invoices.reference')">
                    <span class="mono">{{ invoice.reference }}</span>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(invoice.created_at) }}
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="invoice" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.invoices.show', invoice.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('business_management.invoices.delete', invoice.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === invoice.id"
                    @click="emit('duplicate', invoice)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('business_management.invoices.edit', invoice.slug)"
                >
                    <Button :block="isMobile" type="primary">
                        <EditOutlined /> {{ $t('global.edit') }}
                    </Button>
                </Link>
            </div>
        </template>
    </Drawer>
</template>

<style scoped>
.drawer-hero { display: flex; align-items: center; gap: 14px; padding: 8px 0; }
.drawer-hero__icon {
    width: 48px;
    height: 48px;
    border-radius: 4px;
    background: var(--color-primary);
    color: var(--color-text-on-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.drawer-hero__heading h2 {
    font-size: 1.15rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: var(--color-text);
}
.mono { font-family: ui-monospace, Consolas, monospace; }
.muted { color: var(--color-text-muted); }
.mt-4 { margin-top: 16px; }

.balance-card {
    margin-top: 14px;
    padding: 14px 16px;
    border-radius: 6px;
    border: 1px solid var(--color-border, #e1e3e5);
    background: var(--color-surface-alt, #fafafa);
    display: flex;
    flex-direction: column;
    gap: 4px;
}
.balance-card__label {
    font-size: 0.75rem;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    color: var(--color-text-muted);
}
.balance-card__value { font-size: 1.4rem; font-weight: 600; }
.amount--danger  .balance-card__value { color: #d4380d; }
.amount--success .balance-card__value { color: #389e0d; }

.drawer-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}
.drawer-footer--mobile {
    flex-direction: column-reverse;
    gap: 10px;
    align-items: stretch;
}
</style>
