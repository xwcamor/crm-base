<script setup>
import { computed } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import {
    Card, Tag, Button, Space, Tabs, TabPane, Table, Tooltip,
    Descriptions, DescriptionsItem, Empty, Alert, Badge,
} from 'ant-design-vue';
import {
    SafetyCertificateOutlined, BankOutlined, HistoryOutlined,
    CheckCircleOutlined, CloseCircleOutlined, FileTextOutlined,
} from '@ant-design/icons-vue';
import { resolveIconComponent, resolveColor } from '@/Utils/planAppearance';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const { t } = useI18n();
const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTime, formatDateTimeFull } = useDateFormat();

const props = defineProps({
    plan:                       { type: Object, required: true },
    tenants_count:              { type: Number, default: 0 },
    active_subscriptions_count: { type: Number, default: 0 },
    tenants:                    { type: Array,  default: () => [] },
    activity:                   { type: Array,  default: () => [] },
    featureKeys:                { type: Array,  default: () => [] },
});

const isDeleted = computed(() => !!props.plan.deleted_at);
const iconBg    = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Color e icono del plan desde DB. Fallback: default + SafetyCertificateOutlined.
const planTagColor   = computed(() => resolveColor(props.plan.color));
const planIcon       = computed(() => resolveIconComponent(props.plan.icon));
const headerIconComp = computed(() => planIcon.value ?? SafetyCertificateOutlined);

// Wrappers locales para mantener call-sites compactos (fmt/fmtShort en templates).
const fmt       = (d) => formatDateTimeFull(d);
const fmtShort  = (d) => formatDateTime(d);
const fmtLimit  = (n) => n < 0 ? '∞' : n.toLocaleString();
const fmtMoney  = (n, c) => n > 0 ? `${c} ${Number(n).toFixed(2)}` : '—';
const lastUpdatedRel = computed(() => props.plan.updated_at ? dayjs(props.plan.updated_at).fromNow() : null);

// Features agrupadas — MISMO criterio que Plans/Form.vue. El catch-all
// garantiza que toda key de featureKeys() se muestre aunque no tenga grupo
// asignado — sin esto el detalle queda incompleto vs el form.
const featureGroups = computed(() => {
    const groups = [
        { title: t('plans.group_exports'),    keys: ['export_csv', 'export_excel', 'export_pdf', 'export_word', 'branded_exports'] },
        { title: t('plans.group_team'),       keys: ['team_management', 'bulk_operations', 'imports', 'edit_all'] },
        { title: t('plans.group_visibility'), keys: ['audit_log_view', 'saved_views'] },
        { title: t('plans.group_advanced'),   keys: ['api_access', 'automations', 'scheduled_exports',
                                                      'export_webhook_delivery', 'export_email_delivery'] },
        { title: t('plans.group_quality'),    keys: ['extended_retention', 'higher_export_rate_limit'] },
    ];
    const grouped = new Set(groups.flatMap(g => g.keys));
    const orphans = props.featureKeys.filter(k => !grouped.has(k));
    if (orphans.length) groups.push({ title: t('plans.group_other'), keys: orphans });
    return groups;
});

const featureLabel = (key) => t(`plans.feature_${key.replace(/_(.)/g, (_, c) => c.toUpperCase())}`);

const supportLabel = computed(() => {
    const map = {
        community: t('plans.support_community'),
        email:     t('plans.support_email'),
        priority:  t('plans.support_priority'),
    };
    return map[props.plan.support_level] || map.community;
});

const tenantColumns = computed(() => [
    { title: 'ID',                   dataIndex: 'id',         key: 'id',         width: 80,  sorter: (a, b) => a.id - b.id },
    { title: t('plans.tenant_name'), dataIndex: 'name',       key: 'name',                   sorter: (a, b) => a.name.localeCompare(b.name) },
    { title: t('global.created_at'), dataIndex: 'created_at', key: 'created_at', width: 180, sorter: (a, b) => new Date(a.created_at) - new Date(b.created_at), defaultSortOrder: 'descend' },
    { title: t('global.actions'),    key: 'actions',          width: 100,        fixed: 'right' },
]);
</script>

