<script setup>
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { CheckSquareOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

const { formatDateTime } = useDateFormat();
const { t } = useI18n();

defineProps({
    open:          { type: Boolean, required: true },
    take:          { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);

const statusColor = (s) => ({
    draft: 'default', in_progress: 'blue', completed: 'green', cancelled: 'red',
}[s] || 'default');
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('stock_takes.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="take">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <CheckSquareOutlined />
                </div>
                <div>
                    <h2>{{ take.reference }}</h2>
                    <Tag :color="statusColor(take.status)" :bordered="false">
                        {{ t('stock_takes.status_options.' + take.status) }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ take.id }}</DescriptionsItem>
                <DescriptionsItem v-if="take.warehouse" :label="$t('stock_takes.warehouse')">
                    {{ take.warehouse.name }} ({{ take.warehouse.code }})
                </DescriptionsItem>
                <DescriptionsItem v-if="take.started_at" :label="$t('stock_takes.started_at')">
                    {{ formatDateTime(take.started_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="take.completed_at" :label="$t('stock_takes.completed_at')">
                    {{ formatDateTime(take.completed_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="take.note" :label="$t('stock_takes.note')">
                    {{ take.note }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(take.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="take.creator" :label="$t('global.created_by')">
                    {{ take.creator.name }}
                    <span class="audit-email">({{ take.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="take" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.stock_takes.show', take.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('business_management.stock_takes.delete', take.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === take.id"
                    @click="emit('duplicate', take)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('business_management.stock_takes.edit', take.slug)"
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
