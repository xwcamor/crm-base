<script setup>
/**
 * EntityShowTabs — wrapper de tabs para páginas Show de cualquier módulo.
 *
 * Patrón: 2 tabs (Detalles / Historial). El tab Historial se muestra solo si
 * el viewer tiene permiso (super o admin) — el componente NO calcula
 * eso, lo recibe como prop `showHistory`.
 *
 * Uso:
 *   <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length">
 *     <template #general>
 *       <Card>...</Card>
 *       <Card>...</Card>
 *     </template>
 *     <template #history>
 *       <ActivityTimeline :activity="activity" />
 *     </template>
 *   </EntityShowTabs>
 *
 * Layout: full-width single column dentro de cada tab. Si un módulo quiere
 * grid de 2 cols adentro, usar Row/Col dentro del slot — este wrapper no
 * impone layout interno.
 */
import { ref } from 'vue';
import { Tabs, TabPane, Badge } from 'ant-design-vue';
import {
    FileTextOutlined, HistoryOutlined, CheckSquareOutlined, FileDoneOutlined,
    UserOutlined, DollarOutlined, ProfileOutlined,
} from '@ant-design/icons-vue';
import RecordMetaFooter from '@/Components/Common/RecordMetaFooter.vue';

defineProps({
    showHistory:  { type: Boolean, default: false },
    historyCount: { type: Number,  default: 0 },
    showActivities: { type: Boolean, default: false },
    activitiesCount: { type: Number, default: 0 },
    showContacts: { type: Boolean, default: false },
    contactsCount: { type: Number, default: 0 },
    showDeals:    { type: Boolean, default: false },
    dealsCount:   { type: Number,  default: 0 },
    showQuotes:   { type: Boolean, default: false },
    quotesCount:  { type: Number,  default: 0 },
    showInvoices: { type: Boolean, default: false },
    invoicesCount: { type: Number, default: 0 },
    defaultKey:   { type: String,  default: 'general' },
    // Para el RecordMetaFooter al pie. Si record viene, se renderiza.
    record:       { type: Object, default: null },
    activity:     { type: Array,  default: () => [] },
});

const activeKey = ref('general');
</script>

<template>
    <Tabs
        v-model:activeKey="activeKey"
        class="entity-show-tabs"
        :tabBarStyle="{ marginBottom: '16px' }"
    >
        <TabPane key="general">
            <template #tab>
                <span class="tab-label">
                    <FileTextOutlined /> {{ $t('global.details') }}
                </span>
            </template>
            <slot name="general" />
        </TabPane>

        <TabPane v-if="showActivities" key="activities">
            <template #tab>
                <span class="tab-label">
                    <CheckSquareOutlined /> {{ $t('activities.panel_title') }}
                    <Badge
                        v-if="activitiesCount > 0"
                        :count="activitiesCount"
                        :overflow-count="99"
                        :number-style="{ backgroundColor: 'var(--color-surface-alt, #f0f5ff)', color: 'var(--color-primary, #0A6ED1)', boxShadow: '0 0 0 1px var(--color-border, #d9d9d9) inset' }"
                        class="history-badge"
                    />
                </span>
            </template>
            <slot name="activities" />
        </TabPane>

        <TabPane v-if="showContacts" key="contacts">
            <template #tab>
                <span class="tab-label">
                    <UserOutlined /> {{ $t('contacts.plural') }}
                    <Badge
                        v-if="contactsCount > 0"
                        :count="contactsCount"
                        :overflow-count="99"
                        :number-style="{ backgroundColor: 'var(--color-surface-alt, #f0f5ff)', color: 'var(--color-primary, #0A6ED1)', boxShadow: '0 0 0 1px var(--color-border, #d9d9d9) inset' }"
                        class="history-badge"
                    />
                </span>
            </template>
            <slot name="contacts" />
        </TabPane>

        <TabPane v-if="showDeals" key="deals">
            <template #tab>
                <span class="tab-label">
                    <DollarOutlined /> {{ $t('deals.plural') }}
                    <Badge
                        v-if="dealsCount > 0"
                        :count="dealsCount"
                        :overflow-count="99"
                        :number-style="{ backgroundColor: 'var(--color-surface-alt, #f0f5ff)', color: 'var(--color-primary, #0A6ED1)', boxShadow: '0 0 0 1px var(--color-border, #d9d9d9) inset' }"
                        class="history-badge"
                    />
                </span>
            </template>
            <slot name="deals" />
        </TabPane>

        <TabPane v-if="showQuotes" key="quotes">
            <template #tab>
                <span class="tab-label">
                    <FileDoneOutlined /> {{ $t('quotes.plural') }}
                    <Badge
                        v-if="quotesCount > 0"
                        :count="quotesCount"
                        :overflow-count="99"
                        :number-style="{ backgroundColor: 'var(--color-surface-alt, #f0f5ff)', color: 'var(--color-primary, #0A6ED1)', boxShadow: '0 0 0 1px var(--color-border, #d9d9d9) inset' }"
                        class="history-badge"
                    />
                </span>
            </template>
            <slot name="quotes" />
        </TabPane>

        <TabPane v-if="showInvoices" key="invoices">
            <template #tab>
                <span class="tab-label">
                    <ProfileOutlined /> {{ $t('invoices.plural') }}
                    <Badge
                        v-if="invoicesCount > 0"
                        :count="invoicesCount"
                        :overflow-count="99"
                        :number-style="{ backgroundColor: 'var(--color-surface-alt, #f0f5ff)', color: 'var(--color-primary, #0A6ED1)', boxShadow: '0 0 0 1px var(--color-border, #d9d9d9) inset' }"
                        class="history-badge"
                    />
                </span>
            </template>
            <slot name="invoices" />
        </TabPane>

        <TabPane v-if="showHistory" key="history">
            <template #tab>
                <span class="tab-label">
                    <HistoryOutlined /> {{ $t('global.history') }}
                    <Badge
                        v-if="historyCount > 0"
                        :count="historyCount"
                        :overflow-count="99"
                        :number-style="{ backgroundColor: 'var(--color-surface-alt, #f0f5ff)', color: 'var(--color-primary, #0A6ED1)', boxShadow: '0 0 0 1px var(--color-border, #d9d9d9) inset' }"
                        class="history-badge"
                    />
                </span>
            </template>
            <slot name="history" />
        </TabPane>
    </Tabs>

    <RecordMetaFooter v-if="record" :record="record" :activity="activity" />
</template>

<style scoped>
.entity-show-tabs :deep(.ant-tabs-nav) {
    margin: 0 0 16px 0;
}
.entity-show-tabs :deep(.ant-tabs-tab) {
    padding: 10px 16px;
    font-size: 0.9375rem;
}
.entity-show-tabs :deep(.ant-tabs-tab .anticon) {
    margin-right: 6px;
}
.tab-label {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.history-badge {
    margin-left: 6px;
}

/* Mobile: tabs un poco más chicas, scroll horizontal si no caben */
@media (max-width: 640px) {
    .entity-show-tabs :deep(.ant-tabs-tab) {
        padding: 8px 12px;
        font-size: 0.875rem;
    }
}
</style>
