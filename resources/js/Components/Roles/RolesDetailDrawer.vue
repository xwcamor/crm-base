<script setup>
/** Drawer lateral con preview rapido de un Role sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button, Space } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { TeamOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:      { type: Boolean, required: true },
    role:      { type: Object,  default: null },
    width:     { type: [Number, String], default: 480 },
    isMobile:  { type: Boolean, default: false },
    canCreate: { type: Boolean, default: false },
    canEdit:   { type: Boolean, default: false },
    canDelete: { type: Boolean, default: false },
});

const emit = defineEmits(['update:open', 'duplicate']);
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('roles.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="role">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <TeamOutlined />
                </div>
                <div>
                    <h2>{{ role.name }}</h2>
                    <Space :size="6" wrap>
                        <Tag :color="role.is_active ? 'success' : 'error'" :bordered="false">
                            {{ role.is_active ? $t('global.active') : $t('global.inactive') }}
                        </Tag>
                        <Tag v-if="role.is_system" color="purple" :bordered="false">
                            {{ $t('roles.tag_system') }}
                        </Tag>
                        <Tag v-else-if="role.tenant_id === null" color="orange" :bordered="false">
                            {{ $t('roles.tag_global') }}
                        </Tag>
                        <Tag v-else color="blue" :bordered="false">{{ role.tenant_name ?? '—' }}</Tag>
                    </Space>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ role.id }}</DescriptionsItem>
                <DescriptionsItem v-if="role.description" :label="$t('roles.description')">
                    {{ role.description }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('roles.permissions_count')">
                    <Tag :color="role.permissions_count > 0 ? 'cyan' : 'default'">{{ role.permissions_count }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('roles.users_count')">
                    <Tag :color="role.users_count > 0 ? 'green' : 'default'">{{ role.users_count }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(role.created_at) }}
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="role" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('user_management.roles.show', role.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <template v-if="!role.is_system">
                    <Link
                        v-if="canDelete"
                        :href="route('user_management.roles.delete', role.slug)"
                    >
                        <Button :block="isMobile" ghost danger>
                            <DeleteOutlined /> {{ $t('global.delete') }}
                        </Button>
                    </Link>
                    <Button
                        v-if="canCreate"
                        :block="isMobile"
                        @click="emit('duplicate', role)"
                    >
                        <CopyOutlined /> {{ $t('global.duplicate') }}
                    </Button>
                    <Link
                        v-if="canEdit"
                        :href="route('user_management.roles.edit', role.slug)"
                    >
                        <Button :block="isMobile" type="primary">
                            <EditOutlined /> {{ $t('global.edit') }}
                        </Button>
                    </Link>
                </template>
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
</style>
