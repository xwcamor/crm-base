<script setup>
/** Drawer lateral: preview rapido del mensaje sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { MessageOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    message:       { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);

const audienceLabel = (m) => {
    if (!m) return '-';
    if (m.audience_type === 'global') return 'Global';
    if (m.audience_type === 'tenant') return 'Workspace';
    if (m.audience_type === 'user')   return 'Usuario';
    return m.audience_type;
};
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('messages.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="message">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <MessageOutlined />
                </div>
                <div>
                    <h2>{{ message.subject }}</h2>
                    <Tag :color="message.is_active ? 'success' : 'error'" :bordered="false">
                        {{ message.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ message.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('messages.audience_type')">{{ audienceLabel(message) }}</DescriptionsItem>
                <DescriptionsItem :label="$t('messages.published_at')">
                    {{ message.published_at ? formatDateTime(message.published_at) : $t('messages.status_draft') }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('messages.expires_at')">
                    {{ message.expires_at ? formatDateTime(message.expires_at) : $t('messages.no_expiration') }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('messages.allow_replies')">
                    {{ message.allow_replies ? '✓' : '—' }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(message.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="message.creator" :label="$t('global.created_by')">
                    {{ message.creator.name }}
                    <span class="audit-email">({{ message.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="message" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('communication.messages.show', message.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link :href="route('communication.messages.delete', message.slug)">
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button :block="isMobile" :loading="duplicatingId === message.id" @click="emit('duplicate', message)">
                    <CopyOutlined /> {{ $t('messages.duplicate') }}
                </Button>
                <Link :href="route('communication.messages.edit', message.slug)">
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
    background: var(--color-primary);
    color: var(--color-text-on-dark);
    display: flex; align-items: center; justify-content: center;
    font-size: 1.35rem; flex-shrink: 0;
}
.drawer-hero h2 { font-size: 1.15rem; font-weight: 600; margin: 0 0 6px 0; color: var(--color-text); }
.mt-4 { margin-top: 16px; }
.drawer-footer { display: flex; justify-content: flex-end; align-items: center; gap: 8px; }
.drawer-footer--mobile { flex-direction: column-reverse; gap: 10px; align-items: stretch; }
.audit-email { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
</style>
