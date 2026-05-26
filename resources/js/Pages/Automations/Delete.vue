<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Card, Form, FormItem, Input, Alert, Tag } from 'ant-design-vue';
import { DeleteOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import DeleteFooter from '@/Components/Common/DeleteFooter.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    automation: { type: Object, required: true },
});

const form = useForm({ deleted_description: '' });

const submit = () => {
    form.delete(route('automation_management.automations.deleteSave', props.automation.id), {
        preserveScroll: true,
    });
};
</script>

<template>
    <Head :title="$t('global.delete') + ' — ' + $t('automations.singular')" />

    <div>
        <SectionHeader
            :back-href="route('automation_management.automations.index')"
            :title="$t('global.delete') + ' ' + $t('automations.record')"
            :subtitle="automation.name"
            icon-bg="var(--color-danger)"
        >
            <template #icon><DeleteOutlined /></template>
        </SectionHeader>

        <Card :bodyStyle="{ padding: '24px 28px' }">
            <Alert type="warning" show-icon class="mb-4">
                <template #message>
                    {{ $t('global.delete') + ' "' + automation.name + '"' }}
                </template>
                <template #description>{{ $t('global.delete_reason_hint') }}</template>
            </Alert>

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
                    :cancel-href="route('automation_management.automations.index')"
                    :processing="form.processing"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.mb-4 { margin-bottom: 16px; }
</style>
