<script setup>
/**
 * QuickNoteWidget — input rapido de notas para Show pages del CRM.
 *
 * Aparece SIEMPRE visible arriba de las tabs (no escondido en una tab).
 * Patron HubSpot/Pipedrive: el equipo de ventas anota algo rapido del
 * cliente y queda en el feed de actividades.
 *
 * - Textarea expandible con boton "Agregar nota".
 * - Submit -> POST /crm/activities con type='note' + body + activitable.
 * - Tras crear: Inertia hace reload de la pagina (props se refrescan,
 *   incluyendo activities count en tabs y el ActivitiesPanel).
 *
 * Uso:
 *   <QuickNoteWidget
 *     :activitable="{ type: 'App\\Models\\Company', id: company.id }"
 *     :can-create="canManageActivities"
 *   />
 */
import { ref } from 'vue';
import { router } from '@inertiajs/vue3';
import { Card, Input, Button, message } from 'ant-design-vue';
import { EditOutlined } from '@ant-design/icons-vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

const props = defineProps({
    activitable: { type: Object, required: true },
    canCreate:   { type: Boolean, default: false },
});

const note = ref('');
const submitting = ref(false);

function submit() {
    const body = note.value.trim();
    if (!body || submitting.value) return;
    submitting.value = true;

    router.post(
        route('crm.activities.store'),
        {
            type:              'note',
            body:              body,
            activitable_type:  props.activitable.type,
            activitable_id:    props.activitable.id,
            completed_at:      new Date().toISOString(),
        },
        {
            preserveScroll: true,
            preserveState: false,  // recarga la pagina para refrescar el feed
            onSuccess: () => {
                note.value = '';
                message.success(t('activities.quick_note_success'));
            },
            onError: () => {
                message.error(t('activities.quick_note_error'));
            },
            onFinish: () => {
                submitting.value = false;
            },
        }
    );
}
</script>

<template>
    <Card v-if="canCreate" :bodyStyle="{ padding: '12px 16px' }" class="quick-note-widget">
        <div class="qn-header">
            <EditOutlined />
            <span class="qn-title">{{ $t('activities.quick_note_title') }}</span>
        </div>
        <Input.TextArea
            v-model:value="note"
            :placeholder="$t('activities.quick_note_placeholder')"
            :auto-size="{ minRows: 2, maxRows: 6 }"
            :maxlength="10000"
            @keydown.ctrl.enter="submit"
            @keydown.meta.enter="submit"
        />
        <div class="qn-actions">
            <span class="qn-hint">{{ $t('activities.quick_note_hint') }}</span>
            <Button type="primary" :loading="submitting" :disabled="!note.trim()" @click="submit">
                {{ $t('activities.quick_note_save') }}
            </Button>
        </div>
    </Card>
</template>

<style scoped>
.quick-note-widget {
    margin-bottom: 16px;
    border-radius: 6px;
    border-left: 3px solid var(--color-primary, #0A6ED1);
}
.qn-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 8px;
    font-size: 0.8125rem;
    color: var(--color-text-muted, #666);
    font-weight: 500;
}
.qn-title { font-size: 0.875rem; }
.qn-actions {
    display: flex;
    justify-content: space-between;
    align-items: center;
    margin-top: 8px;
}
.qn-hint {
    font-size: 0.75rem;
    color: var(--color-text-muted, #888);
}
</style>
