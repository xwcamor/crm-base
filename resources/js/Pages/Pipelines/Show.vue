<script setup>
import { computed, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert, Button,
} from 'ant-design-vue';
import { HistoryOutlined, FunnelPlotOutlined, SettingOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import StageEditorDrawer from './Components/StageEditorDrawer.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    pipeline: { type: Object, required: true },
    stages:   { type: Array,  default: () => [] },
    activity:   { type: Array,  default: () => [] },
});

const fmtMoney = (n) => new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n) || 0);

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.pipeline.deleted_at);
const iconBg = computed(() => isDeleted.value
    ? 'var(--color-danger)'
    : (props.pipeline.color || 'var(--color-primary)'));

const stageDrawerRef = ref(null);
const canManageStages = computed(() => !isDeleted.value && can('pipelines.edit'));

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
</script>

<template>
    <Head :title="pipeline.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('crm.pipelines.index')"
            :title="pipeline.name"
            :icon-bg="iconBg"
        >
            <template #icon><FunnelPlotOutlined /></template>
            <template #subtitle>
                <Space :size="6">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="pipeline.is_active ? 'success' : 'default'" :bordered="false">
                        {{ pipeline.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="muted">ID #{{ pipeline.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="pipelines"
                    route-prefix="crm"
                    :slug="pipeline.slug"
                    :id="pipeline.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('pipelines.edit')"
                    :can-delete="can('pipelines.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(pipeline.deleted_at) }}</div>
                <div v-if="pipeline.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ pipeline.deleter.name }}
                </div>
                <div v-if="pipeline.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ pipeline.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="pipelines" route-prefix="crm" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="pipeline"
        :activity="activity"
    >
            <template #general>
                <Card :bodyStyle="{ padding: 16 }" class="info-card" style="margin-bottom: 16px">
                    <template #title>
                        <span>{{ $t('pipelines.stages_title') }}</span>
                    </template>
                    <template #extra>
                        <Button v-if="canManageStages" type="primary" size="small" @click="stageDrawerRef?.openDrawer()">
                            <SettingOutlined /> {{ $t('pipeline_stages.manage_title') }}
                        </Button>
                    </template>
                    <div class="stages-kanban">
                        <div v-for="s in stages" :key="s.id" class="stage-card" :style="{ borderTopColor: s.color || '#888' }">
                            <div class="stage-card-head">
                                <span class="stage-name">{{ s.name }}</span>
                                <Tag v-if="s.is_won" color="green" :bordered="false">{{ $t('pipelines.stage_won') }}</Tag>
                                <Tag v-else-if="s.is_lost" color="red" :bordered="false">{{ $t('pipelines.stage_lost') }}</Tag>
                                <Tag v-else color="blue" :bordered="false">{{ s.probability_pct }}%</Tag>
                            </div>
                            <div class="stage-stats">
                                <div class="stat-line"><span class="muted">{{ $t('pipelines.stage_open_deals') }}</span><strong>{{ s.deal_count }}</strong></div>
                                <div class="stat-line"><span class="muted">{{ $t('pipelines.stage_total_value') }}</span><strong>{{ fmtMoney(s.total_value) }}</strong></div>
                                <div v-if="s.rot_days > 0" class="stat-line"><span class="muted">{{ $t('pipelines.stage_rot_after') }}</span><span>{{ s.rot_days }}d</span></div>
                            </div>
                        </div>
                        <div v-if="stages.length === 0" class="empty-stages">
                            <p class="muted">{{ $t('pipelines.stages_empty') }}</p>
                            <Button v-if="canManageStages" type="primary" @click="stageDrawerRef?.openDrawer()">
                                <SettingOutlined /> {{ $t('pipeline_stages.add_first') }}
                            </Button>
                        </div>
                    </div>
                </Card>

                <StageEditorDrawer ref="stageDrawerRef" :pipeline-slug="pipeline.slug" :stages="stages" />

                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ pipeline.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ pipeline.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('pipelines.name')">{{ pipeline.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('pipelines.description')">{{ pipeline.description ?? '—' }}</DescriptionsItem>
<DescriptionsItem :label="$t('pipelines.is_active')">
                            <Tag :color="pipeline.is_active ? 'success' : 'default'" :bordered="false">
                                {{ pipeline.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(pipeline.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="pipeline.creator" :label="$t('global.created_by')">
                            {{ pipeline.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(pipeline.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(pipeline.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="pipeline.deleter" :label="$t('global.deleted_by')">
                                {{ pipeline.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ pipeline.deleted_description || '—' }}
                            </DescriptionsItem>
                        </template>
                    </Descriptions>
                </Card>

                <Card :bodyStyle="{ padding: 16 }" class="info-card">
                    <template #title>
                        <HistoryOutlined /> {{ $t('global.recent_activity') }}
                    </template>
                    <ActivityTimeline :activity="activity" />
                </Card>
            </template>
        </EntityShowTabs>
    </div>
</template>

<style scoped>
.show-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 16px; border-radius: 6px; }

.stages-kanban { display: grid; grid-template-columns: repeat(auto-fit, minmax(180px, 1fr)); gap: 12px; }
.stage-card { background: var(--color-surface-alt, #fafafa); border: 1px solid var(--color-border, #e8e8e8); border-top: 3px solid; border-radius: 6px; padding: 12px; }
.stage-card-head { display: flex; align-items: center; justify-content: space-between; margin-bottom: 8px; }
.stage-card-head .stage-name { font-weight: 600; font-size: 0.9rem; }
.stage-stats { display: flex; flex-direction: column; gap: 4px; font-size: 0.82rem; }
.stat-line { display: flex; justify-content: space-between; }
.muted { color: var(--color-text-muted, #666); }

@media (max-width: 767px) {
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
