<script setup>
import { ref, computed, onMounted, onBeforeUnmount } from 'vue';
import { Table, Pagination, Empty, Checkbox, Button } from 'ant-design-vue';
import { RightOutlined, LeftOutlined } from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

/**
 * ResponsiveTable — desktop: Ant Design Table | mobile: card list
 *
 * Each column may declare a `mobile` config:
 *   { mobile: { role: 'title' | 'subtitle' | 'status' | 'meta' | 'actions' | 'hidden' } }
 *
 * Roles:
 *   - title:    big text at the top of the card (typically the entity's name)
 *   - subtitle: secondary line under the title
 *   - status:   small element on the top-right (typically a Tag with state)
 *   - meta:     small label-value rows in the body
 *   - actions:  buttons at the bottom of the card
 *   - hidden:   not rendered on mobile
 *
 * Slots:
 *   bodyCell    — same as Ant Design Table (used for both desktop and mobile)
 */

const props = defineProps({
    columns:     { type: Array, required: true },
    dataSource:  { type: Array, required: true },
    pagination:  { type: [Object, Boolean], default: false },
    rowKey:      { type: String, default: 'id' },
    loading:     { type: Boolean, default: false },
    size:        { type: String, default: 'middle' },
    scroll:      { type: Object, default: () => ({ x: 800 }) },
    rowSelection: { type: Object, default: null }, // pass through Ant Design rowSelection
    rowClassName: { type: Function, default: null }, // (record) => string, para highlight de rows
});

const emit = defineEmits(['change', 'row-click', 'update:selectedRowKeys']);

// Mobile selection: a Set of row keys selected via card-level checkbox.
import { computed as _computed } from 'vue';
const mobileSelectedKeys = _computed(() => props.rowSelection?.selectedRowKeys ?? []);
const toggleMobileSelect = (record) => {
    if (!props.rowSelection) return;
    const key = record[props.rowKey];
    const current = [...mobileSelectedKeys.value];
    const idx = current.indexOf(key);
    if (idx === -1) current.push(key); else current.splice(idx, 1);
    props.rowSelection.onChange?.(current, props.dataSource.filter(r => current.includes(r[props.rowKey])));
};
const isMobileSelected = (record) => mobileSelectedKeys.value.includes(record[props.rowKey]);

// ─── Responsive ──────────────────────────────────────────────────────────
const isMobile = ref(false);
const checkMobile = () => { isMobile.value = window.innerWidth < 768; };
onMounted(() => { checkMobile(); window.addEventListener('resize', checkMobile); });
onBeforeUnmount(() => window.removeEventListener('resize', checkMobile));

// ─── Column role helpers ──────────────────────────────────────────────────
const colsByRole = computed(() => {
    const roles = { title: null, subtitle: null, status: null, pin: null, meta: [], actions: null, hidden: [] };
    props.columns.forEach(c => {
        const role = c.mobile?.role;
        if (role === 'title')    roles.title    = c;
        else if (role === 'subtitle') roles.subtitle = c;
        else if (role === 'status')   roles.status   = c;
        // 'pin': elemento toggle (favorito, etc.) en el top-right del card.
        // Patrón Gmail: la estrella vive arriba a la derecha, siempre visible.
        else if (role === 'pin')      roles.pin      = c;
        else if (role === 'actions')  roles.actions  = c;
        else if (role === 'hidden')   roles.hidden.push(c);
        else if (role === 'meta')     roles.meta.push(c);
        // No mobile config: smart defaults
        else if (!roles.title && c.key !== 'id' && c.key !== 'actions') {
            roles.title = c;
        } else if (c.key !== 'actions' && c.key !== 'id') {
            roles.meta.push(c);
        }
    });
    return roles;
});

// ─── Mobile pagination ───────────────────────────────────────────────────
const onPageChange = (page, pageSize) => {
    emit('change', { current: page, pageSize }, {}, {});
};

// Total de páginas (computed para reactividad). Si pagination es false/null
// o no tiene total, devuelve 0 → la paginación no se muestra.
const totalPages = computed(() => {
    if (!props.pagination || !props.pagination.total) return 0;
    return Math.max(1, Math.ceil(props.pagination.total / props.pagination.pageSize));
});

const goToPrevPage = () => {
    if (!props.pagination) return;
    const target = Math.max(1, props.pagination.current - 1);
    onPageChange(target, props.pagination.pageSize);
};
const goToNextPage = () => {
    if (!props.pagination) return;
    const target = Math.min(totalPages.value, props.pagination.current + 1);
    onPageChange(target, props.pagination.pageSize);
};

