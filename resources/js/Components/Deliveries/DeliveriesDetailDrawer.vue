<script setup>
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { CarOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

const { formatDateTime } = useDateFormat();
const { t } = useI18n();

defineProps({
    open:          { type: Boolean, required: true },
    delivery:      { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);

const statusColor = (s) => ({
    pending: 'default', picking: 'cyan', packed: 'blue', shipped: 'geekblue', delivered: 'green', returned: 'red',
}[s] || 'default');
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('deliveries.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="delivery">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <CarOutlined />
                </div>
                <div>
                    <h2>{{ delivery.reference }}</h2>
                    <Tag :color="statusColor(delivery.status)" :bordered="false">
                        {{ t('deliveries.status_options.' + delivery.status) }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ delivery.id }}</DescriptionsItem>
                <DescriptionsItem v-if="delivery.salesOrder || delivery.sales_order" :label="$t('deliveries.sales_order')">
                    {{ (delivery.salesOrder || delivery.sales_order)?.reference }}
                </DescriptionsItem>
                <DescriptionsItem v-if="delivery.warehouse" :label="$t('deliveries.warehouse')">
                    {{ delivery.warehouse.name }} ({{ delivery.warehouse.code }})
                </DescriptionsItem>
                <DescriptionsItem v-if="delivery.carrier" :label="$t('deliveries.carrier')">
                    {{ delivery.carrier }}
                </DescriptionsItem>
                <DescriptionsItem v-if="delivery.tracking_number" :label="$t('deliveries.tracking_number')">
                    <code class="mono">{{ delivery.tracking_number }}</code>
                </DescriptionsItem>
                <DescriptionsItem v-if="delivery.shipped_at" :label="$t('deliveries.shipped_at')">
                    {{ formatDateTime(delivery.shipped_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="delivery.delivered_at" :label="$t('deliveries.delivered_at')">
                    {{ formatDateTime(delivery.delivered_at) }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(delivery.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="delivery.creator" :label="$t('global.created_by')">
                    {{ delivery.creator.name }}
                    <span class="audit-email">({{ delivery.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="delivery" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.deliveries.show', delivery.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('business_management.deliveries.delete', delivery.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === delivery.id"
                    @click="emit('duplicate', delivery)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('business_management.deliveries.edit', delivery.slug)"
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
.mono { font-family: ui-monospace, Consolas, monospace; font-size: 0.8125rem; }
</style>
