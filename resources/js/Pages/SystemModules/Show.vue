<script setup>
import { computed, ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import {
    Card, Tag, Button, Space, Descriptions, DescriptionsItem, Alert,
    Modal, Input, FormItem, Popconfirm, Tooltip,
} from 'ant-design-vue';
import {
    GlobalOutlined, HistoryOutlined, KeyOutlined, SafetyCertificateOutlined,
    PlusOutlined, CloseOutlined,
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
    system_module:    { type: Object, required: true },
    permissions:      { type: Array,  default: () => [] },
    canonicalActions: { type: Array,  default: () => [] },
    activity:         { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.system_module.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

// Wrapper local para mantener call-sites compactos (fmt(...) en templates).
const fmt = (d) => formatDateTimeFull(d);
const lastUpdatedRel = computed(() => props.system_module.updated_at ? dayjs(props.system_module.updated_at).fromNow() : null);

// Single source of truth de canónicas viene del backend (Observer::CANONICAL_ACTIONS).
const isCanonical = (action) => props.canonicalActions.includes(action);

// Modal "Agregar acción"
const addModalOpen = ref(false);
const addForm = useForm({ action: '' });

const openAddModal = () => {
    addForm.reset();
    addForm.clearErrors();
    addModalOpen.value = true;
};
const closeAddModal = () => { addModalOpen.value = false; };
const submitAddAction = () => {
    addForm.post(route('system_management.system_modules.permissions.store', props.system_module.slug), {
        preserveScroll: true,
        onSuccess: () => closeAddModal(),
    });
};

const removePermission = (permissionId) => {
    router.delete(route('system_management.system_modules.permissions.destroy', [props.system_module.slug, permissionId]), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="system_module.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('system_management.system_modules.index')"
            :title="system_module.name"
            :icon-bg="iconBg"
        >
            <template #icon><GlobalOutlined /></template>
            <template #subtitle>
                <Space :size="6" class="show-page__meta">
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="system_module.is_active ? 'success' : 'default'" :bordered="false">
                        {{ system_module.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <span class="page-header__id">ID #{{ system_module.id }}</span>
                    <span v-if="lastUpdatedRel" class="page-header__rel">
                        · {{ $t('global.updated_at') }} {{ lastUpdatedRel }}
                    </span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="system_modules"
                    :slug="system_module.slug"
                    :id="system_module.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('system_modules.edit')"
                    :can-delete="can('system_modules.delete')"
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
                    <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(system_module.deleted_at) }}</div>
                    <div v-if="system_module.deleter">
                        <strong>{{ $t('global.deleted_by') }}:</strong> {{ system_module.deleter.name }} ({{ system_module.deleter.email }})
                    </div>
                    <div v-if="system_module.deleted_description" class="deleted-reason">
                        <strong>{{ $t('global.delete_description') }}:</strong> {{ system_module.deleted_description }}
                    </div>
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="system_modules" />
            </template>
        </Alert>

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="system_module"
        :activity="activity"
    >
            <template #general>
                <Card :title="$t('global.general_info')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ system_module.id }}</DescriptionsItem>
                        <DescriptionsItem label="Slug"><code>{{ system_module.slug }}</code></DescriptionsItem>
                        <DescriptionsItem :label="$t('system_modules.name')">{{ system_module.name }}</DescriptionsItem>
                        <DescriptionsItem label="permission_key">
                            <Space :size="6">
                                <KeyOutlined />
                                <code>{{ system_module.permission_key }}</code>
                            </Space>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('system_modules.is_active')">
                            <Tag :color="system_module.is_active ? 'success' : 'default'" :bordered="false">
                                {{ system_module.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>

                <Card :bodyStyle="{ padding: 16 }" class="info-card">
                    <template #title>
                        <span class="activity-card__title">
                            <SafetyCertificateOutlined /> {{ $t('system_modules.generated_permissions_title') }}
                        </span>
                    </template>
                    <template #extra>
                        <Tooltip v-if="!isDeleted" :title="$t('system_modules.add_action_hint')">
                            <Button type="primary" size="small" @click="openAddModal">
                                <PlusOutlined /> {{ $t('system_modules.add_action') }}
                            </Button>
                        </Tooltip>
                    </template>

                    <Space wrap :size="[8, 8]">
                        <Tag
                            v-for="p in permissions"
                            :key="p.id"
                            :color="isCanonical(p.action) ? 'cyan' : 'gold'"
                            :bordered="false"
                            class="perm-tag"
                        >
                            <span class="perm-tag__name">{{ p.name }}</span>
                            <span class="perm-tag__kind">
                                · {{ isCanonical(p.action) ? $t('system_modules.canonical_action') : $t('system_modules.custom_action') }}
                            </span>
                            <Popconfirm
                                v-if="!isDeleted && !isCanonical(p.action)"
                                :title="$t('system_modules.delete_permission_confirm')"
                                :ok-text="$t('global.delete')"
                                :cancel-text="$t('global.cancel')"
                                :ok-button-props="{ danger: true }"
                                @confirm="removePermission(p.id)"
                            >
                                <button
                                    type="button"
                                    class="perm-tag__close"
                                    :title="$t('global.delete')"
                                    @click.stop
                                >
                                    <CloseOutlined />
                                </button>
                            </Popconfirm>
                        </Tag>
                    </Space>
                    <p class="hint">{{ $t('system_modules.generated_permissions_hint') }}</p>
                </Card>

                <!-- Modal — agregar acción custom -->
                <Modal
                    v-model:open="addModalOpen"
                    :title="$t('system_modules.add_action_modal_title')"
                    :ok-text="$t('global.save_changes')"
                    :cancel-text="$t('global.cancel')"
                    :confirm-loading="addForm.processing"
                    :mask-closable="!addForm.processing"
                    @ok="submitAddAction"
                    @cancel="closeAddModal"
                >
                    <p class="hint" style="margin: 0 0 16px 0;">
                        {{ $t('system_modules.add_action_modal_hint', { prefix: system_module.permission_key }) }}
                    </p>
                    <FormItem
                        :label="$t('system_modules.action_name_label')"
                        :validate-status="addForm.errors.action ? 'error' : ''"
                        :help="addForm.errors.action"
                    >
                        <Input
                            v-model:value="addForm.action"
                            :placeholder="$t('system_modules.action_name_placeholder')"
                            size="large"
                            :maxlength="50"
                            autofocus
                            @keydown.enter.prevent="submitAddAction"
                        >
                            <template #addonBefore><code>{{ system_module.permission_key }}.</code></template>
                        </Input>
                    </FormItem>
                </Modal>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(system_module.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="system_module.creator" :label="$t('global.created_by')">
                            {{ system_module.creator.name }}
                            <span class="muted">({{ system_module.creator.email }})</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(system_module.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(system_module.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="system_module.deleter" :label="$t('global.deleted_by')">
                                {{ system_module.deleter.name }}
                                <span class="muted">({{ system_module.deleter.email }})</span>
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ system_module.deleted_description || '—' }}
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
.activity-card__title { display: inline-flex; align-items: center; gap: 6px; }
.hint { font-size: 0.8125rem; color: var(--color-text-muted); margin: 12px 0 0 0; line-height: 1.4; }

/* Permission tags — inline X para borrar */
.perm-tag {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    padding: 4px 10px;
    font-size: 0.8125rem;
}
.perm-tag__name { font-weight: 500; }
.perm-tag__kind { font-size: 0.6875rem; opacity: 0.7; text-transform: uppercase; letter-spacing: 0.04em; }
.perm-tag__close {
    background: transparent;
    border: 0;
    cursor: pointer;
    padding: 0;
    margin-left: 2px;
    width: 16px;
    height: 16px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    border-radius: 50%;
    color: inherit;
    opacity: 0.55;
    transition: opacity 0.12s ease, background 0.12s ease;
}
.perm-tag__close:hover { opacity: 1; background: rgba(0, 0, 0, 0.1); }
</style>
