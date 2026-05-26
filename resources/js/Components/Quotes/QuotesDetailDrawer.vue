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
    return v.toLocaleString('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};
const docStatusColor = (s) => ({
    draft: 'default', sent: 'blue', accepted: 'success', rejected: 'red', expired: 'orange', revised: 'purple',
}[s] ?? 'default');

const { formatDate, formatDateTime } = useDateFormat();

defineProps({
    open:          { type: Boolean, required: true },
    quote:      { type: Object,  default: null },
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
        :title="$t('quotes.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="quote">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <UserOutlined />
                </div>
                <div>
                    <h2>{{ quote.reference ?? '#' + quote.id }}</h2>
                    <Tag :color="docStatusColor(quote.status)" :bordered="false">
                        {{ $t(`quotes.status_options.${quote.status}`) }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4" size="small">
                <DescriptionsItem v-if="quote.company" :label="$t('quotes.company')">
                    <Link :href="route('crm.companies.show', quote.company.slug ?? quote.company.id)">{{ quote.company.name }}</Link>
                </DescriptionsItem>
                <DescriptionsItem v-if="quote.contact" :label="$t('quotes.contact')">
                    <Link :href="route('crm.contacts.show', quote.contact.slug ?? quote.contact.id)">{{ quote.contact.name }}</Link>
                </DescriptionsItem>
                <DescriptionsItem v-if="quote.issue_date" :label="$t('quotes.issue_date')">
                    {{ formatDate(quote.issue_date) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="quote.valid_until" :label="$t('quotes.valid_until')">
                    {{ formatDate(quote.valid_until) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="quote.grand_total != null" :label="$t('quotes.grand_total')">
                    <strong>{{ quote.currency_code }} {{ fmtMoney(quote.grand_total) }}</strong>
                </DescriptionsItem>
                <DescriptionsItem label="ID">{{ quote.id }}</DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(quote.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="quote.creator" :label="$t('global.created_by')">
                    {{ quote.creator.name }}
                    <span class="audit-email">({{ quote.creator.email }})</span>
                </DescriptionsItem>
            </Descriptions>
        </template>

        <template #footer>
            <div v-if="quote" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link :href="route('business_management.quotes.show', quote.slug)">
                    <Button :block="isMobile">
                        <EyeOutlined /> {{ $t('global.view') }}
                    </Button>
                </Link>
                <Link
                    v-if="canDelete"
                    :href="route('business_management.quotes.delete', quote.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === quote.id"
                    @click="emit('duplicate', quote)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('business_management.quotes.edit', quote.slug)"
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