// ─── Click on card → row-click event ─────────────────────────────────────
// Mirrors the desktop onRowClick logic: skip when the click came from a
// button, link, or Ant Design dropdown trigger inside the card. Avoids
// having the row-click fire when the user interacts with action buttons.
const onCardClick = (event, record) => {
    const skipSelectors = 'button, a, .ant-dropdown-trigger';
    if (event.target.closest(skipSelectors)) return;
    emit('row-click', record);
};

// ─── Desktop row click + keyboard nav → row-click event ────────────────
// Ant Design Table necesita customRow para inyectar handlers. Skip si el
// evento vino del checkbox de selección o de un button/link adentro de la
// fila (esos siguen funcionando sin disparar el drawer).
// Accesibilidad: cada fila es focuseable (tabindex=0) y responde a Enter
// y Space — patrón WCAG estándar para filas tappeables.
const onRowClick = (record) => ({
    tabindex: 0,
    onClick: (event) => {
        const skipSelectors = '.ant-table-selection-column, button, a';
        if (event.target.closest(skipSelectors)) return;
        emit('row-click', record);
    },
    onKeydown: (event) => {
        if (event.key !== 'Enter' && event.key !== ' ') return;
        const skipSelectors = '.ant-table-selection-column, button, a, input, textarea, select';
        if (event.target.closest(skipSelectors)) return;
        event.preventDefault();
        emit('row-click', record);
    },
});
</script>

<template>
    <!-- DESKTOP: Ant Design Table.
         `sticky` prop nativo de antd: el thead queda fijo al scrollear.
         offsetHeader = altura de la .shell-bar (44px) que SÍ es sticky en
         AppLayout — sin offset, el thead quedaría tapado por la shell-bar. -->
    <Table
        v-if="!isMobile"
        :columns="props.columns"
        :dataSource="props.dataSource"
        :pagination="props.pagination"
        :rowKey="props.rowKey"
        :loading="props.loading"
        :size="props.size"
        :scroll="props.scroll"
        :row-selection="props.rowSelection"
        :row-class-name="props.rowClassName ?? undefined"
        :custom-row="onRowClick"
        :sticky="{ offsetHeader: 44 }"
        @change="(...args) => emit('change', ...args)"
    >
        <template #bodyCell="slotProps">
            <slot name="bodyCell" v-bind="slotProps" :isMobile="false" />
        </template>
        <template #emptyText>
            <slot name="empty">
                <Empty description="Sin resultados" />
            </slot>
        </template>
    </Table>

    <!-- MOBILE: card list -->
    <div v-else class="rt-mobile-wrap">
        <div
            v-if="props.dataSource.length === 0 && !props.loading"
            class="rt-empty"
        >
            <slot name="empty">
                <Empty description="Sin resultados" />
            </slot>
        </div>

        <!-- Lista unificada con dividers internos. La paginación va FUERA
             de este contenedor para no quedar encerrada por el border-radius
             y overflow:hidden. -->
        <div v-if="props.dataSource.length > 0" class="rt-mobile">
        <div
            v-for="record in props.dataSource"
            :key="record[props.rowKey]"
            class="rt-card"
            :class="{ 'rt-card--selected': isMobileSelected(record) }"
            @click="onCardClick($event, record)"
        >
            <!-- Top row: title + status -->
            <div class="rt-card__top">
                <Checkbox
                    v-if="props.rowSelection"
                    :checked="isMobileSelected(record)"
                    @click.stop
                    @change="toggleMobileSelect(record)"
                    class="rt-card__check"
                />
                <div class="rt-card__title-block">
                    <div v-if="colsByRole.title" class="rt-card__title">
                        <slot
                            name="bodyCell"
                            :column="colsByRole.title"
                            :record="record"
                            :index="0"
                            :text="record[colsByRole.title.dataIndex]"
                            :isMobile="true"
                        >
                            {{ record[colsByRole.title.dataIndex] }}
                        </slot>
                    </div>
                    <div v-if="colsByRole.subtitle" class="rt-card__subtitle">
                        <slot
                            name="bodyCell"
                            :column="colsByRole.subtitle"
                            :record="record"
                            :index="0"
                            :text="record[colsByRole.subtitle.dataIndex]"
                            :isMobile="true"
                        >
                            {{ record[colsByRole.subtitle.dataIndex] }}
                        </slot>
                    </div>
                </div>
                <div v-if="colsByRole.status" class="rt-card__status" @click.stop>
                    <slot
                        name="bodyCell"
                        :column="colsByRole.status"
                        :record="record"
                        :index="0"
                        :text="record[colsByRole.status.dataIndex]"
                        :isMobile="true"
                    />
                </div>
                <!-- Pin (favorito): toggle siempre visible en top-right. Patrón Gmail. -->
                <div v-if="colsByRole.pin" class="rt-card__pin" @click.stop>
                    <slot
                        name="bodyCell"
                        :column="colsByRole.pin"
                        :record="record"
                        :index="0"
                        :text="record[colsByRole.pin.dataIndex]"
                        :isMobile="true"
                    />
                </div>
                <!-- Chevron indica que la card es tappable (abre el drawer).
                     Patrón estándar iOS/Material para listas navegables. -->
                <RightOutlined class="rt-card__chevron" />
            </div>

            <!-- Meta rows: label-value pairs -->
            <div v-if="colsByRole.meta.length > 0" class="rt-card__meta">
                <div
                    v-for="col in colsByRole.meta"
                    :key="col.key"
                    class="rt-card__meta-row"
                >
                    <span class="rt-card__meta-label">{{ col.title }}</span>
                    <span class="rt-card__meta-value">
                        <slot
                            name="bodyCell"
                            :column="col"
                            :record="record"
                            :index="0"
                            :text="record[col.dataIndex]"
                            :isMobile="true"
                        >
                            {{ record[col.dataIndex] }}
                        </slot>
                    </span>
                </div>
            </div>

            <!-- Actions row -->
            <div v-if="colsByRole.actions" class="rt-card__actions" @click.stop>
                <slot
                    name="bodyCell"
                    :column="colsByRole.actions"
                    :record="record"
                    :index="0"
                    :isMobile="true"
                />
            </div>
        </div>
        </div>

        <!-- Mobile pagination — controles custom claros y grandes.
             El modo `simple` de Ant Design renderiza una UI minimal que
             pasa desapercibida; preferimos botones explícitos prev/next +
             indicador "Página X de Y" + total como contexto. -->
        <div v-if="props.pagination && props.pagination.total > 0" class="rt-pagination">
            <Button
                :disabled="props.pagination.current <= 1"
                class="rt-pagination__btn"
                @click="goToPrevPage"
            >
                <LeftOutlined />
                <span class="rt-pagination__btn-label">{{ $t('global.previous') }}</span>
            </Button>

            <div class="rt-pagination__info">
                <span class="rt-pagination__page">
                    {{ $t('global.page') }} <strong>{{ props.pagination.current }}</strong> {{ $t('global.of') }} <strong>{{ totalPages }}</strong>
                </span>
                <span class="rt-pagination__total">
                    {{ props.pagination.total }} {{ props.pagination.total === 1 ? $t('global.record') : $t('global.records') }}
                </span>
            </div>

            <Button
                :disabled="props.pagination.current >= totalPages"
                class="rt-pagination__btn"
                @click="goToNextPage"
            >
                <span class="rt-pagination__btn-label">{{ $t('global.next') }}</span>
                <RightOutlined />
            </Button>
        </div>
    </div>
