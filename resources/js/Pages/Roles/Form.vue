<script setup>
import { computed, ref } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import {
    Card, Form, FormItem, Input, Button, Alert, Select, Row, Col,
    Collapse, CollapsePanel, Checkbox, Tag, Space,
} from 'ant-design-vue';
import { TeamOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';

defineOptions({ layout: AppLayout });

const props = defineProps({
    role:                  { type: Object, default: null },
    assignablePermissions: { type: Array, default: () => [] },
    tenantOptions:         { type: Array, default: () => [] },
});

const isEdit = computed(() => !!props.role);

const form = useForm({
    name:        props.role?.name        ?? '',
    description: props.role?.description ?? '',
    tenant_id:   props.role?.tenant_id   ?? null,
    permissions: props.role?.permission_ids ?? [],
});

// Agrupar permisos por module (patients, doctors, users, roles…).
const grouped = computed(() => {
    const map = {};
    for (const p of props.assignablePermissions) {
        const mod = p.module ?? 'other';
        if (!map[mod]) map[mod] = [];
        map[mod].push(p);
    }
    return Object.entries(map)
        .sort(([a], [b]) => a.localeCompare(b))
        .map(([module, perms]) => ({ module, perms }));
});

const toggleModule = (perms, select) => {
    const ids = perms.map(p => p.id);
    if (select) {
        form.permissions = [...new Set([...form.permissions, ...ids])];
    } else {
        form.permissions = form.permissions.filter(id => !ids.includes(id));
    }
};

const moduleSelectedCount = (perms) =>
    perms.filter(p => form.permissions.includes(p.id)).length;

const submit = () => {
    if (isEdit.value) {
        form.put(route('user_management.roles.update', props.role.slug));
    } else {
        form.post(route('user_management.roles.store'));
    }
};
</script>

<template>
    <Head :title="isEdit ? $t('roles.edit_title') : $t('roles.new')" />
    <div class="form-page">
        <SectionHeader
            :back-href="route('user_management.roles.index')"
            :title="isEdit ? $t('roles.edit_title') : $t('roles.new')"
            :subtitle="isEdit ? role.name : $t('roles.form_create_hint')"
        >
            <template #icon><TeamOutlined /></template>
        </SectionHeader>

        <Card :bodyStyle="{ padding: 24 }">
            <Form layout="vertical" @submit.prevent="submit">
                <Alert
                    v-if="form.hasErrors && Object.keys(form.errors).length > 0"
                    type="error" show-icon
                    :message="$t('global.fix_marked_fields')"
                    class="mb-4"
                />

                <Row :gutter="[20, 0]">
                    <Col :xs="24" :md="tenantOptions.length ? 8 : 16">
                        <FormItem
                            required
                            :validate-status="form.errors.name ? 'error' : ''"
                            :help="form.errors.name"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('roles.name')" :help="$t('roles.name_hint')" />
                            </template>
                            <Input v-model:value="form.name" size="large" :maxlength="120" />
                        </FormItem>
                    </Col>
                    <Col v-if="tenantOptions.length" :xs="24" :md="8">
                        <FormItem
                            :validate-status="form.errors.tenant_id ? 'error' : ''"
                            :help="form.errors.tenant_id ?? $t('roles.tenant_hint')"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('roles.tenant')" :help="$t('roles.tenant_hint')" />
                            </template>
                            <Select
                                v-model:value="form.tenant_id"
                                :options="tenantOptions"
                                :placeholder="$t('roles.tenant_placeholder')"
                                allow-clear
                                size="large"
                                :disabled="isEdit"
                            />
                        </FormItem>
                    </Col>
                    <Col :xs="24" :md="tenantOptions.length ? 8 : 8">
                        <FormItem
                            required
                            :validate-status="form.errors.description ? 'error' : ''"
                            :help="form.errors.description"
                        >
                            <template #label>
                                <LabelWithHelp :label="$t('roles.description')" :help="$t('roles.description_hint')" />
                            </template>
                            <Input v-model:value="form.description" size="large" :maxlength="255" />
                        </FormItem>
                    </Col>
                </Row>

                <div class="permissions-block">
                    <h3 class="permissions-title">
                        {{ $t('roles.permissions') }}
                        <Tag :bordered="false" color="cyan">{{ form.permissions.length }}</Tag>
                    </h3>
                    <p class="hint">{{ $t('roles.permissions_hint') }}</p>

                    <Collapse v-if="grouped.length" ghost>
                        <CollapsePanel v-for="g in grouped" :key="g.module">
                            <template #header>
                                <Space>
                                    <strong>{{ g.module }}</strong>
                                    <Tag :color="moduleSelectedCount(g.perms) > 0 ? 'cyan' : 'default'" :bordered="false">
                                        {{ moduleSelectedCount(g.perms) }} / {{ g.perms.length }}
                                    </Tag>
                                </Space>
                            </template>
                            <template #extra>
                                <Space @click.stop>
                                    <Button size="small" @click="toggleModule(g.perms, true)">Todos</Button>
                                    <Button size="small" @click="toggleModule(g.perms, false)">Ninguno</Button>
                                </Space>
                            </template>
                            <div class="perm-grid">
                                <Checkbox
                                    v-for="p in g.perms"
                                    :key="p.id"
                                    :checked="form.permissions.includes(p.id)"
                                    @change="(e) => {
                                        if (e.target.checked) form.permissions = [...form.permissions, p.id];
                                        else form.permissions = form.permissions.filter(id => id !== p.id);
                                    }"
                                >
                                    <code>{{ p.action }}</code>
                                </Checkbox>
                            </div>
                        </CollapsePanel>
                    </Collapse>
                    <Alert v-else type="info" :message="$t('roles.no_permissions_available')" show-icon />
                </div>

                <FormFooter
                    :cancel-href="route('user_management.roles.index')"
                    :is-edit="isEdit"
                    :processing="form.processing"
                    create-label-key="roles.new"
                />
            </Form>
        </Card>
    </div>
</template>

<style scoped>
.permissions-block { margin-top: 18px; padding-top: 18px; border-top: 1px solid var(--color-border); }
.permissions-title { font-size: 1rem; font-weight: 600; margin: 0 0 4px 0; }
.hint { font-size: 0.8125rem; color: var(--color-text-muted); margin: 0 0 14px 0; }
.perm-grid { display: grid; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); gap: 8px; padding: 8px 0; }
.mb-4 { margin-bottom: 16px; }
</style>
