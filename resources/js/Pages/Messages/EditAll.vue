<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Button, Card, Pagination, Alert } from 'ant-design-vue';
import { SaveOutlined, UndoOutlined, EditOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import MessagesEditAllTable from '@/Components/Messages/MessagesEditAllTable.vue';

import { useEditAllDraft } from '@/Composables/useEditAllDraft';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    messages: { type: Object, required: true },
    filters:  { type: Object, required: true },
});

const source = computed(() => props.messages.data);
const { draft, isDirty, dirtyCount, dirtyChanges, duplicateRows, discardAll } = useEditAllDraft({
    source,
    editableFields: ['subject', 'is_active'],
    uniqueField:    'subject',
});

const submitting = ref(false);
const saveAll = () => {
    if (dirtyCount.value === 0 || duplicateRows.value.size > 0) return;
    submitting.value = true;
    router.post(
        route('communication.messages.edit_all.update'),
        { changes: dirtyChanges.value },
        {
            preserveScroll: true,
            onFinish: () => { submitting.value = false; },
        },
    );
};

const onPageChange = (page, pageSize) => {
    router.get(
        route('communication.messages.edit_all'),
        { ...props.filters, page, per_page: pageSize },
        { preserveScroll: true, replace: true },
    );
};
</script>

<template>
    <Head :title="$t('messages.edit_all_title')" />

    <div class="edit-all">
        <SectionHeader
            :back-href="route('communication.messages.index')"
            :title="$t('messages.edit_all_title')"
            :subtitle="$t('messages.edit_all_subtitle')"
        >
            <template #icon><EditOutlined /></template>
            <template #actions>
                <Button :disabled="dirtyCount === 0 || submitting" @click="discardAll">
                    <UndoOutlined /> {{ $t('messages.edit_all_discard') }}
                </Button>
                <Button
                    type="primary"
                    :loading="submitting"
                    :disabled="dirtyCount === 0 || duplicateRows.size > 0"
                    @click="saveAll"
                >
                    <SaveOutlined /> {{ $t('global.save_changes') }}
                </Button>
            </template>
        </SectionHeader>

        <Alert v-if="dirtyCount > 0" type="info" show-icon class="status-bar">
            <template #message>
                {{ $t('messages.edit_all_changes', { count: dirtyCount }) }}
            </template>
        </Alert>

        <Alert
            v-if="duplicateRows.size > 0"
            type="error"
            show-icon
            :message="$t('messages.subject_unique')"
            class="status-bar"
        />

        <Card :bodyStyle="{ padding: 0 }" class="edit-table-card">
            <MessagesEditAllTable
                v-model:draft="draft"
                :is-dirty="isDirty"
                :duplicate-rows="duplicateRows"
            />
        </Card>

        <div v-if="messages.total > messages.per_page" class="pagination">
            <Pagination
                :current="messages.current_page"
                :pageSize="messages.per_page"
                :total="messages.total"
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
