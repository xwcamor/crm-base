<script setup>
/**
 * Action cell para Plans — clon del patron DiscountsActionsCell:
 *   - Desktop: icon-only buttons (Ver / Editar / Duplicar / Eliminar)
 *   - Mobile: mismo set con buttons mas grandes
 *   - Hover-to-reveal opacity heredado del padre (.row-actions-desktop)
 */
import { Button, Space, Tooltip } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { EyeOutlined, EditOutlined, CopyOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineProps({
    record:        { type: Object,  required: true },
    isMobile:      { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: true  },
    canCreate:     { type: Boolean, default: true  },
    canDelete:     { type: Boolean, default: true  },
    duplicatingId: { type: [Number, String, null], default: null },
});

defineEmits(['edit', 'duplicate', 'delete']);
</script>

<template>
    <div v-if="isMobile" class="row-actions-mobile" @click.stop>
        <Tooltip :title="t('global.view')">
            <Link :href="route('system_management.plans.show', record.id)">
                <Button type="text" class="row-icon-btn" :aria-label="t('global.view')">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canEdit" :title="t('plans.edit_hint')">
            <Link :href="route('system_management.plans.edit', record.id)">
                <Button type="text" class="row-icon-btn" :aria-label="t('global.edit')">
                    <EditOutlined />
                </Button>
            </Link>
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
        <Tooltip v-if="canDelete" :title="t('plans.delete_hint')">
            <Link :href="route('system_management.plans.delete', record.id)">
                <Button type="text" danger class="row-icon-btn" :aria-label="t('global.delete')" @click.stop="$emit('delete', record)">
                    <DeleteOutlined />
                </Button>
            </Link>
        </Tooltip>
    </div>

    <Space v-else :size="4" class="row-actions-desktop" @click.stop>
        <Tooltip :title="t('global.view')">
            <Link :href="route('system_management.plans.show', record.id)">
                <Button size="small" type="text" :aria-label="t('global.view')">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canEdit" :title="t('plans.edit_hint')">
            <Link :href="route('system_management.plans.edit', record.id)">
                <Button size="small" type="text">
                    <EditOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Tooltip v-if="canCreate" :title="t('global.duplicate')">
            <Button size="small" type="text" :loading="duplicatingId === record.id" @click.stop="$emit('duplicate', record)">
                <CopyOutlined />
            </Button>
        </Tooltip>
        <Tooltip v-if="canDelete" :title="t('plans.delete_hint')">
            <Link :href="route('system_management.plans.delete', record.id)">
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
