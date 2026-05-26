<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { Card, Form, FormItem, Input, Alert, Tag, Space, Descriptions, DescriptionsItem } from 'ant-design-vue';
import { DeleteOutlined, TeamOutlined, WarningOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import DeleteFooter from '@/Components/Common/DeleteFooter.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    role: { type: Object, required: true },
});

const form = useForm({
    deleted_description: '',
    _method: 'delete',
});

const submit = () => {
    form.post(route('user_management.roles.deleteSave', props.role.slug));
};
</script>

<template>
    <Head :title="$t('global.delete') + ' — ' + role.name" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('user_management.roles.index')"
            :title="$t('roles.delete_title') || 'Eliminar perfil'"
            :subtitle="role.name"
            icon-bg="var(--color-danger)"
        >
            <template #icon><DeleteOutlined /></template>
        </SectionHeader>

        <Alert
            type="warning"
            show-icon
            class="mb-3"
            :message="$t('roles.delete_warning_title') || '¿Eliminar este perfil?'"
            :description="$t('roles.delete_warning_desc') || 'El perfil pasa a la papelera. Puedes restaurarlo en los próximos 30 días desde Trash. Los usuarios que tengan este rol asignado lo pierden.'"
        />

        <Alert
            v-if="role.users_count > 0"
            type="error"
            show-icon
            class="mb-3"
        >
            <template #message>
                <Space>
                    <WarningOutlined />
                    <strong>{{ $t('roles.delete_blocked_title') || 'No se puede eliminar' }}</strong>
                </Space>
            </template>
            <template #description>
                {{ $t('roles.delete_blocked_users_count', { count: role.users_count }) ||
                   `Este perfil está asignado a ${role.users_count} usuario(s). Reasignalos a otro perfil primero.` }}
            </template>
        </Alert>

        <Card :bodyStyle="{ padding: '20px 24px' }">
            <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }" class="mb-3">
                <DescriptionsItem :label="$t('roles.name')">
                    <Space>
                        <TeamOutlined />
                        <strong>{{ role.name }}</strong>
                    </Space>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('roles.description')">
                    {{ role.description || '—' }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('roles.permissions_count')">
                    <Tag color="cyan" :bordered="false">{{ role.permissions_count }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('roles.users_count')">
                    <Tag :color="role.users_count > 0 ? 'red' : 'default'" :bordered="false">{{ role.users_count }}</Tag>
                </DescriptionsItem>
            </Descriptions>

            <Form layout="vertical" @submit.prevent="submit">
                <FormItem
                    :label="$t('global.delete_description')"
                    required
                    :validate-status="form.errors.deleted_description ? 'error' : ''"
                    :help="form.errors.deleted_description"
                >
                    <Input.TextArea
                        v-model:value="form.deleted_description"
                        :rows="3"
                        :placeholder="$t('global.delete_reason_placeholder')"
                        :maxlength="1000"
                        showCount
                        autofocus
                    />
                </FormItem>

                <DeleteFooter
                    :cancel-href="route('user_management.roles.index')"
                    :processing="form.processing"
                    :disabled="role.users_count > 0 || form.deleted_description.trim().length < 3"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
/* Full screen — usa todo el ancho del área de contenido, igual que Regions. */
.form-page { width: 100%; }
.mb-3 { margin-bottom: 16px; }

@media (max-width: 767px) {
    /* Override del labelStyle width:180px inline en mobile — auto-width
       y wrap para que strings largos no empujen la pagina a la derecha. */
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
