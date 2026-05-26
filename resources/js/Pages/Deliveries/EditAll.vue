<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Button, Card, Pagination, Alert } from 'ant-design-vue';
import { SaveOutlined, UndoOutlined, EditOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import DeliveriesEditAllTable from '@/Components/Deliveries/DeliveriesEditAllTable.vue';

import { useEditAllDraft } from '@/Composables/useEditAllDraft';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    deliveries:    { type: Object, required: true },
    filters:       { type: Object, required: true },
    statusOptions: { type: Array,  default: () => [] },
});

const source = computed(() => props.deliveries.data);
const { draft, isDirty, dirtyCount, dirtyChanges, duplicateRows, discardAll } = useEditAllDraft({
    source,
    editableFields: ['reference', 'status', 'carrier'],
    uniqueField:    'reference',
});

const submitting = ref(false);
const saveAll = () => {
    if (dirtyCount.value === 0 || duplicateRows.value.size > 0) return;
    submitting.value = true;
    router.post(
        route('business_management.deliveries.edit_all.update'),
        { changes: dirtyChanges.value },
        {
            preserveScroll: true,
            onFinish: () => { submitting.value = false; },
        },
    );
};

const onPageChange = (page, pageSize) => {
    router.get(
        route('business_management.deliveries.edit_all'),
        { ...props.filters, page, per_page: pageSize },
        { preserveScroll: true, replace: true },
    );
};
</script>

<template>
    <Head :title="$t('deliveries.edit_all_title')" />

    <div class="edit-all">
        <SectionHeader
            :back-href="route('business_management.deliveries.index')"
            :title="$t('global.edit_all') + ' — ' + $t('deliveries.plural')"
            :subtitle="$t('deliveries.edit_all_subtitle')"
        >
            <template #icon><EditOutlined /></template>
            <template #actions>
                <Button :disabled="dirtyCount === 0 || submitting" @click="discardAll">
                    <UndoOutlined /> {{ $t('deliveries.edit_all_discard') }}
                </Button>
                <Button
                    type="primary"
                    :loading="submitting"
                    :disabled="dirtyCount === 0 || duplicateRows.size > 0"
                    @click="saveAll"
                >
                    <SaveOutlined /> {{ $t('deliveries.edit_all_save_all') }}
                </Button>
            </template>
        </SectionHeader>

        <Alert v-if="dirtyCount > 0" type="info" show-icon class="status-bar">
            <template #message>
                {{ $t('deliveries.edit_all_changes', { count: dirtyCount }) }}
            </template>
        </Alert>

        <Alert
            v-if="duplicateRows.size > 0"
            type="error"
            show-icon
            :message="$t('deliveries.reference_unique')"
            class="status-bar"
        />

        <Card :bodyStyle="{ padding: 0 }" class="edit-table-card">
            <DeliveriesEditAllTable
                v-model:draft="draft"
                :is-dirty="isDirty"
                :duplicate-rows="duplicateRows"
                :status-options="statusOptions"
            />
        </Card>

        <div v-if="deliveries.total > deliveries.per_page" class="pagination">
            <Pagination
                :current="deliveries.current_page"
                :pageSize="deliveries.per_page"
                :total="deliveries.total"
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
.pagination { display: flex; justify-content: center; margin-top: 16px; }
</style>
