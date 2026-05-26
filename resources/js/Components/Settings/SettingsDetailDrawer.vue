<script setup>
/** Drawer lateral: preview de región sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { GlobalOutlined, DeleteOutlined, CopyOutlined, EditOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

const props = defineProps({
    open:        { type: Boolean, required: true },
    setting:      { type: Object,  default: null },
    width:       { type: [Number, String], default: 480 },
    isMobile:    { type: Boolean, default: false },
    canCreate:   { type: Boolean, default: false },
    canEdit:     { type: Boolean, default: false },
    canDelete:   { type: Boolean, default: false },
    duplicatingId: { type: [Number, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('settings.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="setting">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <GlobalOutlined />
                </div>
                <div>
                    <h2>{{ setting.name }}</h2>
                    <Tag :color="setting.is_active ? 'success' : 'error'" :bordered="false">
                        {{ setting.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ setting.id }}</DescriptionsItem>
                <DescriptionsItem label="Slug">
                    <code>{{ setting.slug }}</code>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(setting.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="setting.creator" :label="$t('global.created_by')">
                    {{ setting.creator.name }}
                    <span class="audit-email">({{ setting.creator.email }})</span>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.updated_at')">
                    {{ formatDateTime(setting.updated_at) }}
                </DescriptionsItem>
            </Descriptions>
        </template>

        <!-- Sticky footer with actions — desktop: right-aligned horizontal,
             mobile: stacked full-width (touch-friendly). -->
        <template #footer>
            <div v-if="setting" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link
                    v-if="canDelete"
                    :href="route('system_management.settings.delete', setting.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === setting.id"
                    @click="emit('duplicate', setting)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('system_management.settings.edit', setting.slug)"
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
.drawer-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}
.drawer-footer--mobile {
    flex-direction: column-reverse;  /* Edit arriba, Delete abajo (primary visible al pulgar) */
    gap: 10px;
    align-items: stretch;
}
.audit-email { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
</style>
