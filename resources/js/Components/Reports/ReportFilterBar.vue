<script setup>
/**
 * ReportFilterBar — barra de filtros reusable para los reportes.
 *
 * Maneja el estado local de filtros + sincroniza con la URL via Inertia.
 * Cada reporte declara qué filtros mostrar via el prop `available` y le
 * pasa opciones para los dropdowns (pipelines, owners, lead sources).
 *
 * Diseñada para integrarse con `SavedViews` — el `state` que se guarda
 * en SavedViews es exactamente el objeto de filtros.
 *
 * Usage:
 *   <ReportFilterBar
 *       :available="['date_range', 'pipeline_id', 'owner_id']"
 *       :pipelines="pipelines"
 *       :owners="owners"
 *       :initial="filters"
 *       route-name="reports.sales_pipeline"
 *       module="report_sales_pipeline"
 *   />
 *
 * Props:
 *   - available  : array con keys de filtros a mostrar
 *   - initial    : objeto con valores iniciales (vienen del controller)
 *   - pipelines, owners, leadSources, currencies, activityTypes : options
 *   - routeName  : nombre de la ruta del reporte (para router.get al aplicar)
 *   - module     : identificador para SavedViews
 */
import { ref, watch, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Card, Button, Select, DatePicker, Space, Tag, Dropdown, Menu, MenuItem,
} from 'ant-design-vue';
import {
    FilterOutlined, ReloadOutlined, DownloadOutlined,
    FilePdfOutlined, FileExcelOutlined,
} from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import SavedViews from '@/Components/Common/SavedViews.vue';

const props = defineProps({
    available:    { type: Array, required: true },
    initial:      { type: Object, default: () => ({}) },
    pipelines:    { type: Array, default: () => [] },
    owners:       { type: Array, default: () => [] },
    leadSources:  { type: Array, default: () => [] },
    currencies:   { type: Array, default: () => [] },
    activityTypes:{ type: Array, default: () => [] },
    routeName:    { type: String, required: true },
    module:       { type: String, required: true },
    // Para construir URLs de export (PDF/Excel) con los filtros actuales.
    // ej. exportKey="sales_pipeline" → /reports/sales_pipeline/pdf?<filters>
    exportKey:    { type: String, default: '' },
});

const filters = ref({
    date_preset: props.initial.date_preset ?? 'last_30d',
    date_from:   props.initial.date_from ?? null,
    date_to:     props.initial.date_to ?? null,
    pipeline_id: props.initial.pipeline_id ?? null,
    owner_id:    props.initial.owner_id ?? null,
    lead_source_id: props.initial.lead_source_id ?? null,
    currency_code:  props.initial.currency_code ?? null,
    activity_type:  props.initial.activity_type ?? null,
});

const DATE_PRESETS = [
    { value: 'today',     label: 'Hoy' },
    { value: 'last_7d',   label: 'Últimos 7 días' },
    { value: 'last_30d',  label: 'Últimos 30 días' },
    { value: 'this_month',label: 'Este mes' },
    { value: 'last_month',label: 'Mes anterior' },
    { value: 'this_quarter', label: 'Trimestre actual' },
    { value: 'this_year', label: 'Este año' },
    { value: 'last_year', label: 'Año anterior' },
    { value: 'custom',    label: 'Personalizado' },
    { value: 'all',       label: 'Todo' },
];

const isCustom = computed(() => filters.value.date_preset === 'custom');

const customRange = ref(
    filters.value.date_from && filters.value.date_to
        ? [dayjs(filters.value.date_from), dayjs(filters.value.date_to)]
        : null,
);

watch(customRange, (v) => {
    if (v && v.length === 2) {
        filters.value.date_from = v[0].format('YYYY-MM-DD');
        filters.value.date_to   = v[1].format('YYYY-MM-DD');
    } else {
        filters.value.date_from = null;
        filters.value.date_to   = null;
    }
});

// Limpia campos del state que no aplican a este reporte (segun `available`)
// antes de mandar al server o guardar como saved view.
const cleanState = () => {
    const has = (k) => props.available.includes(k);
    const s = { ...filters.value };
    if (!has('date_range')) { delete s.date_preset; delete s.date_from; delete s.date_to; }
    if (!has('pipeline_id'))    delete s.pipeline_id;
    if (!has('owner_id'))       delete s.owner_id;
    if (!has('lead_source_id')) delete s.lead_source_id;
    if (!has('currency_code'))  delete s.currency_code;
    if (!has('activity_type'))  delete s.activity_type;
    // Saca nulls/undefined
    return Object.fromEntries(Object.entries(s).filter(([_, v]) => v !== null && v !== undefined && v !== ''));
};

const apply = () => {
    router.get(route(props.routeName), cleanState(), {
        preserveState: false,
        preserveScroll: true,
    });
};

const reset = () => {
    filters.value = {
        date_preset: 'last_30d', date_from: null, date_to: null,
        pipeline_id: null, owner_id: null, lead_source_id: null,
        currency_code: null, activity_type: null,
    };
    customRange.value = null;
    apply();
};

// SavedViews integration: cuando carga una vista, sobreescribimos filters
// y disparamos apply().
const onViewApply = (state) => {
    Object.assign(filters.value, state);
    if (state.date_from && state.date_to) {
        customRange.value = [dayjs(state.date_from), dayjs(state.date_to)];
    } else {
        customRange.value = null;
    }
    apply();
};

