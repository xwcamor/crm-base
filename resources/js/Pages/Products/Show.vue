<script setup>
import { computed } from 'vue';
import { Head } from '@inertiajs/vue3';
import {
    Card, Tag, Space, Descriptions, DescriptionsItem, Alert, Image as AImage,
} from 'ant-design-vue';
import {
    HistoryOutlined, AppstoreOutlined, ShoppingOutlined,
    InboxOutlined, ReloadOutlined, ColumnHeightOutlined, PictureOutlined,
    DollarOutlined, BarcodeOutlined, PercentageOutlined,
} from '@ant-design/icons-vue';

import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import EntityShowTabs from '@/Components/Common/EntityShowTabs.vue';
import KPITiles from '@/Components/Common/KPITiles.vue';
import EntityShowActions from '@/Components/Common/EntityShowActions.vue';
import ViewDeletedButton from '@/Components/Common/ViewDeletedButton.vue';
import ActivityTimeline from '@/Components/Common/ActivityTimeline.vue';
import { useAuth } from '@/Composables/useAuth';
import { useDateFormat } from '@/Composables/useDateFormat';

defineOptions({ layout: AppLayout });

const props = defineProps({
    product:  { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { can, isSuper, canSeeAudit } = useAuth();
const { formatDateTimeFull } = useDateFormat();

const isDeleted = computed(() => !!props.product.deleted_at);
const iconBg = computed(() => isDeleted.value ? 'var(--color-danger)' : 'var(--color-primary)');

const fmt = (d) => formatDateTimeFull(d);
const fmtMoney = (n) => n == null || n === ''
    ? '—'
    : new Intl.NumberFormat('es', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(n));
const fmtNum = (n) => n == null ? '—' : new Intl.NumberFormat('es').format(Number(n));

const typeColor = { good: 'blue', service: 'green', subscription: 'purple', bundle: 'orange' };

const isGood = computed(() => props.product.type === 'good');
const isSubscription = computed(() => props.product.type === 'subscription');

const margin = computed(() => {
    if (!props.product.list_price || props.product.cost == null) return null;
    const v = ((Number(props.product.list_price) - Number(props.product.cost)) / Number(props.product.list_price)) * 100;
    return Number.isFinite(v) ? v.toFixed(1) + '%' : null;
});
const finalMargin = computed(() => {
    if (!props.product.list_price || props.product.final_cost == null) return null;
    const v = ((Number(props.product.list_price) - Number(props.product.final_cost)) / Number(props.product.list_price)) * 100;
    return Number.isFinite(v) ? v.toFixed(1) + '%' : null;
});

const kpiTiles = computed(() => {
    const code = props.product.currency_code || '';
    return [
        { icon: DollarOutlined,     label: 'Precio de lista', value: code + ' ' + fmtMoney(props.product.list_price), color: 'primary' },
        { icon: PercentageOutlined, label: 'Margen',          value: margin.value ?? '—',
          color: margin.value && parseFloat(margin.value) > 0 ? 'success' : 'default' },
        { icon: InboxOutlined,      label: 'Stock total',     value: fmtNum(props.product.stock_on_hand ?? 0),
          hint: props.product.type === 'good' ? null : 'No aplica',
          color: props.product.type !== 'good' ? 'default' : (Number(props.product.stock_on_hand ?? 0) > 0 ? 'success' : 'warning') },
        { icon: BarcodeOutlined,    label: 'SKU',             value: props.product.sku || '—' },
    ];
});
</script>

<template>
    <Head :title="product.name" />

    <div class="show-page">
        <SectionHeader
            :back-href="route('business_management.products.index')"
            :title="product.name"
            :icon-bg="iconBg"
        >
            <template #icon><AppstoreOutlined /></template>
            <template #subtitle>
                <Space :size="6" wrap>
                    <Tag v-if="isDeleted" color="red" :bordered="false">{{ $t('global.deleted') }}</Tag>
                    <Tag v-else :color="product.is_active ? 'success' : 'default'" :bordered="false">
                        {{ product.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                    <Tag :color="typeColor[product.type] || 'default'" :bordered="false">
                        {{ $t('products.type_options.' + product.type) }}
                    </Tag>
                    <span v-if="product.sku" class="muted">SKU {{ product.sku }}</span>
                    <span class="muted">ID #{{ product.id }}</span>
                </Space>
            </template>
            <template #actions>
                <EntityShowActions
                    module="products"
                    route-prefix="business_management"
                    :slug="product.slug"
                    :id="product.id"
                    :is-deleted="isDeleted"
                    :can-edit="can('products.edit')"
                    :can-delete="can('products.delete')"
                    :can-see-audit="canSeeAudit"
                />
            </template>
        </SectionHeader>

        <Alert v-if="isDeleted" type="error" show-icon class="deleted-alert">
            <template #message>{{ $t('global.record_is_deleted') }}</template>
            <template #description>
                <div><strong>{{ $t('global.deleted_at') }}:</strong> {{ fmt(product.deleted_at) }}</div>
                <div v-if="product.deleter">
                    <strong>{{ $t('global.deleted_by') }}:</strong> {{ product.deleter.name }}
                </div>
                <div v-if="product.deleted_description">
                    <strong>{{ $t('global.delete_description') }}:</strong> {{ product.deleted_description }}
                </div>
            </template>
            <template v-if="isSuper" #action>
                <ViewDeletedButton module="products" route-prefix="business_management" />
            </template>
        </Alert>

        <KPITiles :tiles="kpiTiles" />

        <EntityShowTabs :show-history="canSeeAudit" :history-count="activity.length"
        :record="product"
        :activity="activity"
    >
            <template #general>

                <!-- Identidad + clasificación -->
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title><AppstoreOutlined /> {{ $t('products.section_general') }}</template>
                    <Descriptions :column="2" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem label="ID">{{ product.id }}</DescriptionsItem>
                        <DescriptionsItem v-if="isSuper" label="Slug">
                            <code class="muted">{{ product.slug }}</code>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.name')" :span="2">{{ product.name }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('products.sku')">{{ product.sku ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('products.barcode')">{{ product.barcode ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('products.description')" :span="2">{{ product.description ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem v-if="product.long_description" :label="$t('products.long_description')" :span="2">
                            <div class="long-desc">{{ product.long_description }}</div>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.is_active')" :span="2">
                            <Tag :color="product.is_active ? 'success' : 'default'" :bordered="false">
                                {{ product.is_active ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>

                <!-- Clasificación -->
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title><ShoppingOutlined /> {{ $t('products.section_classification') }}</template>
                    <Descriptions :column="2" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('products.type')">
                            <Tag :color="typeColor[product.type] || 'default'" :bordered="false">
                                {{ $t('products.type_options.' + product.type) }}
                            </Tag>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.category')">{{ product.category?.name ?? '—' }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('products.brand')" :span="2">{{ product.brand ?? '—' }}</DescriptionsItem>
                    </Descriptions>
                </Card>

                <!-- Precios -->
                <Card :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title>{{ $t('products.section_pricing') }}</template>
                    <Descriptions :column="2" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('products.list_price')">
                            <strong>{{ product.currency_code }} {{ fmtMoney(product.list_price) }}</strong>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.cost')">
                            {{ product.cost ? product.currency_code + ' ' + fmtMoney(product.cost) : '—' }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.final_cost')">
                            {{ product.final_cost ? product.currency_code + ' ' + fmtMoney(product.final_cost) : '—' }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.margin')">
                            <Tag v-if="margin" color="green" :bordered="false">{{ margin }}</Tag>
                            <span v-else>—</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.final_margin_pct')">
                            <Tag v-if="finalMargin" color="blue" :bordered="false">{{ finalMargin }}</Tag>
                            <span v-else>—</span>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.currency')">{{ product.currency_code ?? '—' }}</DescriptionsItem>
                    </Descriptions>
                </Card>

                <!-- Inventario (solo good) -->
                <Card v-if="isGood" :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title><InboxOutlined /> {{ $t('products.section_inventory') }}</template>
                    <Descriptions :column="2" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('products.track_inventory')">
                            <Tag :color="product.track_inventory ? 'green' : 'default'" :bordered="false">
                                {{ product.track_inventory ? $t('global.active') : $t('global.inactive') }}
                            </Tag>
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.low_stock_threshold')">{{ fmtNum(product.low_stock_threshold) }}</DescriptionsItem>
                    </Descriptions>
                </Card>

                <!-- Subscription (solo subscription) -->
                <Card v-if="isSubscription" :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title><ReloadOutlined /> {{ $t('products.section_subscription') }}</template>
                    <Descriptions :column="2" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('products.billing_cycle')">
                            {{ product.billing_cycle ? $t('products.billing_cycle_options.' + product.billing_cycle) : '—' }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.billing_period')">{{ fmtNum(product.billing_period) }}</DescriptionsItem>
                    </Descriptions>
                </Card>

                <!-- Dimensiones (solo good) -->
                <Card v-if="isGood && (product.weight_kg || product.length_cm || product.width_cm || product.height_cm)" :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title><ColumnHeightOutlined /> {{ $t('products.section_shipping') }}</template>
                    <Descriptions :column="4" bordered :labelStyle="{ width: '120px' }">
                        <DescriptionsItem :label="$t('products.weight_kg')">{{ fmtMoney(product.weight_kg) }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('products.length_cm')">{{ fmtMoney(product.length_cm) }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('products.width_cm')">{{ fmtMoney(product.width_cm) }}</DescriptionsItem>
                        <DescriptionsItem :label="$t('products.height_cm')">{{ fmtMoney(product.height_cm) }}</DescriptionsItem>
                    </Descriptions>
                </Card>

                <!-- Imagen + IDs externos -->
                <Card v-if="product.image_url || product.external_id" :bodyStyle="{ padding: 0 }" class="info-card">
                    <template #title><PictureOutlined /> {{ $t('products.section_media') }}</template>
                    <Descriptions :column="2" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem v-if="product.image_url" :label="$t('products.image_url')" :span="2">
                            <AImage :src="product.image_url" :width="180" />
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('products.external_id')" :span="2">
                            <code v-if="product.external_id" class="muted-code">{{ product.external_id }}</code>
                            <span v-else class="muted">—</span>
                            <div class="muted hint">{{ $t('products.external_id_hint') }}</div>
                        </DescriptionsItem>
                    </Descriptions>
                </Card>
            </template>

            <template #history>
                <Card :title="$t('global.record_audit')" :bodyStyle="{ padding: 0 }" class="info-card">
                    <Descriptions :column="1" bordered :labelStyle="{ width: '180px' }">
                        <DescriptionsItem :label="$t('global.created_at')">{{ fmt(product.created_at) }}</DescriptionsItem>
                        <DescriptionsItem v-if="product.creator" :label="$t('global.created_by')">
                            {{ product.creator.name }}
                        </DescriptionsItem>
                        <DescriptionsItem :label="$t('global.updated_at')">{{ fmt(product.updated_at) }}</DescriptionsItem>
                        <template v-if="isDeleted">
                            <DescriptionsItem :label="$t('global.deleted_at')">{{ fmt(product.deleted_at) }}</DescriptionsItem>
                            <DescriptionsItem v-if="product.deleter" :label="$t('global.deleted_by')">
                                {{ product.deleter.name }}
                            </DescriptionsItem>
                            <DescriptionsItem :label="$t('global.delete_description')">
                                {{ product.deleted_description || '—' }}
                            </DescriptionsItem>
                        </template>
                    </Descriptions>
                </Card>

                <Card :bodyStyle="{ padding: 16 }" class="info-card">
                    <template #title>
                        <HistoryOutlined /> {{ $t('global.recent_activity') }}
                    </template>
                    <ActivityTimeline :activity="activity" />
                </Card>
            </template>
        </EntityShowTabs>
    </div>
</template>

<style scoped>
.muted { color: var(--color-text-muted); font-size: 0.8125rem; }
.muted-code { font-family: var(--font-mono, monospace); padding: 2px 6px; background: var(--color-surface-alt, #f5f5f5); border-radius: 3px; font-size: 0.85rem; }
.hint { font-size: 0.78rem; margin-top: 4px; }
.deleted-alert { margin-bottom: 16px; }
.info-card { margin-bottom: 16px; border-radius: 6px; }
.long-desc { white-space: pre-wrap; }

@media (max-width: 767px) {
    :deep(.ant-descriptions-item-label) {
        width: auto !important;
        min-width: 0 !important;
        white-space: normal !important;
        font-weight: 500;
    }
    :deep(.ant-descriptions-item-content) { word-break: break-word; }
}
</style>
