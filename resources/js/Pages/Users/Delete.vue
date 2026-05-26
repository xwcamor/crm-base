<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Card, Form, FormItem, Input, Alert, Tag } from 'ant-design-vue';
import { DeleteOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import DeleteFooter from '@/Components/Common/DeleteFooter.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    user: { type: Object, required: true },
});

const form = useForm({
    deleted_description: '',
});

const submit = () => {
    form.delete(route('user_management.users.deleteSave', props.user.slug), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('users.delete_title')" />

    <div class="delete-page">
        <SectionHeader
            :back-href="route('user_management.users.index')"
            :title="$t('users.delete_title')"
            :subtitle="$t('users.delete_subtitle')"
            icon-bg="var(--color-danger)"
        >
            <template #icon><DeleteOutlined /></template>
        </SectionHeader>

        <Card class="delete-card" :bodyStyle="{ padding: '24px 28px' }">
            <Alert type="warning" show-icon class="mb-4">
                <template #message>
                    {{ $t('users.delete_about', { name: user.name }) }}
                </template>
                <template #description>
                    {{ $t('global.delete_reason_hint') }}
                </template>
            </Alert>

            <div class="user-summary">
                <div class="user-summary__row">
                    <span class="user-summary__label">{{ $t('users.id') }}</span>
                    <span class="user-summary__value">{{ user.id }}</span>
                </div>
                <div class="user-summary__row">
                    <span class="user-summary__label">{{ $t('users.name') }}</span>
                    <span class="user-summary__value">{{ user.name }}</span>
                </div>
                <div class="user-summary__row">
                    <span class="user-summary__label">{{ $t('users.email') }}</span>
                    <span class="user-summary__value">{{ user.email }}</span>
                </div>
                <div class="user-summary__row">
                    <span class="user-summary__label">{{ $t('users.is_active') }}</span>
                    <span class="user-summary__value">
                        <Tag :color="user.is_active ? 'success' : 'error'" :bordered="false">
                            {{ user.is_active ? $t('global.active') : $t('global.inactive') }}
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
                    :cancel-href="route('user_management.users.index')"
                    :processing="form.processing"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.delete-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }

.delete-card { border-radius: 6px; }

.user-summary {
    display: flex; flex-direction: column; gap: 6px;
    padding: 14px 16px;
    background: #F8FAFC;
    border: 1px solid #E5E5E5;
    border-radius: 6px;
    margin-bottom: 20px;
}
.user-summary__row {
    display: flex; justify-content: space-between; align-items: flex-start;
    gap: 12px; font-size: 0.875rem;
    min-width: 0;
}
.user-summary__label { color: #6A6D70; font-weight: 500; flex-shrink: 0; }
.user-summary__value {
    color: #32363A; text-align: right;
    word-break: break-word; overflow-wrap: anywhere;
    min-width: 0; flex: 1 1 auto;
}

.mb-4 { margin-bottom: 16px; }
</style>

<style>
html[data-theme="dark"] .user-summary {
    background: #2c3034; border-color: #3f4448;
}
html[data-theme="dark"] .user-summary__label { color: #a8aaae; }
html[data-theme="dark"] .user-summary__value { color: #e5e6e7; }
</style>
