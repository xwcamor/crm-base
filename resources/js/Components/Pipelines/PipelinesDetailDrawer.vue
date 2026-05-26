<script setup>
/** Drawer lateral: preview rapido del cliente sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { FunnelPlotOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    pipeline:      { type: Object,  default: null },
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
        :title="$t('pipelines.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="pipeline">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <FunnelPlotOutlined />
                </div>
                <div>
                    <h2>{{ pipeline.name }}</h2>
                    <Tag :color="pipeline.is_active ? 'success' : 'default'" :bordered="false">
                        {{ pipeline.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4" size="small">
                <DescriptionsItem v-if="pipeline.description" :label="$t('pipelines.description')">
                    {{ pipeline.description }}
                </DescriptionsItem>
                <DescriptionsItem v-if="pipeline.color" :label="$t('pipelines.color')">
                    <span :style="{ display: 'inline-block', width: '20px', height: '20px', borderRadius: '4px', background: pipeline.color, border: '1px solid rgba(0,0,0,0.1)', verticalAlign: 'middle', marginRight: '6px' }" />
                    <code class="muted">{{ pipeline.color }}</code>
                </DescriptionsItem>
                <DescriptionsItem v-if="pipeline.stages_count != null" :label="$t('pipelines.stages_count')">
                    <Tag :bordered="false">{{ pipeline.stages_count }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem v-if="pipeline.open_deals_count != null" :label="$t('pipelines.open_deals_count')">
                    <Tag v-if="pipeline.open_deals_count > 0" color="blue" :bordered="false">{{ pipeline.open_deals_count }}</Tag>
                    <span v-else class="muted">0</span>
                </DescriptionsItem>
                <DescriptionsItem v-if="pipeline.is_default" :label="$t('pipelines.is_default')">
                    <Tag color="gold" :bordered="false">{{ $t('global.yes') ?? 'Si' }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem label="ID">{{ pipeline.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(pipeline.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="pipeline.creator" :label="$t('global.created_by')">
                    {{ pipeline.creator.name }}
                    <span class="audit-email">({{ pipeline.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="pipeline" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('crm.pipelines.show', pipeline.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('crm.pipelines.delete', pipeline.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === pipeline.id"
                    @click="emit('duplicate', pipeline)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('crm.pipelines.edit', pipeline.slug)"
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