<template>
    <Head :title="plan.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('system_management.plans.index')"
            :title="plan.name"
            :icon-bg="iconBg"
        >
            <template #icon><component :is="headerIconComp" /></template>
            <template #subtitle>
                <Space :size="6" class="show-page__meta">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="plan.is_active ? 'success' : 'default'" :bordered="false">
                        {{ plan.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <Tag :color="planTagColor" :bordered="false" class="plan-tag">
                        <component :is="planIcon" v-if="planIcon" /> {{ plan.slug.toUpperCase() }}
                    </Tag>
                    <span class="show-page__id">ID #{{ plan.id }}</span>
                    <span v-if="lastUpdatedRel" class="show-page__rel">
                        · {{ $t('global.updated_at') }} {{ lastUpdatedRel }}
                    </span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="plans"
                    :slug="plan.id"
                    :id="plan.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('plans.edit') || isSuper"
                    :can-delete="can('plans.delete') || isSuper"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <!-- Alert si el plan fue soft-deleted -->
        <Alert
            v-if="isDeleted"
            type="error"
            show-icon
            class="deleted-alert"
        >
            <template #message>
                <span v-html="$t('global.record_is_deleted')" />
            </template>
            <template #description>
                <div class="deleted-info">
                    <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(plan.deleted_at) }}</div>
                    <div v-if="plan.deleter">
                        <strong>{{ $t('global.deleted_by') }}:</strong> {{ plan.deleter.name }} ({{ plan.deleter.email }})
                    </div>
                    <div v-if="plan.deleted_description" class="deleted-reason">
                        <strong>{{ $t('global.delete_description') }}:</strong> {{ plan.deleted_description }}
                    </div>
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="plans" />
            </template>
        </Alert>

        <Card class="tabs-card" :bodyStyle="{ padding: '0 16px' }">
            <Tabs default-active-key="info" :tabBarStyle="{ marginBottom: '16px' }">

                <!-- TAB: Detalles — SOLO dominio. Sin updated_at ni info de audit. -->
                <TabPane key="info">
                    <template #tab>
                        <span class="tab-label"><FileTextOutlined /> {{ $t('global.details') }}</span>
                    </template>

                    <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                        <Descriptions :column="{ xs: 1, md: 2 }" bordered :labelStyle="{ width: '200px' }">
                            <DescriptionsItem :label="$t('plans.slug')">
                                <code class="mono">{{ plan.slug }}</code>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.name')">
                                <Tag :color="planTagColor" :bordered="false">
                                    <component :is="planIcon" v-if="planIcon" />
                                    {{ plan.name.toUpperCase() }}
                                </Tag>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.tagline')">{{ plan.tagline || '—' }}</DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.is_active')">
                                <Tag :color="plan.is_active ? 'success' : 'default'" :bordered="false">
                                    {{ plan.is_active ? $t('global.active') : $t('global.inactive') }}
                                </Tag>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.is_public')">
                                <Tag :color="plan.is_public ? 'success' : 'default'" :bordered="false">
                                    {{ plan.is_public ? $t('global.yes') : $t('global.no') }}
                                </Tag>
                            </DescriptionsItem>
                        </Descriptions>
                    </Card>

                    <Card :bodyStyle="{ padding: 0 }" class="info-card">
                        <template #title>{{ $t('plans.section_limits') }} + {{ $t('plans.section_pricing') }}</template>
                        <Descriptions :column="{ xs: 1, md: 3 }" bordered :labelStyle="{ width: '180px' }">
                            <DescriptionsItem :label="$t('plans.max_users')">
                                <strong>{{ fmtLimit(plan.max_users) }}</strong>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.max_records_per_module')">
                                <strong>{{ fmtLimit(plan.max_records_per_module) }}</strong>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.export_rate_limit')">
                                {{ plan.export_rate_limit }} /min
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.support_level')">
                                <Tag :bordered="false">{{ supportLabel }}</Tag>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.price_monthly')">
                                {{ fmtMoney(plan.price_monthly, plan.currency) }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.price_yearly')">
                                {{ fmtMoney(plan.price_yearly, plan.currency) }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('plans.currency')">
                                <code>{{ plan.currency }}</code>
                            </DescriptionsItem>
                        </Descriptions>
                    </Card>

                    <Card :title="$t('plans.section_features')" :bodyStyle="{ padding: 16 }" class="info-card">
                        <div v-for="g in featureGroups" :key="g.title" class="feature-group">
                            <div class="feature-group__title">{{ g.title }}</div>
                            <Space wrap :size="[8, 8]">
                                <Tag
                                    v-for="key in g.keys"
                                    :key="key"
                                    :color="plan.features?.[key] ? 'success' : 'default'"
                                    :bordered="false"
                                >
                                    <CheckCircleOutlined v-if="plan.features?.[key]" />
                                    <CloseCircleOutlined v-else />
                                    {{ featureLabel(key) }}
                                </Tag>
                            </Space>
                        </div>
                    </Card>
                </TabPane>

                <!-- TAB: Tenants en este plan -->
                <TabPane key="tenants">
                    <template #tab>
                        <span class="tab-label"><BankOutlined /> {{ $t('plans.tab_tenants') }}
                            <Badge :count="tenants_count" :overflow-count="99" :number-style="{ backgroundColor: 'var(--color-surface-alt)', color: 'var(--color-primary)', boxShadow: '0 0 0 1px var(--color-border) inset' }" />
                        </span>
                    </template>

                    <Empty v-if="tenants.length === 0" :description="$t('plans.no_tenants_in_plan')" />

                    <Table
                        v-else
                        :data-source="tenants"
                        :columns="tenantColumns"
                        :pagination="false"
                        row-key="id"
                        size="small"
                    >
                        <template #bodyCell="{ column, record }">
                            <template v-if="column.key === 'name'">
                                <Space>
                                    <Tag :color="record.is_active ? 'success' : 'default'" :bordered="false">
                                        {{ record.is_active ? $t('global.active') : $t('global.inactive') }}
                                    </Tag>
                                    <strong>{{ record.name }}</strong>
                                </Space>
                            </template>
                            <template v-else-if="column.key === 'created_at'">
                                {{ fmtShort(record.created_at) }}
                            </template>
                            <template v-else-if="column.key === 'actions'">
                                <Link :href="route('system_management.tenants.show', record.slug)">
                                    <Button size="small" type="link">{{ $t('global.view') }}</Button>
                                </Link>
                            </template>
                        </template>
                    </Table>
                </TabPane>

                <!-- TAB: Historial — metadata de auditoría + timeline. Gated por canSeeAudit. -->
                <TabPane v-if="canSeeAudit" key="history">
                    <template #tab>
                        <span class="tab-label"><HistoryOutlined /> {{ $t('global.history') }}
                            <Badge v-if="activity.length > 0" :count="activity.length" :overflow-count="99" :number-style="{ backgroundColor: 'var(--color-surface-alt)', color: 'var(--color-primary)', boxShadow: '0 0 0 1px var(--color-border) inset' }" />
                        </span>
                    </template>

                    <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                        <Descriptions :column="1" bordered :labelStyle="{ width: '200px' }">
                            <DescriptionsItem :label="$t('global.created_at')">{{ fmt(plan.created_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="plan.creator" :label="$t('global.created_by')">
                                {{ plan.creator.name }}
                                <span class="muted">({{ plan.creator.email }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(plan.updated_at) }}</DescriptionsItem>
                            <template v-if="isDeleted">
                                <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(plan.deleted_at) }}</DescriptionsItem>
                                <DescriptionsItem v-if="plan.deleter" :label="$t('global.deleted_by')">
                                    {{ plan.deleter.name }}
                                    <span class="muted">({{ plan.deleter.email }})</span>
                                </DescriptionsItem>
                                <DescriptionsItem :label="$t('global.delete_description')">
                                    {{ plan.deleted_description || '—' }}
                                </DescriptionsItem>
                            </template>
                        </Descriptions>
                    </Card>

                    <Card :bodyStyle="{ padding: 16 }" class="info-card">
                        <template #title>
                            <span class="activity-card__title">
                                <HistoryOutlined /> {{ $t('global.recent_activity') }}
                            </span>
                        </template>
                        <Empty v-if="activity.length === 0" :description="$t('plans.no_activity')" />
                        <ActivityTimeline v-else :activity="activity" />
                    </Card>
                </TabPane>

            </Tabs>
        </Card>
    </div>
</template>

<style scoped>
.show-page { width: 100%; }
.show-page__meta { margin-top: 4px; }
.show-page__id,
.show-page__rel {
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}
.deleted-alert { margin-bottom: 16px; }
.deleted-info { display: flex; flex-direction: column; gap: 4px; font-size: 0.875rem; }
.deleted-reason { margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(0,0,0,0.1); }

.tabs-card { border-radius: 6px; transition: box-shadow 0.18s ease; }
.tabs-card:hover { box-shadow: 0 4px 14px rgba(15, 23, 42, 0.08); }

.info-card { margin-bottom: 16px; border-radius: 6px; }
.mono {
    font-family: ui-monospace, 'SF Mono', Consolas, monospace;
    font-size: 0.875rem;
    background: var(--color-surface-alt);
    padding: 2px 6px;
    border-radius: 4px;
}
.plan-tag { font-weight: 600; letter-spacing: 0.3px; }
.feature-group { margin-bottom: 14px; }
.feature-group__title {
    font-size: 0.8125rem;
    font-weight: 600;
    color: var(--color-text-strong);
    margin-bottom: 6px;
}
.muted { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
.tab-label { display: inline-flex; align-items: center; gap: 6px; }
</style>
