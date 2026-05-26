<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Button, Card, Pagination, Alert } from 'ant-design-vue';
import { SaveOutlined, UndoOutlined, EditOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import TenantsEditAllTable from '@/Components/Tenants/TenantsEditAllTable.vue';

import { useEditAllDraft } from '@/Composables/useEditAllDraft';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineOptions({ layout: AppLayout });

/**
 * Edit-All: patrón SAP "smart table en modo edición masiva". Mantenemos
 * snapshot `original` + `draft` mutable (vía composable). Si el usuario
 * navega/recarga, los drafts se pierden — patrón SAP.
 */
const props = defineProps({
    tenants:     { type: Object, required: true },
    filters:     { type: Object, required: true },
    planOptions: { type: Array, default: () => [] },
});

const source = computed(() => props.tenants.data);
// El plan no es columna del tenant — se gestiona por suscripciones, no por
// edit-all batch. Edit-all solo toca name + is_active.
const { draft, isDirty, dirtyCount, dirtyChanges, duplicateRows, discardAll } = useEditAllDraft({
    source,
    editableFields: ['name', 'is_active'],
    uniqueField:    'name',
});

const submitting = ref(false);
const saveAll = () => {
    if (dirtyCount.value === 0 || duplicateRows.value.size > 0) return;
    submitting.value = true;
    router.post(
        route('system_management.tenants.edit_all.update'),
        { changes: dirtyChanges.value },
        {
            preserveScroll: true,
            onFinish: () => { submitting.value = false; },
        },
    );
};

const onPageChange = (page, pageSize) => {
    router.get(
        route('system_management.tenants.edit_all'),
        { ...props.filters, page, per_page: pageSize },
        { preserveScroll: true, replace: true },
    );
};
</script>

<template>
    <Head :title="$t('tenants.edit_all_title')" />

    <div class="edit-all">
        <SectionHeader
            :back-href="route('system_management.tenants.index')"
            :title="$t('global.edit_all') + ' — ' + $t('tenants.plural')"
            :subtitle="$t('tenants.edit_all_subtitle')"
        >
            <template #icon><EditOutlined /></template>
            <template #actions>
                <Button :disabled="dirtyCount === 0 || submitting" @click="discardAll">
                    <UndoOutlined /> {{ $t('tenants.edit_all_discard') }}
                </Button>
                <Button
                    type="primary"
                    :loading="submitting"
                    :disabled="dirtyCount === 0 || duplicateRows.size > 0"
                    @click="saveAll"
                >
                    <SaveOutlined /> {{ $t('tenants.edit_all_save_all') }}
                </Button>
            </template>
        </SectionHeader>

        <Alert v-if="dirtyCount > 0" type="info" show-icon class="status-bar">
            <template #message>
                {{ $t('tenants.edit_all_changes', { count: dirtyCount }) }}
            </template>
        </Alert>

        <Alert
            v-if="duplicateRows.size > 0"
            type="error"
            show-icon
            :message="$t('tenants.name_unique')"
            class="status-bar"
        />

        <Card :bodyStyle="{ padding: 0 }" class="edit-table-card">
            <TenantsEditAllTable
                v-model:draft="draft"
                :is-dirty="isDirty"
                :duplicate-rows="duplicateRows"
            />
        </Card>

        <div v-if="tenants.total > tenants.per_page" class="pagination">
            <Pagination
                :current="tenants.current_page"
                :pageSize="tenants.per_page"
                :total="tenants.total"
                :pageSizeOptions="['10', '25', '50', '100']"
                show-size-changer
                @change="onPageChange"
                @show-size-change="onPageChange"
            />
        </div>
    </div>
</template>

<style scoped>
.status-bar { margin-bottom: 12px; }
.edit-table-card { border-radius: 6px; }
.pagination {
    display: flex;
    justify-content: center;
    margin-top: 16px;
}
</style>
