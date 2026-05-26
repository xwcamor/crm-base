<script setup>
import { Button, Space, Tooltip } from 'ant-design-vue';
import { Link, router } from '@inertiajs/vue3';
import {
    EyeOutlined, EditOutlined, DeleteOutlined,
} from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

// Navegacion explicita para left-click — Link wrapping provee right-click
// "Abrir en nueva pestana" pero el click izquierdo no siempre dispara la
// navegacion de Inertia cuando hay <a><button> anidado (quirk de browser).
const goDelete = (slug) => router.visit(route('user_management.users.delete', slug));

const { t } = useI18n();

defineProps({
    record:        { type: Object,  required: true },
    isMobile:      { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

defineEmits(['edit', 'delete']);
</script>

<template>
    <!-- Mobile: Ver -> Editar -> Eliminar (Users no tiene duplicate). -->
    <div v-if="isMobile" class="row-actions-mobile" @click.stop>
        <Tooltip :title="t('global.view')">
            <Link :href="route('user_management.users.show', record.slug)">
                <Button
                    type="text"
                    class="row-icon-btn"
                    :aria-label="t('global.view')"
                >
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canEdit" :title="t('users.edit_hint')">
            <Link :href="route('user_management.users.edit', record.slug)">
                <Button
                    type="text"
                    class="row-icon-btn"
                    :aria-label="t('global.edit')"
                >
                    <EditOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canDelete" :title="t('users.delete_hint')">
            <Link :href="route('user_management.users.delete', record.slug)">
                <Button
                    type="text"
                    danger
                    class="row-icon-btn"
                    :aria-label="t('global.delete')"
                    @click.stop="goDelete(record.slug)"
                >
                    <DeleteOutlined />
                </Button>
            </Link>
        </Tooltip>
    </div>

    <!-- Desktop: Ver + Editar + Eliminar. -->
    <Space v-else :size="4" class="row-actions-desktop" @click.stop>
        <Tooltip :title="t('global.view')">
            <Link :href="route('user_management.users.show', record.slug)">
                <Button size="small" type="text" :aria-label="t('global.view')">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canEdit" :title="t('users.edit_hint')">
            <Link :href="route('user_management.users.edit', record.slug)">
                <Button size="small" type="text">
                    <EditOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canDelete" :title="t('users.delete_hint')">
            <Link :href="route('user_management.users.delete', record.slug)">
                <Button size="small" type="text" danger @click.stop="goDelete(record.slug)">
                    <DeleteOutlined />
                </Button>
            </Link>
        </Tooltip>
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
