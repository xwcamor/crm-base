<script setup>
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { ShoppingCartOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

const { formatDate, formatDateTime } = useDateFormat();
const { t } = useI18n();

defineProps({
    open:          { type: Boolean, required: true },
    order:         { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);

const statusColor = (s) => ({
    pending: 'default', processing: 'blue', partially_shipped: 'cyan',
    shipped: 'green', delivered: 'green', cancelled: 'red', closed: 'default',
}[s] || 'default');

const fmtAmount = (n, code) => {
    if (n == null) return '—';
    const v = Number(n);
    if (Number.isNaN(v)) return '—';
    return (code || '') + ' ' + new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(v);
};
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('sales_orders.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="order">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <ShoppingCartOutlined />
                </div>
                <div>
                    <h2>{{ order.reference }}</h2>
                    <Tag :color="statusColor(order.status)" :bordered="false">
                        {{ t('sales_orders.status_options.' + order.status) }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ order.id }}</DescriptionsItem>
                <DescriptionsItem v-if="order.company" :label="$t('sales_orders.company')">
                    {{ order.company.name }}
                </DescriptionsItem>
                <DescriptionsItem v-if="order.warehouse" :label="$t('sales_orders.warehouse')">
                    {{ order.warehouse.name }} ({{ order.warehouse.code }})
                </DescriptionsItem>
                <DescriptionsItem :label="$t('sales_orders.payment_status')">
                    {{ t('sales_orders.payment_status_options.' + order.payment_status) }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('sales_orders.order_date')">
                    {{ formatDate(order.order_date) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="order.expected_delivery_date" :label="$t('sales_orders.expected_delivery_date')">
                    {{ formatDate(order.expected_delivery_date) }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('sales_orders.grand_total')">
                    <strong>{{ fmtAmount(order.grand_total, order.currency_code) }}</strong>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(order.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="order.creator" :label="$t('global.created_by')">
                    {{ order.creator.name }}
                    <span class="audit-email">({{ order.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="order" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.sales_orders.show', order.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('business_management.sales_orders.delete', order.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === order.id"
                    @click="emit('duplicate', order)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('business_management.sales_orders.edit', order.slug)"
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
    width: 48px; height: 48px; border-radius: 4px;
    background: var(--color-primary); color: var(--color-text-on-dark);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem; flex-shrink: 0;
}
.drawer-hero h2 { font-size: 1.15rem; font-weight: 600; margin: 0 0 6px 0; color: var(--color-text); }
.mt-4 { margin-top: 16px; }
.drawer-footer { display: flex; justify-content: flex-end; align-items: center; gap: 8px; }
.drawer-footer--mobile { flex-direction: column-reverse; gap: 10px; align-items: stretch; }
.audit-email { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
</style>
