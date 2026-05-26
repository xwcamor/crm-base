<script setup>
import { Button, Space, Tag, Tooltip, Popconfirm } from 'ant-design-vue';
import { Link, router } from '@inertiajs/vue3';
import {
    EyeOutlined, EditOutlined, CopyOutlined, DeleteOutlined,
} from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

// Navegacion explicita para left-click — Link wrapping provee right-click
// "Abrir en nueva pestana" pero el click izquierdo no siempre dispara la
// navegacion de Inertia cuando hay <a><button> anidado (quirk de browser).
const goDelete = (slug) => router.visit(route('user_management.roles.delete', slug));

const { t } = useI18n();

defineProps({
    record:        { type: Object,  required: true },
    isMobile:      { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

defineEmits(['duplicate']);
</script>

<template>
    <!-- Mobile: Ver -> Editar -> Eliminar. Sistema: Tag protegido. -->
    <div v-if="isMobile" class="row-actions-mobile" @click.stop>
        <Tooltip :title="t('global.details')">
            <Link :href="route('user_management.roles.show', record.slug)">
                <Button type="text" class="row-icon-btn" :aria-label="t('global.details')">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <template v-if="!record.is_system">
            <Tooltip v-if="canEdit" :title="t('global.edit')">
                <Link :href="route('user_management.roles.edit', record.slug)">
                    <Button type="text" class="row-icon-btn" :aria-label="t('global.edit')">
                        <EditOutlined />
                    </Button>
                </Link>
            </Tooltip>
            <Tooltip v-if="canDelete" :title="t('global.delete')">
                <Link :href="route('user_management.roles.delete', record.slug)">
                    <Button type="text" danger class="row-icon-btn" :aria-label="t('global.delete')" @click.stop="goDelete(record.slug)">
                        <DeleteOutlined />
                    </Button>
                </Link>
            </Tooltip>
        </template>
        <Tag v-else color="default" :bordered="false">{{ t('roles.protected') }}</Tag>
    </div>

    <!-- Desktop: Ver + Editar + Duplicar (con Popconfirm) + Eliminar. -->
    <Space v-else :size="4" class="row-actions-desktop" @click.stop>
        <Tooltip :title="t('global.details')">
            <Link :href="route('user_management.roles.show', record.slug)">
                <Button size="small" type="text">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <template v-if="!record.is_system">
            <Tooltip v-if="canEdit" :title="t('global.edit')">
                <Link :href="route('user_management.roles.edit', record.slug)">
                    <Button size="small" type="text">
                        <EditOutlined />
                    </Button>
                </Link>
            </Tooltip>
            <Tooltip v-if="canCreate" :title="t('global.duplicate')">
                <Popconfirm
                    :title="t('roles.confirm_duplicate') || '¿Duplicar este perfil?'"
                    :ok-text="t('global.duplicate')"
                    :cancel-text="t('global.cancel')"
                    @confirm="$emit('duplicate', record)"
                >
                    <Button size="small" type="text" :loading="duplicatingId === record.id">
                        <CopyOutlined />
                    </Button>
                </Popconfirm>
            </Tooltip>
            <Tooltip v-if="canDelete" :title="t('global.delete')">
                <Link :href="route('user_management.roles.delete', record.slug)">
                    <Button size="small" type="text" danger @click.stop="goDelete(record.slug)">
                        <DeleteOutlined />
                    </Button>
                </Link>
            </Tooltip>
        </template>
        <Tag v-else color="default" :bordered="false">{{ t('roles.protected') }}</Tag>
    </Space>
</template>

<style scoped>
.row-actions-mobile {
    display: flex;
    justify-content: flex-end;
    gap: 4px;
    width: 100%;
}
.row-icon-btn {
    width: 40px !important;
    height: 40px !important;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    padding: 0 !important;
}
.row-icon-btn :deep(.anticon) { font-size: 18px; }
</style>
