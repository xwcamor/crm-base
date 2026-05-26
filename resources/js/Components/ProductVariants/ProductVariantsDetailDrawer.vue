<script setup>
import { computed } from 'vue';
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { AppstoreAddOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

const props = defineProps({
    open:          { type: Boolean, required: true },
    variant:       { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);

const attributePairs = computed(() => {
    if (!props.variant?.attributes || typeof props.variant.attributes !== 'object') return [];
    return Object.entries(props.variant.attributes).map(([k, v]) => ({ key: k, value: v }));
});
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('product_variants.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="variant">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <AppstoreAddOutlined />
                </div>
                <div>
                    <h2>{{ variant.name }}</h2>
                    <Tag :color="variant.is_active ? 'success' : 'error'" :bordered="false">
                        {{ variant.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ variant.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('product_variants.sku')">
                    <code>{{ variant.sku }}</code>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('product_variants.name')">{{ variant.name }}</DescriptionsItem>
                <DescriptionsItem v-if="variant.product" :label="$t('product_variants.product')">
                    {{ variant.product.name }}
                    <span v-if="variant.product.sku" class="muted">({{ variant.product.sku }})</span>
                </DescriptionsItem>
                <DescriptionsItem v-if="variant.barcode" :label="$t('product_variants.barcode')">
                    {{ variant.barcode }}
                </DescriptionsItem>
                <DescriptionsItem v-if="attributePairs.length" :label="$t('product_variants.attributes')">
                    <div class="attr-chips">
                        <Tag v-for="pair in attributePairs" :key="pair.key" color="blue" :bordered="false">
                            <strong>{{ pair.key }}:</strong> {{ pair.value }}
                        </Tag>
                    </div>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('product_variants.price')">{{ variant.price ?? '—' }}</DescriptionsItem>
                <DescriptionsItem :label="$t('product_variants.cost')">{{ variant.cost ?? '—' }}</DescriptionsItem>
                <DescriptionsItem :label="$t('product_variants.sort_order')">{{ variant.sort_order }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(variant.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="variant.creator" :label="$t('global.created_by')">
                    {{ variant.creator.name }}
                    <span class="audit-email">({{ variant.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="variant" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.product_variants.show', variant.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link v-if="canDelete" :href="route('business_management.product_variants.delete', variant.slug)">
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button v-if="canCreate" :block="isMobile" :loading="duplicatingId === variant.id" @click="emit('duplicate', variant)">
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link v-if="canEdit" :href="route('business_management.product_variants.edit', variant.slug)">
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
.muted { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
.attr-chips { display: flex; flex-wrap: wrap; gap: 4px; }
</style>
