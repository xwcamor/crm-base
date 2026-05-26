<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Alert, Tag,
} from 'ant-design-vue';
import { DeleteOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import DeleteFooter from '@/Components/Common/DeleteFooter.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    variant:    { type: Object, required: true },
    dependents: { type: Object, default: () => ({}) },
});

const hasDependents = computed(() => Object.keys(props.dependents).length > 0);

const form = useForm({
    deleted_description: '',
});

const submit = () => {
    form.delete(route('business_management.product_variants.deleteSave', props.variant.slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('global.delete') + ' — ' + $t('product_variants.singular')" />

    <div class="delete-page">
        <SectionHeader
            :back-href="route('business_management.product_variants.index')"
            :title="$t('global.delete') + ' ' + $t('product_variants.record')"
            :subtitle="$t('product_variants.delete_hint')"
            icon-bg="var(--color-danger)"
        >
            <template #icon><DeleteOutlined /></template>
        </SectionHeader>

        <Card class="delete-card" :bodyStyle="{ padding: '24px 28px' }">
            <Alert type="warning" show-icon class="mb-4">
                <template #message>
                    {{ $t('product_variants.delete_about', { name: variant.name }) }}
                </template>
                <template #description>
                    {{ $t('global.delete_reason_hint') }}
                </template>
            </Alert>

            <Alert v-if="hasDependents" type="error" show-icon class="mb-4">
                <template #message>
                    {{ $t('global.has_dependents_warning') }}
                </template>
                <template #description>
                    <ul class="dependents-list">
                        <li v-for="(d, key) in dependents" :key="key">
                            {{ $t('global.has_dependents_detail', { count: d.count, label: d.label }) }}
                        </li>
                    </ul>
                    <p class="dependents-note">{{ $t('global.has_dependents_proceed') }}</p>
                </template>
            </Alert>

            <div class="record-summary">
                <div class="record-summary__row">
                    <span class="record-summary__label">ID</span>
                    <span class="record-summary__value">{{ variant.id }}</span>
                </div>
                <div class="record-summary__row">
                    <span class="record-summary__label">{{ $t('product_variants.sku') }}</span>
                    <span class="record-summary__value"><code>{{ variant.sku }}</code></span>
                </div>
                <div class="record-summary__row">
                    <span class="record-summary__label">{{ $t('product_variants.name') }}</span>
                    <span class="record-summary__value">{{ variant.name }}</span>
                </div>
                <div v-if="variant.product" class="record-summary__row">
                    <span class="record-summary__label">{{ $t('product_variants.product') }}</span>
                    <span class="record-summary__value">{{ variant.product.name }}</span>
                </div>
                <div class="record-summary__row">
                    <span class="record-summary__label">{{ $t('product_variants.is_active') }}</span>
                    <span class="record-summary__value">
                        <Tag :color="variant.is_active ? 'success' : 'error'" :bordered="false">
                            {{ variant.is_active ? $t('global.active') : $t('global.inactive') }}
                        </Tag>
                    </span>
                </div>
            </div>

            <Form layout="vertical" @submit.prevent="submit">
                <FormItem
                    :label="$t('global.delete_description')"
                    required
                    :validate-status="form.errors.deleted_description ? 'error' : ''"
                    :help="form.errors.deleted_description"
                >
                    <Input.TextArea
                        v-model:value="form.deleted_description"
                        :rows="4"
                        :placeholder="$t('global.delete_reason_placeholder')"
                        :maxlength="1000"
                        showCount
                        autofocus
                    />
                </FormItem>

                <DeleteFooter
                    :cancel-href="route('business_management.product_variants.index')"
                    :processing="form.processing"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.delete-card { border-radius: 6px; }

.record-summary {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border-strong);
    border-radius: 6px;
    margin-bottom: 20px;
}
.record-summary__row {
    display: flex;
    justify-content: space-between;
    align-items: flex-start;
    gap: 12px;
    font-size: 0.875rem;
    min-width: 0;
}
.record-summary__label {
    color: var(--color-text-muted);
    font-weight: 500;
    flex-shrink: 0;
}
.record-summary__value {
    color: var(--color-text);
    text-align: right;
    word-break: break-word;
    overflow-wrap: anywhere;
    min-width: 0;
    flex: 1 1 auto;
}

.mb-4 { margin-bottom: 16px; }

.dependents-list {
    margin: 4px 0 8px 0;
    padding-left: 20px;
    font-size: 0.875rem;
}
.dependents-list li { line-height: 1.5; }
.dependents-note {
    margin: 4px 0 0 0;
    font-size: 0.78rem;
    color: var(--color-text-muted);
    font-style: italic;
}
</style>
