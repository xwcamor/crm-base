<script setup>
/** 2 bottom drawers mobile: Filtros (replica FilterBar) y Otros (secondary actions). */
import { computed } from 'vue';
import { Drawer, Button } from 'ant-design-vue';
import {
    DownloadOutlined, UploadOutlined, InboxOutlined, AuditOutlined, EditOutlined,
    FilterOutlined, CloseCircleFilled,
} from '@ant-design/icons-vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import ColumnSelector from '@/Components/Common/ColumnSelector.vue';
import { usePlanFeatures } from '@/Composables/usePlanFeatures';

const { canUse } = usePlanFeatures();
const canUseImports = computed(() => canUse('imports'));
const canUseEditAll = computed(() => canUse('edit_all'));

defineProps({
    filtersOpen:  { type: Boolean, required: true },
    otrosOpen:    { type: Boolean, required: true },
    filterFields: { type: Array,   required: true },
    allColumns:   { type: Array,   default: () => [] },
    canCreate:    { type: Boolean, default: false },
    canEdit:      { type: Boolean, default: false },
    isSuper:      { type: Boolean, default: false },
    canSeeAudit:  { type: Boolean, default: false },
    advancedCount: { type: Number, default: 0 },
});

const emit = defineEmits([
    'update:filtersOpen',
    'update:otrosOpen',
    'open-export',
    'open-import',
    'go-trash',
    'go-audit',
    'go-edit-all',
    'open-advanced',
    'clear-advanced',
]);

const filtersValue   = defineModel('filters',        { type: Object, required: true });
const visibleColumns = defineModel('visibleColumns', { type: Array,  default: () => [] });

const fromOtros = (event) => {
    emit('update:otrosOpen', false);
    emit(event);
};
</script>

<template>
    <Drawer
        :open="filtersOpen"
        :title="$t('global.filters')"
        placement="bottom"
        height="auto"
        :body-style="{ paddingBottom: '24px' }"
        @update:open="emit('update:filtersOpen', $event)"
    >
        <div class="adv-mobile">
            <Button
                block
                size="large"
                :type="advancedCount > 0 ? 'primary' : 'default'"
                @click="emit('update:filtersOpen', false); emit('open-advanced')"
            >
                <FilterOutlined />
                {{ $t('global.advanced_filters') }}
                <span v-if="advancedCount > 0" class="adv-mobile__count">{{ advancedCount }}</span>
            </Button>
            <Button
                v-if="advancedCount > 0"
                block
                size="small"
                type="text"
                danger
                @click="emit('clear-advanced')"
                class="adv-mobile__clear"
            >
                <CloseCircleFilled /> {{ $t('global.clear') }}
            </Button>
        </div>

        <FilterBar
            :fields="filterFields"
            v-model="filtersValue"
            storage-key="exchange_rates"
        />
    </Drawer>

    <Drawer
        :open="otrosOpen"
        :title="$t('global.more') + ' ' + $t('global.actions').toLowerCase()"
        placement="bottom"
        height="auto"
        @update:open="emit('update:otrosOpen', $event)"
    >
        <div class="otros-list">
            <Button block size="large" @click="fromOtros('open-export')">
                <DownloadOutlined /> {{ $t('global.export') }}
            </Button>
            <Button v-if="canCreate && canUseImports" block size="large" @click="fromOtros('open-import')">
                <UploadOutlined /> {{ $t('global.import') }}
            </Button>
            <Button v-if="canEdit && canUseEditAll" block size="large" @click="fromOtros('go-edit-all')">
                <EditOutlined /> {{ $t('global.edit_all') }}
            </Button>
            <ColumnSelector
                v-if="allColumns.length"
                :columns="allColumns"
                v-model="visibleColumns"
                storage-key="exchange_rates"
            />
            <Button v-if="isSuper" block size="large" @click="fromOtros('go-trash')">
                <InboxOutlined /> {{ $t('global.view_deleted') }}
            </Button>
            <Button v-if="canSeeAudit" block size="large" @click="fromOtros('go-audit')">
                <AuditOutlined /> {{ $t('sidebar.group_audit') }}
            </Button>
        </div>
    </Drawer>
</template>

<style scoped>
.otros-list {
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.otros-list :deep(.ant-btn) {
    height: 48px;
    text-align: left;
    justify-content: flex-start;
}

.adv-mobile {
    margin-bottom: 14px;
    padding-bottom: 14px;
    border-bottom: 1px solid var(--color-border, #e1e3e5);
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.adv-mobile__count {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    min-width: 20px;
    height: 20px;
    padding: 0 7px;
    border-radius: 10px;
    background: rgba(255, 255, 255, 0.25);
    font-size: 0.75rem;
    font-weight: 600;
    margin-left: 4px;
}
.adv-mobile__clear { margin-top: 0; }
</style>
