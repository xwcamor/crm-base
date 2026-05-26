<script setup>
import { ref, computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Switch, Button, Space, Alert, Avatar, Upload, Select,
} from 'ant-design-vue';
import {
    UserOutlined, MailOutlined, LockOutlined,
    EyeOutlined, EyeInvisibleOutlined, UploadOutlined, TeamOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

defineOptions({ layout: AppLayout });

const props = defineProps({
    user:           { type: Object, default: null },
    roleOptions:    { type: Array,  default: () => [] },
    tenantOptions:  { type: Array,  default: () => [] },
    countryOptions: { type: Array,  default: () => [] },
    localeOptions:  { type: Array,  default: () => [] },
});

const isEdit = computed(() => !!props.user);

// useForm — Inertia handles multipart/form-data automatically when File present.
const form = useForm({
    name:       props.user?.name       ?? '',
    email:      props.user?.email      ?? '',
    password:   '',
    is_active:  props.user?.is_active  ?? true,
    role_id:    props.user?.role_id    ?? null,
    tenant_id:  props.user?.tenant_id  ?? null,
    country_id: props.user?.country_id ?? null,
    locale_id:  props.user?.locale_id  ?? null,
    photo:      null,
    _method:    isEdit.value ? 'put' : 'post',
});

// Photo preview (from upload OR existing photo_url).
const previewUrl = ref(props.user?.photo_url ?? null);
const onPhotoChange = (file) => {
    form.photo = file;
    previewUrl.value = file ? URL.createObjectURL(file) : (props.user?.photo_url ?? null);
};

const showPassword = ref(false);

const submit = () => {
    if (isEdit.value) {
        // Inertia uses POST + _method=put for multipart edits with files.
        form.post(route('user_management.users.update', props.user.slug), {
            forceFormData: true,
        });
    } else {
        form.post(route('user_management.users.store'), {
            forceFormData: true,
        });
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('users.edit_title') : $t('users.create_title')" />

    <div class="form-page">
        <SectionHeader
            :back-href="route('user_management.users.index')"
            :title="isEdit ? $t('users.edit_title') : $t('users.create_title')"
            :subtitle="isEdit ? user.name : $t('users.create_subtitle')"
        >
            <template #icon><UserOutlined /></template>
        </SectionHeader>

        <Card class="form-card" :bodyStyle="{ padding: '24px 28px' }">
            <Form layout="vertical" @submit.prevent="submit">

                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error"
                    show-icon
                    :message="$t('global.form_has_errors')"
                    class="mb-4"
                />

                <!-- Foto -->
                <div class="photo-section">
                    <Avatar :src="previewUrl" :size="96">
                        <template v-if="!previewUrl" #icon><UserOutlined /></template>
                    </Avatar>
                    <div class="photo-controls">
                        <input
                            ref="fileInput"
                            type="file"
                            accept="image/jpeg,image/png,image/gif,image/jpg"
                            style="display: none"
                            @change="(e) => onPhotoChange(e.target.files[0])"
                        />
                        <Button @click="$refs.fileInput.click()">
                            <UploadOutlined /> {{ previewUrl ? $t('global.change_photo') : $t('global.upload_photo') }}
                        </Button>
                        <Button v-if="form.photo" @click="onPhotoChange(null)" type="text" danger>
                            {{ $t('global.remove') }}
                        </Button>
                        <p class="photo-hint">{{ $t('global.photo_hint') }}</p>
                    </div>
                </div>
                <div v-if="form.errors.photo" class="field-error">{{ form.errors.photo }}</div>

                <!-- Nombre -->
                <FormItem
                    required
                    :validate-status="form.errors.name ? 'error' : ''"
                    :help="form.errors.name"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('users.name')" :help="$t('users.name_hint')" />
                    </template>
                    <Input
                        v-model:value="form.name"
                        size="large"
                        :maxlength="255"
                        showCount
                        autofocus
                    >
                        <template #prefix><UserOutlined /></template>
                    </Input>
                </FormItem>

                <!-- Email -->
                <FormItem
                    required
                    :validate-status="form.errors.email ? 'error' : ''"
                    :help="form.errors.email"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('users.email')" :help="$t('users.email_hint')" />
                    </template>
                    <Input
                        v-model:value="form.email"
                        placeholder="user@example.com"
                        size="large"
                        type="email"
                    >
                        <template #prefix><MailOutlined /></template>
                    </Input>
                </FormItem>

                <!-- Password -->
                <FormItem
                    :required="!isEdit"
                    :validate-status="form.errors.password ? 'error' : ''"
                    :help="form.errors.password || (isEdit ? $t('global.leave_blank_to_keep') : $t('global.min_chars', { n: 6 }))"
                >
                    <template #label>
                        <LabelWithHelp
                            :label="isEdit ? $t('users.password') + ' (opcional)' : $t('users.password')"
                            :help="$t('users.password_hint')"
                        />
                    </template>
                    <Input
                        v-model:value="form.password"
                        :placeholder="isEdit ? '••••••••' : 'Mínimo 6 caracteres'"
                        size="large"
                        :type="showPassword ? 'text' : 'password'"
                    >
                        <template #prefix><LockOutlined /></template>
                        <template #suffix>
                            <button
                                type="button"
                                class="pass-toggle"
                                @click="showPassword = !showPassword"
                            >
                                <EyeOutlined v-if="!showPassword" />
                                <EyeInvisibleOutlined v-else />
                            </button>
                        </template>
                    </Input>
                </FormItem>

                <!-- Perfil (Rol) -->
                <FormItem
                    v-if="roleOptions.length"
                    required
                    :validate-status="form.errors.role_id ? 'error' : ''"
                    :help="form.errors.role_id"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('users.role')" :help="$t('users.role_hint')" />
                    </template>
                    <Select
                        v-model:value="form.role_id"
                        :options="roleOptions"
                        :placeholder="$t('users.role')"
                        size="large"
                        allow-clear
                    >
                        <template #prefix><TeamOutlined /></template>
                    </Select>
                </FormItem>

                <!-- Tenant (super only) -->
                <FormItem
                    v-if="tenantOptions.length"
                    :validate-status="form.errors.tenant_id ? 'error' : ''"
                    :help="form.errors.tenant_id"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('users.tenant')" :help="$t('users.tenant_hint')" />
                    </template>
                    <Select
                        v-model:value="form.tenant_id"
                        :options="tenantOptions"
                        :placeholder="$t('users.tenant')"
                        size="large"
                        allow-clear
                    />
                </FormItem>

                <!-- País -->
                <FormItem
                    v-if="countryOptions.length"
                    :validate-status="form.errors.country_id ? 'error' : ''"
                    :help="form.errors.country_id"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('countries.singular')" :help="$t('users.country_hint')" />
                    </template>
                    <Select
                        v-model:value="form.country_id"
                        :options="countryOptions"
                        :placeholder="$t('countries.singular')"
                        size="large"
                        allow-clear
                        show-search
                        :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())"
                    />
                </FormItem>

                <FormItem
                    v-if="localeOptions.length"
                    :validate-status="form.errors.locale_id ? 'error' : ''"
                    :help="form.errors.locale_id"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('locales.singular')" :help="$t('users.locale_hint')" />
                    </template>
                    <Select
                        v-model:value="form.locale_id"
                        :options="localeOptions"
                        :placeholder="$t('locales.singular')"
                        size="large"
                        allow-clear
                    />
                </FormItem>

                <!-- Estado (solo edición) -->
                <FormItem
                    v-if="isEdit"
                    :validate-status="form.errors.is_active ? 'error' : ''"
                    :help="form.errors.is_active"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('users.is_active')" :help="$t('users.is_active_hint')" />
                    </template>
                    <Space>
                        <Switch v-model:checked="form.is_active" />
                        <span class="state-label">
                            {{ form.is_active ? $t('global.active') : $t('global.inactive') }}
                        </span>
                    </Space>
                </FormItem>

                <!-- Footer -->
                <FormFooter
                    :cancel-href="route('user_management.users.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="users.new"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.form-page { /* fullscreen — sin max-width, ocupa todo el ancho del content */ }

.form-card { border-radius: 6px; }

.photo-section {
    display: flex; align-items: center; gap: 18px;
    margin-bottom: 24px; padding-bottom: 18px;
    border-bottom: 1px solid #E5E5E5;
}
.photo-controls { display: flex; flex-direction: column; gap: 8px; align-items: flex-start; }
.photo-hint { font-size: 0.75rem; color: #6A6D70; margin: 0; }
.field-error { color: #dc2626; font-size: 0.8rem; font-weight: 500; margin-bottom: 12px; }

.state-label { font-size: 0.875rem; color: #32363A; font-weight: 500; }

.pass-toggle {
    background: transparent; border: 0; cursor: pointer; padding: 0;
    color: #6A6D70; display: flex; align-items: center;
}
.pass-toggle:hover { color: #0A6ED1; }

.mb-4 { margin-bottom: 16px; }

@media (max-width: 768px) {
    .photo-section { flex-direction: column; align-items: flex-start; gap: 12px; }
}
</style>

<style>
html[data-theme="dark"] .photo-section  { border-bottom-color: #3f4448; }
html[data-theme="dark"] .state-label    { color: #e5e6e7; }
</style>
