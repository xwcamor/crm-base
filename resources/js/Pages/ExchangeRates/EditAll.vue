<script setup>
import { ref, computed } from 'vue';
import { Head, router } from '@inertiajs/vue3';
import { Button, Card, Pagination, Alert } from 'ant-design-vue';
import { SaveOutlined, UndoOutlined, EditOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import ExchangeRatesEditAllTable from '@/Components/ExchangeRates/ExchangeRatesEditAllTable.vue';

import { useEditAllDraft } from '@/Composables/useEditAllDraft';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    rates:   { type: Object, required: true },
    filters: { type: Object, required: true },
});

const source = computed(() => props.rates.data);
const { draft, isDirty, dirtyCount, dirtyChanges, duplicateRows, discardAll } = useEditAllDraft({
    source,
    editableFields: ['rate', 'is_active'],
    // No unique-in-batch check — la unicidad (tenant_id, base, quote, valid_at)
    // se cuida en el backend al guardar.
    uniqueField:    null,
});

const submitting = ref(false);
const saveAll = () => {
    if (dirtyCount.value === 0) return;
    submitting.value = true;
    router.post(
        route('business_management.exchange_rates.edit_all.update'),
        { changes: dirtyChanges.value },
        {
            preserveScroll: true,
            onFinish: () => { submitting.value = false; },
        },
    );
};

const onPageChange = (page, pageSize) => {
    router.get(
        route('business_management.exchange_rates.edit_all'),
        { ...props.filters, page, per_page: pageSize },
        { preserveScroll: true, replace: true },
    );
};
</script>

<template>
    <Head :title="$t('exchange_rates.edit_all_title')" />

    <div class="edit-all">
        <SectionHeader
            :back-href="route('business_management.exchange_rates.index')"
            :title="$t('global.edit_all') + ' — ' + $t('exchange_rates.plural')"
            :subtitle="$t('exchange_rates.edit_all_subtitle')"
        >
            <template #icon><EditOutlined /></template>
            <template #actions>
                <Button :disabled="dirtyCount === 0 || submitting" @click="discardAll">
                    <UndoOutlined /> {{ $t('exchange_rates.edit_all_discard') }}
                </Button>
                <Button
                    type="primary"
                    :loading="submitting"
                    :disabled="dirtyCount === 0"
                    @click="saveAll"
                >
                    <SaveOutlined /> {{ $t('exchange_rates.edit_all_save_all') }}
                </Button>
            </template>
        </SectionHeader>

        <Alert v-if="dirtyCount > 0" type="info" show-icon class="status-bar">
            <template #message>
                {{ $t('exchange_rates.edit_all_changes', { count: dirtyCount }) }}
            </template>
        </Alert>

        <Card :bodyStyle="{ padding: 0 }" class="edit-table-card">
            <ExchangeRatesEditAllTable
                v-model:draft="draft"
                :is-dirty="isDirty"
                :duplicate-rows="duplicateRows"
            />
        </Card>

        <div v-if="rates.total > rates.per_page" class="pagination">
            <Pagination
                :current="rates.current_page"
                :pageSize="rates.per_page"
                :total="rates.total"
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
