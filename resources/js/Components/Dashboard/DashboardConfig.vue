<script setup>
/**
 * DashboardConfig — drawer para personalizar que cards/secciones se ven
 * en el dashboard.
 *
 * Funcionamiento:
 * - Recibe un catalogo de `sections` que el dashboard tiene disponibles
 *   con { key, label, group? }.
 * - Mantiene el estado de visibilidad en localStorage (per-browser) bajo
 *   la key `dashboard_layout_<storageKey>`. Persistencia cross-device es
 *   feature futura.
 * - Emite `update:visible` cuando cambia. El padre puede ocultar cards
 *   con v-if="isVisible('section_key')".
 *
 * Usage:
 *   <DashboardConfig
 *       storage-key="business"
 *       :sections="sectionCatalog"
 *       v-model:visible="visible"
 *   />
 *   ...
 *   <Row v-if="visible.kpis_crm">...</Row>
 */
import { ref, computed, onMounted, watch } from 'vue';
import { Drawer, Button, Switch, Divider, Tooltip, message } from 'ant-design-vue';
import { SettingOutlined, EyeOutlined, EyeInvisibleOutlined, ReloadOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    storageKey: { type: String, required: true },
    sections:   { type: Array, required: true },   // [{ key, label, group? }]
});
const emit = defineEmits(['update:visible']);

const STORAGE_PREFIX = 'dashboard_layout_';
const open = ref(false);

// Por defecto: todas las secciones visibles.
const defaultVisible = computed(() => {
    const obj = {};
    for (const s of props.sections) obj[s.key] = true;
    return obj;
});

const visible = ref({ ...defaultVisible.value });

const load = () => {
    try {
        const raw = localStorage.getItem(STORAGE_PREFIX + props.storageKey);
        if (raw) {
            const parsed = JSON.parse(raw);
            // Merge con los defaults — si en el codigo se agregaron secciones
            // nuevas despues de guardar, esas aparecen visibles por defecto.
            visible.value = { ...defaultVisible.value, ...parsed };
        } else {
            visible.value = { ...defaultVisible.value };
        }
    } catch (e) {
        visible.value = { ...defaultVisible.value };
    }
    emit('update:visible', visible.value);
};

const save = () => {
    try {
        localStorage.setItem(STORAGE_PREFIX + props.storageKey, JSON.stringify(visible.value));
    } catch (e) { /* quota lleno, ignorar */ }
    emit('update:visible', visible.value);
};

const reset = () => {
    visible.value = { ...defaultVisible.value };
    save();
    message.success('Layout restablecido');
};

watch(visible, save, { deep: true });
onMounted(load);

// Agrupa el catalogo por `group` (si lo trae). Si no tiene group, va en
// el grupo "General".
const grouped = computed(() => {
    const groups = {};
    for (const s of props.sections) {
        const g = s.group ?? 'General';
        if (!groups[g]) groups[g] = [];
        groups[g].push(s);
    }
    return groups;
});

const visibleCount = computed(() => Object.values(visible.value).filter(v => v).length);
const totalCount   = computed(() => props.sections.length);
</script>

<template>
    <Tooltip :title="`${visibleCount} de ${totalCount} secciones visibles`">
        <Button @click="open = true">
            <SettingOutlined /> Personalizar
        </Button>
    </Tooltip>

    <Drawer
        v-model:open="open"
        title="Personalizar dashboard"
        :width="380"
        :body-style="{ padding: '12px 20px' }"
    >
        <p class="dash-config-hint">
            Activa o desactiva las secciones del dashboard. Los cambios se guardan
            automaticamente en este navegador.
        </p>

        <div v-for="(items, groupName) in grouped" :key="groupName" class="dash-config-group">
            <Divider orientation="left" :plain="true" style="margin: 14px 0 8px 0; font-size: 0.82rem; color: var(--color-text-muted, #8c8c8c)">
                {{ groupName }}
            </Divider>
            <div v-for="s in items" :key="s.key" class="dash-config-row">
                <span class="dash-config-label">
                    <component :is="visible[s.key] ? EyeOutlined : EyeInvisibleOutlined" :style="{ color: visible[s.key] ? '#1677ff' : '#bfbfbf' }" />
                    {{ s.label }}
                </span>
                <Switch v-model:checked="visible[s.key]" size="small" />
            </div>
        </div>

        <template #footer>
            <div style="text-align: right">
                <Button @click="reset" type="link"><ReloadOutlined /> Mostrar todas</Button>
            </div>
        </template>
    </Drawer>
</template>

<style scoped>
.dash-config-hint {
    font-size: 0.82rem;
    color: var(--color-text-muted, #595959);
    margin: 0 0 10px 0;
}
.dash-config-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 8px 0;
}
.dash-config-label {
    display: inline-flex;
    align-items: center;
    gap: 8px;
    font-size: 0.88rem;
    color: var(--color-text-strong, #262626);
}
</style>
