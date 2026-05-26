<script setup>
/** Drawer lateral: preview rapido de la lista de precios sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { TagsOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    priceList:     { type: Object,  default: null },
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
        :title="$t('price_lists.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="priceList">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <TagsOutlined />
                </div>
                <div>
                    <h2>{{ priceList.name }}</h2>
                    <Tag :color="priceList.is_active ? 'success' : 'error'" :bordered="false">
                        {{ priceList.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <Tag v-if="priceList.is_default" color="gold" :bordered="false">
                        {{ $t('price_lists.is_default') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ priceList.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('price_lists.currency')">
                    <code>{{ priceList.currency_code || '—' }}</code>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('price_lists.global_discount_pct')">
                    <strong>{{ Number(priceList.global_discount_pct).toFixed(2) }}%</strong>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('price_lists.priority')">{{ priceList.priority }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(priceList.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="priceList.creator" :label="$t('global.created_by')">
                    {{ priceList.creator.name }}
                    <span class="audit-email">({{ priceList.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="priceList" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.price_lists.show', priceList.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link v-if="canDelete" :href="route('business_management.price_lists.delete', priceList.slug)">
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button v-if="canCreate" :block="isMobile" :loading="duplicatingId === priceList.id" @click="emit('duplicate', priceList)">
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link v-if="canEdit" :href="route('business_management.price_lists.edit', priceList.slug)">
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
