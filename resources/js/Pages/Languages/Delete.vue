<script setup>
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
    language: { type: Object, required: true },
    dependents: { type: Object, default: () => ({}) },
});

import { computed } from 'vue';
const hasDependents = computed(() => Object.keys(props.dependents).length > 0);

const form = useForm({
    deleted_description: '',
});

const submit = () => {
    form.delete(route('system_management.languages.deleteSave', props.language.slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('global.delete') + ' — ' + $t('languages.singular')" />

    <div class="delete-page">
        <SectionHeader
            :back-href="route('system_management.languages.index')"
            :title="$t('global.delete') + ' ' + $t('languages.record')"
            :subtitle="$t('languages.delete_hint')"
            icon-bg="var(--color-danger)"
        >
            <template #icon><DeleteOutlined /></template>
        </SectionHeader>

        <!-- Card -->
        <Card class="delete-card" :bodyStyle="{ padding: '24px 28px' }">
            <Alert
                type="warning"
                show-icon
                class="mb-4"
            >
                <template #message>
                    {{ $t('languages.delete_about', { name: language.name }) }}
                </template>
                <template #description>
                    {{ $t('global.delete_reason_hint') }}
                </template>
            </Alert>

            <!-- Dependency check warning — solo aparece si hay registros que
                 dependen de esta idioma (ej. locales.language_id). El backend
                 puebla `dependents` con los conteos. -->
            <Alert
                v-if="hasDependents"
                type="error"
                show-icon
                class="mb-4"
            >
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

            <!-- Resumen: orden id, slug, name, estado -->
            <div class="language-summary">
                <div class="language-summary__row">
                    <span class="language-summary__label">ID</span>
                    <span class="language-summary__value">{{ language.id }}</span>
                </div>
                <div class="language-summary__row">
                    <span class="language-summary__label">Slug</span>
                    <span class="language-summary__value"><code>{{ language.slug }}</code></span>
                </div>
                <div class="language-summary__row">
                    <span class="language-summary__label">{{ $t('languages.name') }}</span>
                    <span class="language-summary__value">{{ language.name }}</span>
                </div>
                <div class="language-summary__row">
                    <span class="language-summary__label">{{ $t('languages.iso_code') }}</span>
                    <span class="language-summary__value"><code>{{ language.iso_code }}</code></span>
                </div>
                <div class="language-summary__row">
                    <span class="language-summary__label">{{ $t('languages.is_active') }}</span>
                    <span class="language-summary__value">
                        <Tag :color="language.is_active ? 'success' : 'error'" :bordered="false">
                            {{ language.is_active ? $t('global.active') : $t('global.inactive') }}
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
                        :maxlength="500"
                        showCount
                        autofocus
                    />
                </FormItem>

                <DeleteFooter
                    :cancel-href="route('system_management.languages.index')"
                    :processing="form.processing"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.delete-card { border-radius: 6px; }

.language-summary {
    display: flex;
    flex-direction: column;
    gap: 6px;
    padding: 14px 16px;
    background: var(--color-surface-alt);
    border: 1px solid var(--color-border-strong);
    border-radius: 6px;
    margin-bottom: 20px;
}
.language-summary__row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 12px;
    font-size: 0.875rem;
}
.language-summary__label {
    color: var(--color-text-muted);
    font-weight: 500;
}
.language-summary__value {
    color: var(--color-text);
    text-align: right;
    word-break: break-word;
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
