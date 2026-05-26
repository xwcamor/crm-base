<script setup>
/** Drawer lateral: preview rapido del cliente sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { UserOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    company:      { type: Object,  default: null },
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
        :title="$t('companies.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="company">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <UserOutlined />
                </div>
                <div>
                    <h2>{{ company.name }}</h2>
                    <Tag :color="company.is_active ? 'success' : 'error'" :bordered="false">
                        {{ company.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4" size="small">
                <DescriptionsItem v-if="company.legal_name && company.legal_name !== company.name" :label="$t('companies.legal_name')">
                    {{ company.legal_name }}
                </DescriptionsItem>
                <DescriptionsItem v-if="company.tax_id" :label="$t('companies.tax_id')">
                    <code class="muted">{{ company.tax_id }}</code>
                </DescriptionsItem>
                <DescriptionsItem v-if="company.industry" :label="$t('companies.industry')">
                    {{ company.industry.name }}
                </DescriptionsItem>
                <DescriptionsItem v-if="company.country" :label="$t('companies.country')">
                    {{ company.country.name }}
                </DescriptionsItem>
                <DescriptionsItem v-if="company.lifecycle_stage" :label="$t('companies.lifecycle_stage')">
                    <Tag :bordered="false">{{ company.lifecycle_stage }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem v-if="company.owner" :label="$t('companies.owner')">
                    {{ company.owner.name }}
                </DescriptionsItem>
                <DescriptionsItem v-if="company.website" :label="$t('companies.website')">
                    <a :href="company.website.startsWith('http') ? company.website : 'https://' + company.website" target="_blank" rel="noopener">{{ company.website }}</a>
                </DescriptionsItem>
                <DescriptionsItem v-if="company.deals_count != null" :label="$t('deals.plural')">
                    <Tag color="blue" :bordered="false">{{ company.deals_count }}</Tag>
                </DescriptionsItem>
                <DescriptionsItem label="ID">{{ company.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(company.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="company.creator" :label="$t('global.created_by')">
                    {{ company.creator.name }}
                    <span class="audit-email">({{ company.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="company" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('crm.companies.show', company.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('crm.companies.delete', company.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === company.id"
                    @click="emit('duplicate', company)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('crm.companies.edit', company.slug)"
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