const onDefaultLoaded = (state) => {
    // Solo aplica el default si la URL NO trae filtros explicitos.
    const url = new URL(window.location.href);
    const hasUrlFilters = ['date_preset','date_from','date_to','pipeline_id','owner_id','lead_source_id','currency_code','activity_type']
        .some(k => url.searchParams.has(k));
    if (!hasUrlFilters) onViewApply(state);
};

const currentState = computed(() => cleanState());

// Cuenta de filtros activos (distintos del default) para mostrar badge.
const activeCount = computed(() => {
    let n = 0;
    if (filters.value.date_preset && filters.value.date_preset !== 'last_30d') n++;
    if (filters.value.pipeline_id) n++;
    if (filters.value.owner_id) n++;
    if (filters.value.lead_source_id) n++;
    if (filters.value.currency_code) n++;
    if (filters.value.activity_type) n++;
    return n;
});

const has = (k) => props.available.includes(k);

// URL de export con los filtros actuales serializados como query string.
const buildExportUrl = (format) => {
    if (!props.exportKey) return '#';
    const params = new URLSearchParams(cleanState());
    const qs = params.toString();
    const url = route(`reports.export_${format}`, { report: props.exportKey });
    return qs ? `${url}?${qs}` : url;
};
</script>

<template>
    <Card class="report-filter-bar" :bodyStyle="{ padding: '12px 16px' }">
        <div class="rfb-row">
            <div class="rfb-left">
                <span class="rfb-label">
                    <FilterOutlined /> Filtros
                    <Tag v-if="activeCount > 0" color="blue" :bordered="false" style="margin-left: 6px">{{ activeCount }}</Tag>
                </span>

                <!-- Date range -->
                <Select
                    v-if="has('date_range')"
                    v-model:value="filters.date_preset"
                    :options="DATE_PRESETS"
                    style="min-width: 180px"
                    size="middle"
                />
                <DatePicker.RangePicker
                    v-if="has('date_range') && isCustom"
                    v-model:value="customRange"
                    format="DD/MM/YYYY"
                    style="min-width: 240px"
                />

                <!-- Pipeline -->
                <Select
                    v-if="has('pipeline_id')"
                    v-model:value="filters.pipeline_id"
                    :options="pipelines"
                    :field-names="{ label: 'name', value: 'id' }"
                    placeholder="Pipeline"
                    allow-clear
                    style="min-width: 180px"
                />

                <!-- Owner / Vendedor -->
                <Select
                    v-if="has('owner_id')"
                    v-model:value="filters.owner_id"
                    :options="owners"
                    :field-names="{ label: 'name', value: 'id' }"
                    placeholder="Vendedor"
                    allow-clear
                    show-search
                    :filter-option="(input, option) => option.name?.toLowerCase().includes(input.toLowerCase())"
                    style="min-width: 180px"
                />

                <!-- Lead source -->
                <Select
                    v-if="has('lead_source_id')"
                    v-model:value="filters.lead_source_id"
                    :options="leadSources"
                    :field-names="{ label: 'name', value: 'id' }"
                    placeholder="Origen del lead"
                    allow-clear
                    style="min-width: 180px"
                />

                <!-- Currency -->
                <Select
                    v-if="has('currency_code')"
                    v-model:value="filters.currency_code"
                    :options="currencies"
                    :field-names="{ label: 'code', value: 'code' }"
                    placeholder="Moneda"
                    allow-clear
                    style="min-width: 120px"
                />

                <!-- Activity type -->
                <Select
                    v-if="has('activity_type')"
                    v-model:value="filters.activity_type"
                    :options="activityTypes"
                    :field-names="{ label: 'label', value: 'value' }"
                    placeholder="Tipo"
                    allow-clear
                    style="min-width: 140px"
                />
            </div>

            <Space>
                <Button type="primary" @click="apply"><FilterOutlined /> Aplicar</Button>
                <Button @click="reset"><ReloadOutlined /> Limpiar</Button>
                <Dropdown v-if="exportKey">
                    <Button><DownloadOutlined /> Exportar</Button>
                    <template #overlay>
                        <Menu>
                            <MenuItem key="pdf">
                                <a :href="buildExportUrl('pdf')" target="_blank" rel="noopener">
                                    <FilePdfOutlined /> Descargar PDF
                                </a>
                            </MenuItem>
                            <MenuItem key="excel">
                                <a :href="buildExportUrl('excel')">
                                    <FileExcelOutlined /> Descargar Excel
                                </a>
                            </MenuItem>
                        </Menu>
                    </template>
                </Dropdown>
                <SavedViews
                    :module="module"
                    :current-state="currentState"
                    @apply="onViewApply"
                    @default-loaded="onDefaultLoaded"
                />
            </Space>
        </div>
    </Card>
</template>

<style scoped>
.report-filter-bar {
    margin-bottom: 16px;
    border-radius: 6px;
}
.rfb-row {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    justify-content: space-between;
}
.rfb-left {
    display: flex;
    flex-wrap: wrap;
    gap: 8px;
    align-items: center;
    flex: 1;
    min-width: 0;
}
.rfb-label {
    display: inline-flex;
    align-items: center;
    gap: 6px;
    font-size: 0.85rem;
    color: var(--color-text-muted, #595959);
    margin-right: 4px;
}
@media (max-width: 767px) {
    .rfb-row { flex-direction: column; align-items: stretch; }
    .rfb-left { width: 100%; }
}
</style>
