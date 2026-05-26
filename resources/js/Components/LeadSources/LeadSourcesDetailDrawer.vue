<script setup>
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { CompassOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    leadSource:    { type: Object,  default: null },
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
        :title="$t('lead_sources.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="leadSource">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <CompassOutlined />
                </div>
                <div>
                    <h2>{{ leadSource.name }}</h2>
                    <Tag :color="leadSource.is_active ? 'success' : 'error'" :bordered="false">
                        {{ leadSource.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ leadSource.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('lead_sources.name')">{{ leadSource.name }}</DescriptionsItem>
                <DescriptionsItem v-if="leadSource.category" :label="$t('lead_sources.category')">
                    {{ leadSource.category }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('lead_sources.sort_order')">{{ leadSource.sort_order }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(leadSource.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="leadSource.creator" :label="$t('global.created_by')">
                    {{ leadSource.creator.name }}
                    <span class="audit-email">({{ leadSource.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="leadSource" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.lead_sources.show', leadSource.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link v-if="canDelete" :href="route('business_management.lead_sources.delete', leadSource.slug)">
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button v-if="canCreate" :block="isMobile" :loading="duplicatingId === leadSource.id" @click="emit('duplicate', leadSource)">
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link v-if="canEdit" :href="route('business_management.lead_sources.edit', leadSource.slug)">
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
