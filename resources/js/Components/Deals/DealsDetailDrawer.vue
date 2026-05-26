<script setup>
/** Drawer lateral: preview rapido del cliente sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { UserOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const fmtMoney = (n) => {
    if (n == null) return '0';
    const v = Number(n);
    if (!Number.isFinite(v)) return '0';
    return v.toLocaleString('es', { minimumFractionDigits: 0, maximumFractionDigits: 0 });
};
const dealStatusColor = (s) => ({ open: 'blue', won: 'success', lost: 'red', dormant: 'default' }[s] ?? 'default');

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    deal:      { type: Object,  default: null },
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
        :title="$t('deals.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="deal">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <UserOutlined />
                </div>
                <div>
                    <h2>{{ deal.name }}</h2>
                    <Tag :color="dealStatusColor(deal.status)" :bordered="false">
                        {{ $t(`deals.status_options.${deal.status}`) }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4" size="small">
                <DescriptionsItem v-if="deal.company" :label="$t('companies.singular')">
                    <Link :href="route('crm.companies.show', deal.company.slug ?? deal.company.id)">{{ deal.company.name }}</Link>
                </DescriptionsItem>
                <DescriptionsItem v-if="deal.pipeline" :label="$t('deals.pipeline')">
                    <Tag :bordered="false" :style="{ background: deal.pipeline.color || '#888', color: '#fff', borderColor: 'transparent' }">
                        {{ deal.pipeline.name }}
                    </Tag>
                </DescriptionsItem>
                <DescriptionsItem v-if="deal.stage" :label="$t('deals.stage')">
                    <Tag :bordered="false" :style="{ background: deal.stage.color || '#888', color: '#fff', borderColor: 'transparent' }">
                        {{ deal.stage.name }}
                    </Tag>
                </DescriptionsItem>
                <DescriptionsItem v-if="deal.value != null" :label="$t('deals.value')">
                    <strong>{{ deal.currency_code }} {{ fmtMoney(deal.value) }}</strong>
                </DescriptionsItem>
                <DescriptionsItem v-if="deal.expected_close_date" :label="$t('deals.expected_close_date')">
                    {{ deal.expected_close_date }}
                </DescriptionsItem>
                <DescriptionsItem v-if="deal.owner" :label="$t('deals.owner')">
                    {{ deal.owner.name }}
                </DescriptionsItem>
                <DescriptionsItem label="ID">{{ deal.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(deal.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="deal.creator" :label="$t('global.created_by')">
                    {{ deal.creator.name }}
                    <span class="audit-email">({{ deal.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="deal" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('crm.deals.show', deal.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('crm.deals.delete', deal.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === deal.id"
                    @click="emit('duplicate', deal)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('crm.deals.edit', deal.slug)"
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
