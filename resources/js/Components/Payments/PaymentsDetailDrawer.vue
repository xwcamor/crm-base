<script setup>
/** Drawer lateral: preview rapido del pago sin salir del listado. */
import { computed } from 'vue';
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import {
    CreditCardOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined,
} from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();
const { formatDateTime } = useDateFormat();

const props = defineProps({
    open:          { type: Boolean, required: true },
    payment:       { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);

// Color por estado del pago (enum: pending/completed/failed/refunded/disputed).
const statusColor = {
    pending:   'gold',
    completed: 'green',
    failed:    'red',
    refunded:  'purple',
    disputed:  'orange',
};

const statusLabel = computed(() => {
    if (!props.payment?.status) return '';
    return t('payments.status_options.' + props.payment.status);
});

const typeLabel = computed(() => {
    if (!props.payment?.type) return '';
    return t('payments.type_options.' + props.payment.type);
});

const formattedAmount = computed(() => {
    if (!props.payment || props.payment.amount == null) return '—';
    const v = Number(props.payment.amount);
    if (Number.isNaN(v)) return '—';
    const code = props.payment.currency_code || '';
    return `${code} ${new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v)}`;
});
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('payments.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="payment">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <CreditCardOutlined />
                </div>
                <div>
                    <h2>{{ payment.reference || '—' }}</h2>
                    <Tag :color="statusColor[payment.status] || 'default'" :bordered="false">
                        {{ statusLabel }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem v-if="payment.id" label="ID">{{ payment.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('payments.amount')">
                    <strong>{{ formattedAmount }}</strong>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('payments.type')">{{ typeLabel }}</DescriptionsItem>
                <DescriptionsItem v-if="payment.paid_at" :label="$t('payments.paid_at')">
                    {{ formatDateTime(payment.paid_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="payment.company" :label="$t('payments.company')">
                    {{ payment.company.name }}
                </DescriptionsItem>
                <DescriptionsItem v-if="payment.invoice" :label="$t('payments.invoice')">
                    {{ payment.invoice.number }}
                </DescriptionsItem>
                <DescriptionsItem v-if="payment.paymentMethod || payment.payment_method" :label="$t('payments.payment_method')">
                    {{ (payment.paymentMethod || payment.payment_method)?.name }}
                </DescriptionsItem>
                <DescriptionsItem v-if="payment.bank_reference" :label="$t('payments.bank_reference')">
                    {{ payment.bank_reference }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(payment.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="payment.creator" :label="$t('global.created_by')">
                    {{ payment.creator.name }}
                    <span class="audit-email">({{ payment.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="payment" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.payments.show', payment.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('business_management.payments.delete', payment.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === payment.id"
                    @click="emit('duplicate', payment)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('business_management.payments.edit', payment.slug)"
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
.drawer-hero h2 {
    font-size: 1.15rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: var(--color-text);
}
.mt-4 { margin-top: 16px; }
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
.audit-email { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
</style>
