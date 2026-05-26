<script setup>
import { Button, Space, Tooltip } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import {
    EyeOutlined, EditOutlined, CopyOutlined, DeleteOutlined,
} from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineProps({
    record:        { type: Object,  required: true },
    isMobile:      { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

defineEmits(['edit', 'duplicate', 'delete']);
</script>

<template>
    <!-- Mobile: Ver → Editar → Duplicar → Eliminar. El row tap también abre
         el drawer; el botón Ver lleva a la página completa de detalle. -->
    <div v-if="isMobile" class="row-actions-mobile">
        <Tooltip :title="t('global.view')">
            <Link :href="route('system_management.regions.show', record.slug)">
                <Button
                    type="text"
                    class="row-icon-btn"
                    :aria-label="t('global.view')"
                >
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canEdit" :title="t('global.edit')">
            <Button
                type="text"
                class="row-icon-btn"
                :aria-label="t('global.edit')"
                @click="$emit('edit', record)"
            >
                <EditOutlined />
            </Button>
        </Tooltip>
        <Tooltip v-if="canCreate" :title="t('global.duplicate')">
            <Button
                type="text"
                class="row-icon-btn"
                :aria-label="t('global.duplicate')"
                :loading="duplicatingId === record.id"
                @click="$emit('duplicate', record)"
            >
                <CopyOutlined />
            </Button>
        </Tooltip>
        <Tooltip v-if="canDelete" :title="t('global.delete')">
            <Link :href="route('system_management.regions.delete', record.slug)">
                <Button
                    type="text"
                    danger
                    class="row-icon-btn"
                    :aria-label="t('global.delete')"
                    @click.stop="$emit('delete', record)"
                >
                    <DeleteOutlined />
                </Button>
            </Link>
        </Tooltip>
    </div>

    <!-- Desktop: Ver (explícito, va a /show) + Editar + Duplicar + Eliminar.
         El row click también abre el drawer; el botón Ver lleva a la página
         completa de detalle (algunos usuarios prefieren eso al drawer). -->
    <Space v-else size="small" class="row-actions-desktop">
        <Tooltip :title="t('global.view')">
            <Link :href="route('system_management.regions.show', record.slug)">
                <Button size="small" type="text" :aria-label="t('global.view')">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canEdit" :title="t('global.edit')">
            <Link :href="route('system_management.regions.edit', record.slug)">
                <Button size="small" type="text">
                    <EditOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canCreate" :title="t('global.duplicate')">
            <Button
                size="small"
                type="text"
                :loading="duplicatingId === record.id"
                @click.stop="$emit('duplicate', record)"
            >
                <CopyOutlined />
            </Button>
        </Tooltip>
        <Tooltip v-if="canDelete" :title="t('global.delete')">
            <Link :href="route('system_management.regions.delete', record.slug)">
                <Button size="small" type="text" danger @click.stop="$emit('delete', record)">
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
