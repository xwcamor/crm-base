<script setup>
/** Drawer lateral: preview rapido del cliente sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { UserOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    contact:      { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('contacts.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="contact">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <UserOutlined />
                </div>
                <div>
                    <h2>{{ contact.name }}</h2>
                    <Tag :color="contact.is_active ? 'success' : 'error'" :bordered="false">
                        {{ contact.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4" size="small">
                <DescriptionsItem v-if="contact.primary_email" :label="$t('contacts.email')">
                    <a :href="'mailto:' + contact.primary_email">{{ contact.primary_email }}</a>
                </DescriptionsItem>
                <DescriptionsItem v-if="contact.primary_phone" :label="$t('contacts.phone')">
                    {{ contact.primary_phone }}
                </DescriptionsItem>
                <DescriptionsItem v-if="contact.job_title" :label="$t('contacts.position')">
                    {{ contact.job_title }}
                </DescriptionsItem>
                <DescriptionsItem v-if="contact.company" :label="$t('companies.singular')">
                    <Link :href="route('crm.companies.show', contact.company.slug ?? contact.company.id)">{{ contact.company.name }}</Link>
                </DescriptionsItem>
                <DescriptionsItem v-if="contact.lifecycle_stage" :label="$t('contacts.lifecycle_stage')">
                    <Tag :bordered="false">{{ contact.lifecycle_stage }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem v-if="contact.owner" :label="$t('contacts.owner')">
                    {{ contact.owner.name }}
                </DescriptionsItem>
                <DescriptionsItem label="ID">{{ contact.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(contact.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="contact.creator" :label="$t('global.created_by')">
                    {{ contact.creator.name }}
                    <span class="audit-email">({{ contact.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="contact" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('crm.contacts.show', contact.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('crm.contacts.delete', contact.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === contact.id"
                    @click="emit('duplicate', contact)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('crm.contacts.edit', contact.slug)"
                >
                    <Button :block="isMobile" type="primary">
                        <EditOutlined /> {{ $t('global.edit') }}
                    </Button>
                </Link>
            </div>
        </template>
    </Drawer>
</template>

<style scoped>
.drawer-hero { display: flex; align-items: center; gap: 14px; padding: 8px 0; }
.drawer-hero__icon {
    width: 48px;
    height: 48px;
    border-radius: 4px;
    background: var(--color-primary);
    color: var(--color-text-on-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.drawer-hero h2 {
    font-size: 1.15rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: var(--color-text);
}
.mt-4 { margin-top: 16px; }
.drawer-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}
.drawer-footer--mobile {
    flex-direction: column-reverse;
    gap: 10px;
    align-items: stretch;
}
.audit-email { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
</style>
