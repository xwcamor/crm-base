<script setup>
import { Button, Space, Tooltip, Popconfirm } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import {
    EyeOutlined, EditOutlined, CopyOutlined, DeleteOutlined, PlayCircleOutlined,
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

defineEmits(['edit', 'duplicate', 'delete', 'run-now']);
</script>

<template>
    <!-- Mobile: Ver → Run-now → Editar → Duplicar → Eliminar -->
    <div v-if="isMobile" class="row-actions-mobile">
        <Tooltip :title="t('global.view')">
            <Link :href="route('automation_management.automations.show', record.id)">
                <Button
                    type="text"
                    class="row-icon-btn"
                    :aria-label="t('global.view')"
                >
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Popconfirm
            :title="t('automations.run_now') + '?'"
            :ok-text="t('automations.run_now')"
            :cancel-text="t('global.cancel')"
            @confirm="$emit('run-now', record)"
        >
            <Tooltip :title="t('automations.run_now_hint')">
                <Button
                    type="text"
                    class="row-icon-btn"
                    :aria-label="t('automations.run_now')"
                    @click.stop
                >
                    <PlayCircleOutlined />
                </Button>
            </Tooltip>
        </Popconfirm>
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
            <Link :href="route('automation_management.automations.delete', record.id)">
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

    <!-- Desktop: Ver + Run-now + Editar + Duplicar + Eliminar -->
    <Space v-else size="small" class="row-actions-desktop">
        <Tooltip :title="t('global.view')">
            <Link :href="route('automation_management.automations.show', record.id)">
                <Button size="small" type="text" :aria-label="t('global.view')">
                    <EyeOutlined />
                </Button>
            </Link>
        </Tooltip>
        <Popconfirm
            :title="t('automations.run_now') + '?'"
            :ok-text="t('automations.run_now')"
            :cancel-text="t('global.cancel')"
            @confirm="$emit('run-now', record)"
        >
            <Tooltip :title="t('automations.run_now_hint')">
                <Button size="small" type="text" @click.stop>
                    <PlayCircleOutlined />
                </Button>
            </Tooltip>
        </Popconfirm>
        <Tooltip v-if="canEdit" :title="t('global.edit')">
            <Link :href="route('automation_management.automations.edit', record.id)">
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
            <Link :href="route('automation_management.automations.delete', record.id)">
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
