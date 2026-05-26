<script setup>
/** Drawer lateral: preview rapido del cliente sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { UserOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const fmtMoney = (n) => {
    if (n == null) return '0';
    const v = Number(n);
    if (!Number.isFinite(v)) return '0';
    return v.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    product:      { type: Object,  default: null },
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
        :title="$t('products.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="product">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <UserOutlined />
                </div>
                <div>
                    <h2>{{ product.name }}</h2>
                    <Tag :color="product.is_active ? 'success' : 'error'" :bordered="false">
                        {{ product.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4" size="small">
                <DescriptionsItem v-if="product.sku" :label="$t('products.sku')">
                    <code class="muted">{{ product.sku }}</code>
                </DescriptionsItem>
                <DescriptionsItem v-if="product.brand" :label="$t('products.brand')">
                    {{ product.brand }}
                </DescriptionsItem>
                <DescriptionsItem v-if="product.type" :label="$t('products.type')">
                    <Tag :bordered="false">{{ $t('products.type_options.' + product.type) }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem v-if="product.category" :label="$t('products.category')">
                    {{ product.category.name }}
                </DescriptionsItem>
                <DescriptionsItem v-if="product.list_price != null" :label="$t('products.list_price')">
                    <strong>{{ product.currency_code }} {{ fmtMoney(product.list_price) }}</strong>
                </DescriptionsItem>
                <DescriptionsItem v-if="product.cost != null" :label="$t('products.cost')">
                    {{ product.currency_code }} {{ fmtMoney(product.cost) }}
                </DescriptionsItem>
                <DescriptionsItem label="ID">{{ product.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(product.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="product.creator" :label="$t('global.created_by')">
                    {{ product.creator.name }}
                    <span class="audit-email">({{ product.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="product" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.products.show', product.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('business_management.products.delete', product.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === product.id"
                    @click="emit('duplicate', product)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('business_management.products.edit', product.slug)"
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
