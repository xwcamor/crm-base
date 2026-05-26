<script setup>
/**
 * RecordMetaFooter — linea de metadata "Creado por X · Editado por Y"
 * que se muestra al pie de cada Show.
 *
 * Datos que toma:
 *   - record: el objeto principal (company, contact, product, etc.). Espera
 *             opcionalmente: creator.name, created_at, updated_at, updater.name.
 *   - activity: array de audit logs (opcional). Si esta presente, el
 *               "Última edición" se deriva del log mas reciente con event
 *               = 'updated' (incluye el nombre del usuario). Si no, cae
 *               al updated_at del record sin nombre.
 *
 * Visible para todos los roles. La info que muestra (timestamps + nombre
 * del creador) ya esta disponible en el modelo para todos.
 *
 * Para admin/super, ademas se enriquece con el nombre del ultimo editor
 * derivado del audit log (que ellos ya reciben).
 */
import { computed } from 'vue';
import { Tooltip } from 'ant-design-vue';
import { ClockCircleOutlined, UserOutlined } from '@ant-design/icons-vue';
import dayjs from 'dayjs';
import relativeTime from 'dayjs/plugin/relativeTime';
import 'dayjs/locale/es';
import { useI18n } from '@/Plugins/i18n';
import { useDateFormat } from '@/Composables/useDateFormat';

dayjs.extend(relativeTime);

const props = defineProps({
    record:   { type: Object, required: true },
    activity: { type: Array,  default: () => [] },
});

const { t } = useI18n();
const { formatDateTimeFull } = useDateFormat();

const creatorName = computed(() => props.record.creator?.name ?? null);
const createdAt   = computed(() => props.record.created_at ?? null);
const updatedAt   = computed(() => props.record.updated_at ?? null);

const lastUpdateLog = computed(() => {
    if (!props.activity?.length) return null;
    return props.activity.find(l => l.event === 'updated') ?? null;
});

const updaterName = computed(() => {
    if (lastUpdateLog.value?.user?.name) return lastUpdateLog.value.user.name;
    return props.record.updater?.name ?? null;
});

const wasEdited = computed(() => {
    if (!createdAt.value || !updatedAt.value) return false;
    return dayjs(updatedAt.value).diff(dayjs(createdAt.value), 'second') > 5;
});

const fmtRelative = (iso) => iso ? dayjs(iso).fromNow() : '';
const fmtAbsolute = (iso) => iso ? formatDateTimeFull(iso) : '';
</script>

<template>
    <div v-if="createdAt" class="record-meta-footer">
        <span class="record-meta-footer__part">
            <UserOutlined />
            {{ t('global.created_by_short') }}
            <strong v-if="creatorName">{{ creatorName }}</strong>
            <span v-else class="muted">—</span>
            <Tooltip :title="fmtAbsolute(createdAt)">
                <span class="muted">· {{ fmtRelative(createdAt) }}</span>
            </Tooltip>
        </span>

        <span v-if="wasEdited" class="record-meta-footer__sep">·</span>

        <span v-if="wasEdited" class="record-meta-footer__part">
            <ClockCircleOutlined />
            {{ updaterName ? t('global.updated_by_short') : t('global.updated_at_short') }}
            <strong v-if="updaterName">{{ updaterName }}</strong>
            <Tooltip :title="fmtAbsolute(updatedAt)">
                <span class="muted">{{ updaterName ? '' : ':' }} {{ fmtRelative(updatedAt) }}</span>
            </Tooltip>
        </span>

        <span v-else-if="createdAt" class="record-meta-footer__part muted">
            <span class="record-meta-footer__sep">·</span>
            {{ t('global.never_modified') }}
        </span>
    </div>
</template>

<style scoped>
.record-meta-footer {
    margin-top: 24px;
    padding: 10px 14px;
    border-top: 1px solid var(--color-border-soft, #f0f0f0);
    color: var(--color-text-muted, #8c8c8c);
    font-size: 0.78rem;
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 8px;
    line-height: 1.4;
}
.record-meta-footer__part {
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.record-meta-footer__part :deep(.anticon) {
    font-size: 0.85rem;
}
.record-meta-footer__part strong {
    color: var(--color-text-strong, #262626);
    font-weight: 600;
}
.record-meta-footer__sep {
    color: var(--color-border, #d9d9d9);
}
.muted { color: var(--color-text-muted, #8c8c8c); }
</style>
