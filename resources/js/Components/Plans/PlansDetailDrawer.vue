<script setup>
/** Drawer lateral: preview rapido del plan sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { SafetyCertificateOutlined, DeleteOutlined, CopyOutlined, EditOutlined, EyeOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    plan:          { type: Object,  default: null },
    width:         { type: [Number, String], default: 480 },
    isMobile:      { type: Boolean, default: false },
    canCreate:     { type: Boolean, default: true },
    canEdit:       { type: Boolean, default: true },
    canDelete:     { type: Boolean, default: true },
    duplicatingId: { type: [Number, String, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);

const fmtLimit = (n) => n < 0 ? '∞' : n.toLocaleString();
const fmtMoney = (n, c) => n > 0 ? `${c} ${Number(n).toFixed(2)}` : '—';
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('plans.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="plan">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <SafetyCertificateOutlined />
                </div>
                <div>
                    <h2>{{ plan.name }}</h2>
                    <Tag :color="plan.is_active ? 'success' : 'error'" :bordered="false">
                        {{ plan.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ plan.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('plans.slug')"><code>{{ plan.slug }}</code></DescriptionsItem>
                <DescriptionsItem v-if="plan.tagline" :label="$t('plans.tagline')">{{ plan.tagline }}</DescriptionsItem>
                <DescriptionsItem :label="$t('plans.support_level')">
                    {{ $t('plans.support_' + (plan.support_level || 'community')) }}
                </DescriptionsItem>
                <DescriptionsItem :label="$t('plans.max_users')">{{ fmtLimit(plan.max_users) }}</DescriptionsItem>
                <DescriptionsItem :label="$t('plans.max_records_per_module')">{{ fmtLimit(plan.max_records_per_module) }}</DescriptionsItem>
                <DescriptionsItem :label="$t('plans.price_monthly')">{{ fmtMoney(plan.price_monthly, plan.currency) }}</DescriptionsItem>
                <DescriptionsItem :label="$t('plans.price_yearly')">{{ fmtMoney(plan.price_yearly, plan.currency) }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(plan.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="plan.creator" :label="$t('global.created_by')">
                    {{ plan.creator.name }}
                    <span class="audit-email">({{ plan.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="plan" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('system_management.plans.show', plan.id)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link v-if="canDelete" :href="route('system_management.plans.delete', plan.id)">
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button v-if="canCreate" :block="isMobile" :loading="duplicatingId === plan.id" @click="emit('duplicate', plan)">
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link v-if="canEdit" :href="route('system_management.plans.edit', plan.id)">
                    <Button :block="isMobile" type="primary">
                        <EditOutlined /> {{ $t('global.edit') }}
                    </Button>
                </Link>
            </div>
        </template>
    </Drawer>
</template>

<style scoped>
.drawer-hero {
    display: flex;
    align-items: center;
    gap: 14px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--color-border);
}
.drawer-hero__icon {
    width: 44px;
    height: 44px;
    border-radius: 6px;
    background: var(--color-primary);
    color: var(--color-text-on-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.2rem;
}
.drawer-hero h2 { margin: 0; font-size: 1.1rem; font-weight: 500; }

.audit-email { color: var(--color-text-muted); font-size: 0.8rem; }

.drawer-footer {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    justify-content: flex-end;
}
.drawer-footer--mobile {
    flex-direction: column;
    gap: 10px;
}
.mt-4 { margin-top: 16px; }
</style>
