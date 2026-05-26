<script setup>
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { CreditCardOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    method:        { type: Object,  default: null },
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
        :title="$t('payment_methods.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="method">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <CreditCardOutlined />
                </div>
                <div>
                    <h2>{{ method.name }}</h2>
                    <Tag :color="method.is_active ? 'success' : 'error'" :bordered="false">
                        {{ method.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ method.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('payment_methods.name')">{{ method.name }}</DescriptionsItem>
                <DescriptionsItem v-if="method.code" :label="$t('payment_methods.code')">{{ method.code }}</DescriptionsItem>
                <DescriptionsItem v-if="method.integration_provider" :label="$t('payment_methods.integration_provider')">
                    {{ method.integration_provider }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('payment_methods.requires_reference')">
                    {{ method.requires_reference ? $t('global.yes') : $t('global.no') }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('payment_methods.sort_order')">{{ method.sort_order }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(method.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="method.creator" :label="$t('global.created_by')">
                    {{ method.creator.name }}
                    <span class="audit-email">({{ method.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="method" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.payment_methods.show', method.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link v-if="canDelete" :href="route('business_management.payment_methods.delete', method.slug)">
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button v-if="canCreate" :block="isMobile" :loading="duplicatingId === method.id" @click="emit('duplicate', method)">
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link v-if="canEdit" :href="route('business_management.payment_methods.edit', method.slug)">
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
