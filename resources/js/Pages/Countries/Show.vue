<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert,
} from 'ant-design-vue';
import {
    GlobalOutlined, HistoryOutlined,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
dayjs.extend(relativeTime);

import AppLayout from '@/Layouts/AppLayout.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    country:   { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.country.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
const lastUpdatedRel = computed(() => props.country.updated_at ? dayjs(props.country.updated_at).fromNow() : null);
</script>

<template>
    <Head :title="country.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('system_management.countries.index')"
            :title="country.name"
            :icon-bg="iconBg"
        >
            <template #icon><GlobalOutlined /></template>
            <template #subtitle>
                <Space :size="6" class="show-page__meta">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="country.is_active ? 'success' : 'default'" :bordered="false">
                        {{ country.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="page-header__id">ID #{{ country.id }}</span>
                    <span v-if="lastUpdatedRel" class="page-header__rel">
                        · {{ $t('global.updated_at') }} {{ lastUpdatedRel }}
                    </span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="countries"
                    :slug="country.slug"
                    :id="country.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('countries.edit')"
                    :can-delete="can('countries.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

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
                    <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(country.deleted_at) }}</div>
                    <div v-if="country.deleter">
                        <strong>{{ $t('global.deleted_by') }}:</strong> {{ country.deleter.name }} ({{ country.deleter.email }})
                    </div>
                    <div v-if="country.deleted_description" class="deleted-reason">
                        <strong>{{ $t('global.delete_description') }}:</strong> {{ country.deleted_description }}
                    </div>
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="countries" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="country"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ country.id }}</DescriptionsItem>
                        <DescriptionsItem label="Slug"><code>{{ country.slug }}</code></DescriptionsItem>
                        <DescriptionsItem :label="$t('countries.name')">{{ country.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('countries.iso_code')">
                            <code>{{ country.iso_code }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('countries.currency')">
                            <code>{{ country.currency }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('countries.timezone')">{{ country.timezone }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('countries.region')">
                            {{ country.region?.name ?? '—' }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('countries.default_locale')">
                            <template v-if="country.default_locale">
                                <code>{{ country.default_locale.code }}</code>
                                <span class="muted ml-1">— {{ country.default_locale.name }}</span>
                            </template>
                            <template v-else>—</template>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('countries.is_active')">
                            <Tag :color="country.is_active ? 'success' : 'default'" :bordered="false">
                                {{ country.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(country.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="country.creator" :label="$t('global.created_by')">
                            {{ country.creator.name }}
                            <span class="muted">({{ country.creator.email }})</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(country.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(country.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="country.deleter" :label="$t('global.deleted_by')">
                                {{ country.deleter.name }}
                                <span class="muted">({{ country.deleter.email }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ country.deleted_description || '—' }}
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
                    <ActivityTimeline :activity="activity" />
                </Card>
            </template>
        </EntityShowTabs>
    </div>
</template>

<style scoped>
.show-page__meta { margin-top: 4px; }
.page-header__id,
.page-header__rel {
    font-size: 0.8125rem;
    color: var(--color-text-muted);
}
.deleted-alert { margin-bottom: 16px; }
.deleted-info { display: flex; flex-direction: column; gap: 4px; font-size: 0.875rem; }
.deleted-reason { margin-top: 6px; padding-top: 6px; border-top: 1px dashed rgba(0,0,0,0.1); }
.info-card { margin-bottom: 16px; border-radius: 6px; }
.muted { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
.ml-1 { margin-left: 4px; }
</style>
