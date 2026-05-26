<script setup>
import { computed } from 'vue';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import {
    Card, Button, Input, Alert, Form, FormItem, Space,
} from 'ant-design-vue';
import { DeleteOutlined, ArrowLeftOutlined } from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import { useI18n } from '@/Plugins/i18n';

defineOptions({ layout: AppLayout });

const { t } = useI18n();

const props = defineProps({
    message: { type: Object, required: true },
});

const form = useForm({
    subject_confirmation: '',
    deleted_description:  '',
});

const canConfirm = computed(() =>
    form.subject_confirmation === props.message.subject &&
    form.deleted_description.trim().length >= 3
);

const submit = () => {
    router.delete(route('communication.messages.deleteSave', props.message.slug), {
        data: form.data(),
        onError: () => {},
    });
};
</script>

<template>
    <Head :title="t('messages.delete_title')" />

    <div class="delete-page">
        <Card>
            <div class="page-header">
                <Link :href="route('communication.messages.show', message.slug)">
                    <Button type="text"><template #icon><ArrowLeftOutlined /></template></Button>
                </Link>
                <h1>{{ t('messages.delete_title') }}</h1>
            </div>

            <Alert type="warning" show-icon :message="t('messages.delete_warning')" style="margin-bottom: 14px" />

            <Form layout="vertical">
                <FormItem label="">
                    <div style="margin-bottom:8px"><strong>{{ message.subject }}</strong></div>
                </FormItem>

                <FormItem
                    :label="t('messages.delete_subject_label')"
                    :validate-status="form.errors.subject_confirmation ? 'error' : ''"
                    :help="form.errors.subject_confirmation"
                >
                    <Input v-model:value="form.subject_confirmation" />
                </FormItem>

                <FormItem
                    :label="t('messages.delete_reason_label')"
                    :validate-status="form.errors.deleted_description ? 'error' : ''"
                    :help="form.errors.deleted_description"
                >
                    <Input.TextArea v-model:value="form.deleted_description" :rows="3" :maxlength="1000" show-count />
                </FormItem>

                <Space>
                    <Button danger :disabled="!canConfirm" :loading="form.processing" @click="submit">
                        <template #icon><DeleteOutlined /></template>
                        {{ t('global.delete') }}
                    </Button>
                    <Link :href="route('communication.messages.show', message.slug)">
                        <Button type="text">{{ t('global.cancel') }}</Button>
                    </Link>
                </Space>
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.delete-page { padding: 16px; max-width: 720px; margin: 0 auto; }
.page-header { display:flex; align-items:center; gap:10px; margin-bottom:16px; }
.page-header h1 { font-size:1.15rem; margin:0; }
</style>
