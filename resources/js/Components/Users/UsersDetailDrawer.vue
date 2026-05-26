<script setup>
/** Drawer lateral: preview de usuario sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { DeleteOutlined, EditOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';
import UserAvatar from '@/Components/Common/UserAvatar.vue';

const { formatDateTime } = useDateFormat();

const props = defineProps({
    open:         { type: Boolean, required: true },
    user:         { type: Object,  default: null },
    width:        { type: [Number, String], default: 480 },
    isMobile:     { type: Boolean, default: false },
    canEdit:      { type: Boolean, default: false },
    canDelete:    { type: Boolean, default: false },
    isSuper: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open']);

</script>

<template>
    <Drawer
        :open="open"
        :title="$t('users.detail_drawer')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="user">
            <div class="drawer-hero">
                <UserAvatar :photo="user.photo" :name="user.name" :size="72" :updated-at="user.updated_at" />
                <div>
                    <h2>{{ user.name }}</h2>
                    <Tag :color="user.is_active ? 'success' : 'error'" :bordered="false">
                        {{ user.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem :label="$t('users.id')">{{ user.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('users.email')">{{ user.email }}</DescriptionsItem>
                <DescriptionsItem v-if="isSuper" :label="$t('users.tenant')">
                    <Tag v-if="user.tenant" color="blue" :bordered="false">
                        {{ user.tenant.name }}
                    </Tag>
                    <Tag v-else color="purple" :bordered="false">{{ $t('global.platform') }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(user.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="user.creator" :label="$t('global.created_by')">
                    {{ user.creator.name }}
                    <span class="audit-email">({{ user.creator.email }})</span>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.updated_at')">
                    {{ formatDateTime(user.updated_at) }}
                </DescriptionsItem>
            </Descriptions>
        </template>

        <!-- Sticky footer with actions — desktop: right-aligned horizontal,
             mobile: stacked full-width (touch-friendly). -->
        <template #footer>
            <div v-if="user" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link
                    v-if="canDelete"
                    :href="route('user_management.users.delete', user.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Link
                    v-if="canEdit"
                    :href="route('user_management.users.edit', user.slug)"
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
    flex-direction: column-reverse;
    gap: 10px;
    align-items: stretch;
}
.audit-email { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
.mt-4 { margin-top: 16px; }
</style>