</template>

<style scoped>
/* Wrapper exterior: lista + paginación. Sin border ni radius — los aplica
   solo el card interno para mantener el look "single card with dividers". */
.rt-mobile-wrap {
    display: flex;
    flex-direction: column;
    gap: 12px;
    min-width: 0;
    max-width: 100%;
}

/* Mobile cards container — single card-style con una única línea divisoria
   entre registros (border-bottom del .rt-card). Sin bordes internos
   (meta/actions): un registro se ve como un bloque cohesivo. */
.rt-mobile {
    display: flex;
    flex-direction: column;
    background: var(--color-surface);
    border: 1px solid var(--color-border-strong);
    border-radius: 6px;
    overflow: hidden;
}

.rt-empty { padding: 32px 16px; }

/* Single row inside the unified card. UNA línea separadora entre registros,
   sin bordes internos para meta/actions. */
.rt-card {
    position: relative;
    background: var(--color-surface);
    border: 0;
    /* `!important` para vencer la regla de Tailwind v4 preflight que aplica
       `border-color: currentColor` a todos los elementos. El color viene del
       token `--color-divider` (slate-500, contraste 3.7 sobre blanco) que
       sigue siendo customizable desde app.css. */
    border-bottom: 1px solid var(--color-divider) !important;
    border-radius: 0;
    padding: 12px 14px;
    cursor: pointer;
    transition: background 0.15s ease;
    display: flex;
    flex-direction: column;
    gap: 8px;
    /* min-width: 0 + overflow: hidden defienden de hijos que intentan
       expandirse mas alla del ancho del card. Combinado con word-break
       en title/subtitle, nada se escapa a la derecha. */
    min-width: 0;
    overflow: hidden;
}
.rt-card:last-child { border-bottom: 0 !important; }
.rt-card:active { background: var(--color-surface-hover); }

/* Selección con stripe lateral en lugar de fondo intenso.
   - Fondo blanco preservado (texto legible, tags no chocan con tinte).
   - Stripe de 4px a la izquierda con el color primary (señal clara).
   - Tinte sutil de fondo (~7% opacidad) que no compromete contraste.
   Patrón Gmail/Outlook/Linear: el accent comunica selección sin gritar. */
