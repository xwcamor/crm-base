<script setup>
/** 2 bottom drawers mobile: Filtros (replica FilterBar) y Otros (secondary actions). */
import { computed } from 'vue';
import { Drawer, Button } from 'ant-design-vue';
import {
    DownloadOutlined, UploadOutlined, InboxOutlined, AuditOutlined, EditOutlined,
} from '@ant-design/icons-vue';
import FilterBar from '@/Components/Common/FilterBar.vue';
import ColumnSelector from '@/Components/Common/ColumnSelector.vue';
import { usePlanFeatures } from '@/Composables/usePlanFeatures';

const { canUse } = usePlanFeatures();
// `imports` requiere plan pro+. Si el tenant no lo tiene, ocultamos el control
// para no confundir (mismo patron que ModuleToolbar).
const canUseImports = computed(() => canUse('imports'));
const canUseEditAll = computed(() => canUse('edit_all'));

defineProps({
    filtersOpen:  { type: Boolean, required: true },
    otrosOpen:    { type: Boolean, required: true },
    filterFields: { type: Array,   required: true },
    // Columns para el ColumnSelector — sin estos, el boton "Columnas" del
    // drawer Otros no se renderiza (defensive default).
    allColumns:   { type: Array,   default: () => [] },
    canCreate:    { type: Boolean, default: false },
    canEdit:      { type: Boolean, default: false },
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
    'go-edit-all',
]);

const filtersValue = defineModel('filters', { type: Object, required: true });
// v-model para columns: comparte estado con la instancia del toolbar desktop
// via el mismo localStorage key — coherencia entre vistas.
const visibleColumns = defineModel('visibleColumns', { type: Array, default: () => [] });

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
            storage-key="users"
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
            <!-- Configurar columnas — el ColumnSelector ya provee su propio
                 trigger Button + Drawer interno. v-model comparte estado con
                 el toolbar desktop (mismo localStorage key). -->
            <ColumnSelector
                v-if="allColumns.length"
                :columns="allColumns"
                v-model="visibleColumns"
                storage-key="users"
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
</style>
