<script setup>
/** Drawer lateral: preview rapido de la tasa sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { SwapOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    rate:          { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('exchange_rates.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="rate">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <SwapOutlined />
                </div>
                <div>
                    <h2>{{ rate.base_code }} / {{ rate.quote_code }}</h2>
                    <Tag :color="rate.is_active ? 'success' : 'error'" :bordered="false">
                        {{ rate.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ rate.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('exchange_rates.base_code')">{{ rate.base_code }}</DescriptionsItem>
                <DescriptionsItem :label="$t('exchange_rates.quote_code')">{{ rate.quote_code }}</DescriptionsItem>
                <DescriptionsItem :label="$t('exchange_rates.rate')">
                    <strong>{{ Number(rate.rate).toFixed(6) }}</strong>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('exchange_rates.valid_at')">
                    {{ formatDateTime(rate.valid_at) }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('exchange_rates.source')">{{ rate.source || '—' }}</DescriptionsItem>
                <DescriptionsItem v-if="rate.creator" :label="$t('global.created_by')">
                    {{ rate.creator.name }}
                    <span class="audit-email">({{ rate.creator.email }})</span>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(rate.created_at) }}
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="rate" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.exchange_rates.show', rate.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link v-if="canDelete" :href="route('business_management.exchange_rates.delete', rate.slug)">
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button v-if="canCreate" :block="isMobile" :loading="duplicatingId === rate.id" @click="emit('duplicate', rate)">
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link v-if="canEdit" :href="route('business_management.exchange_rates.edit', rate.slug)">
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
    font-family: ui-monospace, Consolas, monospace;
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
