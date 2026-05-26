<script setup>
/** Drawer lateral: preview de la automation sin salir del listado. */
import { computed } from 'vue';
import { Drawer, Tag, Descriptions, DescriptionsItem, Button, Switch, Popconfirm, Tooltip } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { BranchesOutlined, DeleteOutlined, CopyOutlined, EditOutlined, PlayCircleOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

const props = defineProps({
    open:        { type: Boolean, required: true },
    automation:  { type: Object,  default: null },
    catalog:     { type: Object,  default: () => ({ data_sources: [], actions: [] }) },
    width:       { type: [Number, String], default: 480 },
    isMobile:    { type: Boolean, default: false },
    canCreate:   { type: Boolean, default: false },
    canEdit:     { type: Boolean, default: false },
    canDelete:   { type: Boolean, default: false },
    duplicatingId: { type: [Number, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate', 'run-now', 'toggle']);

const fmt = (d) => d ? dayjs(d).format('YYYY-MM-DD HH:mm') : '—';
const fmtRel = (d) => d ? dayjs(d).fromNow() : '—';

const sourceLabel = computed(() => {
    if (!props.automation?.data_source) return '—';
    return props.catalog.data_sources?.find(s => s.key === props.automation.data_source)?.label
        ?? props.automation.data_source;
});

const actionLabel = computed(() => {
    if (!props.automation?.action_type) return '—';
    return props.catalog.actions?.find(a => a.key === props.automation.action_type)?.label
        ?? props.automation.action_type;
});

const triggerSummary = computed(() => {
    if (!props.automation) return '—';
    const c = props.automation.trigger_config ?? {};
    switch (c.kind) {
        case 'daily':   return `${t('automations.trigger_kind_daily')} · ${c.time ?? '09:00'}`;
        case 'weekly':  return `${t('automations.trigger_kind_weekly')} · ${c.time ?? '09:00'}`;
        case 'monthly': return `${t('automations.trigger_kind_monthly')} · ${c.time ?? '09:00'}`;
        case 'cron':    return `cron: ${c.expression}`;
        default:        return '—';
    }
});
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('automations.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="automation">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <BranchesOutlined />
                </div>
                <div>
                    <h2>{{ automation.name }}</h2>
                    <div class="drawer-hero__status">
                        <Switch
                            :checked="automation.is_active"
                            size="small"
                            @click.stop
                            @change="emit('toggle', automation)"
                        />
                        <Tag :color="automation.is_active ? 'success' : 'default'" :bordered="false">
                            {{ automation.is_active ? $t('global.active') : $t('global.inactive') }}
                        </Tag>
                    </div>
                </div>
            </div>

            <p v-if="automation.description" class="drawer-desc">{{ automation.description }}</p>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ automation.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('automations.data_source')">{{ sourceLabel }}</DescriptionsItem>
                <DescriptionsItem :label="$t('automations.action_type')">
                    <Tag :bordered="false">{{ actionLabel }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('automations.col_trigger')">{{ triggerSummary }}</DescriptionsItem>
                <DescriptionsItem :label="$t('automations.col_runs')">
                    <Tag :color="automation.runs_count > 0 ? 'cyan' : 'default'" :bordered="false">
                        {{ automation.runs_count }}
                    </Tag>
                    <Tag v-if="automation.failures_count > 0" color="error" :bordered="false">
                        {{ automation.failures_count }} fail
                    </Tag>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('automations.col_next_run')">
                    <Tooltip v-if="automation.next_run_at" :title="fmt(automation.next_run_at)">
                        {{ fmtRel(automation.next_run_at) }}
                    </Tooltip>
                    <span v-else class="audit-email">{{ $t('automations.next_run_none') }}</span>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ fmt(automation.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="automation.creator" :label="$t('global.created_by')">
                    {{ automation.creator.name }}
                    <span class="audit-email">({{ automation.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <!-- Sticky footer with actions — desktop horizontal, mobile stacked. -->
        <template #footer>
            <div v-if="automation" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link
                    v-if="canDelete"
                    :href="route('automation_management.automations.delete', automation.id)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === automation.id"
                    @click="emit('duplicate', automation)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Popconfirm
                    :title="$t('automations.run_now') + '?'"
                    :ok-text="$t('automations.run_now')"
                    :cancel-text="$t('global.cancel')"
                    @confirm="emit('run-now', automation)"
                >
                    <Button :block="isMobile">
                        <PlayCircleOutlined /> {{ $t('automations.run_now') }}
                    </Button>
                </Popconfirm>
                <Link
                    v-if="canEdit"
                    :href="route('automation_management.automations.edit', automation.id)"
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
.drawer-hero__status { display: flex; align-items: center; gap: 8px; }
.drawer-desc {
    color: var(--color-text-muted);
    font-size: 0.875rem;
    margin: 12px 0 0 0;
    line-height: 1.5;
}
.drawer-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
    flex-wrap: wrap;
}
.drawer-footer--mobile {
    flex-direction: column-reverse;
    gap: 10px;
    align-items: stretch;
}
.audit-email { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
</style>