.rt-card--selected {
    background: var(--color-surface);
    box-shadow: inset 4px 0 0 0 var(--color-primary);
}
.rt-card--selected::before {
    content: '';
    position: absolute;
    inset: 0;
    background: var(--color-primary);
    opacity: 0.06;
    pointer-events: none;
}
.rt-card--selected:active { background: var(--color-surface-hover); }
.rt-card__check { padding-top: 2px; position: relative; z-index: 1; }

/* Top row: title + status. min-width: 0 evita que un hijo (status tag con
   texto largo) empuje el row mas alla del padre. overflow: hidden recorta
   por si algo se escapa (defense in depth). */
.rt-card__top {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 12px;
    position: relative;
    z-index: 1;
    min-width: 0;
    overflow: hidden;
}
.rt-card__title-block { flex: 1; min-width: 0; }
.rt-card__title {
    font-size: 1rem;
    font-weight: 600;
    color: var(--color-text);
    line-height: 1.3;
    min-width: 0;
    /* Strings largos (nombres, emails sin espacios) WRAPEAN a multi-linea
       en lugar de truncarse con "...". Patron Android/iOS list cell:
       toda la info visible. `overflow-wrap: anywhere` rompe cualquier
       caracter si no hay espacios (mejor que break-word para emails). */
    word-break: break-word;
    overflow-wrap: anywhere;
}
.rt-card__subtitle {
    font-size: 0.8125rem;
    color: var(--color-text-muted);
    margin-top: 2px;
    min-width: 0;
    word-break: break-word;
    overflow-wrap: anywhere;
}
.rt-card__status { flex-shrink: 0; }
.rt-card__pin {
    flex-shrink: 0;
    display: inline-flex;
    align-items: center;
    margin-top: -2px;
}

/* Chevron — sutil, gris claro. Indica navegación sin gritar. */
.rt-card__chevron {
    color: var(--color-icon-mute);
    font-size: 0.75rem;
    flex-shrink: 0;
    margin-top: 4px;
    transition: color 0.12s ease, transform 0.12s ease;
}
.rt-card:active .rt-card__chevron {
    color: var(--color-primary);
    transform: translateX(2px);
}

/* Meta rows — sin border-top (única separación es entre cards). */
.rt-card__meta {
    display: flex;
    flex-direction: column;
    gap: 4px;
    position: relative;
    z-index: 1;
}
.rt-card__meta-row {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    gap: 12px;
    font-size: 0.8125rem;
    min-width: 0;
    flex-wrap: wrap;
}
.rt-card__meta-label {
    color: var(--color-text-muted);
    font-weight: 500;
    white-space: nowrap;
}
.rt-card__meta-value {
    color: var(--color-text);
    text-align: right;
    word-break: break-word;
    overflow-wrap: anywhere;
    min-width: 0;
}

/* Actions — sin border-top, separados solo por gap. */
.rt-card__actions {
    display: flex;
    justify-content: stretch;
    align-items: center;
    gap: 8px;
    position: relative;
    z-index: 1;
}
.rt-card__actions :deep(.ant-space) { width: 100%; }
.rt-card__actions :deep(.ant-space-item) { flex: 1; }
.rt-card__actions :deep(.ant-btn) { width: 100%; }

/* Pagination — vive fuera de la card unificada. Layout 3-zonas en mobile:
   [Anterior] [info centrado] [Siguiente]. Botones grandes touch-friendly. */
.rt-pagination {
    display: flex;
    justify-content: space-between;
    align-items: center;
    gap: 8px;
    padding: 12px 14px;
    background: var(--color-surface);
    border: 1px solid var(--color-border-strong);
    border-radius: 6px;
    font-size: 0.8125rem;
}
.rt-pagination__btn {
    height: 36px !important;
    display: inline-flex !important;
    align-items: center;
    gap: 6px;
}
.rt-pagination__btn-label { font-size: 0.8125rem; }
.rt-pagination__info {
    display: flex;
    flex-direction: column;
    align-items: center;
    line-height: 1.2;
    flex: 1;
    text-align: center;
}
.rt-pagination__page {
    color: var(--color-text);
    font-size: 0.8125rem;
}
.rt-pagination__page strong { color: var(--color-primary); font-weight: 600; }
.rt-pagination__total {
    color: var(--color-text-muted);
    font-size: 0.7rem;
    margin-top: 2px;
}

/* Pantallas muy chicas: ocultar texto de los botones, dejar solo iconos */
@media (max-width: 380px) {
    .rt-pagination__btn-label { display: none; }
    .rt-pagination__btn { width: 36px; padding: 0 !important; justify-content: center; }
}

/* (Antes había aquí media queries para landscape phone con grid de 2 columnas.
   Se removió porque RotatePortraitOverlay impide ese caso — los celulares en
   landscape ven el overlay "rotá tu celu", nunca esta vista.) */
</style>

