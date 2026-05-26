<script setup>
/** 2 bottom drawers mobile: Filtros (replica FilterBar) y Otros (secondary actions). */
import { computed } from 'vue';
import { Drawer, Button } from 'ant-design-vue';
import {
    DownloadOutlined, UploadOutlined, InboxOutlined, AuditOutlined,
} from '@ant-design/icons-vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import { usePlanFeatures } from '@/Composables/usePlanFeatures';

const { canUse } = usePlanFeatures();
// `imports` requiere plan pro+. Si el tenant no lo tiene, ocultamos el control
// para no confundir (mismo patron que ModuleToolbar).
const canUseImports = computed(() => canUse('imports'));

defineProps({
    filtersOpen:  { type: Boolean, required: true },
    otrosOpen:    { type: Boolean, required: true },
    filterFields: { type: Array,   required: true },
    canCreate:    { type: Boolean, default: false },
    isSuper: { type: Boolean, default: false },
    canSeeAudit:  { type: Boolean, default: false },
});

const emit = defineEmits([
    'update:filtersOpen',
    'update:otrosOpen',
    'open-export',
    'open-import',
    'go-trash',
    'go-audit',
]);

const filtersValue = defineModel('filters', { type: Object, required: true });

// Helper: cierra el drawer de Otros antes de disparar la acción para que el
// usuario vea la transición del drawer cerrándose y luego la pantalla destino.
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
        <FilterBar
            :fields="filterFields"
            v-model="filtersValue"
            storage-key="tenants"
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
</style>
