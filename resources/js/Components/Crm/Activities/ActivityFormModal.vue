<script setup>
/**
 * ActivityFormModal — Modal con tabs por tipo de Activity (note/call/email/
 * meeting/task). Cada tab renderiza solo los campos relevantes para ese tipo.
 *
 * Se usa desde ActivitiesPanel (en Deal/Company/Contact Show) y desde el
 * Index global. El parent (activitable) se pasa via props.
 */
import { ref, reactive, computed, watch } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    Modal, Form, FormItem, Input, InputNumber, Select,
    DatePicker, Button, Radio, RadioGroup, message, Upload,
} from 'ant-design-vue';
const RadioButton = Radio.Button;
import {
    FileTextOutlined, PhoneOutlined, MailOutlined,
    TeamOutlined, CheckSquareOutlined, PaperClipOutlined,
    InboxOutlined, DeleteOutlined,
} from '@ant-design/icons-vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();

const props = defineProps({
    open: { type: Boolean, default: false },
    activitable: { type: Object, default: null }, // {type, id} — required for create
    editing: { type: Object, default: null },     // existing activity for edit
    /**
     * Lista de quotes asociadas al parent (solo cuando activitable es un Deal).
     * Si hay quotes, el modal muestra un selector "Cotización relacionada"
     * para emails/meetings. Permite trackear qué propuesta se envió.
     */
    quotes: { type: Array, default: () => [] },
    /** Pre-fill cuando el modal se abre desde "Registrar envío" en un Quote. */
    initialQuoteId: { type: Number, default: null },
});

const emit = defineEmits(['update:open', 'saved']);

const submitting = ref(false);
const errors = ref({});

const blank = () => ({
    type: 'note',
    subject: '',
    body: '',
    due_at: null,
    duration_min: null,
    outcome: null,
    location: '',
    priority: 'medium',
    related_quote_id: null,
});

const form = reactive(blank());
const attachmentFile = ref(null);

const typeOptions = [
    { value: 'note',    icon: FileTextOutlined,     labelKey: 'types.note',    color: '#6b7280' },
    { value: 'call',    icon: PhoneOutlined,        labelKey: 'types.call',    color: '#0ea5e9' },
    { value: 'email',   icon: MailOutlined,         labelKey: 'types.email',   color: '#8b5cf6' },
    { value: 'meeting', icon: TeamOutlined,         labelKey: 'types.meeting', color: '#f59e0b' },
    { value: 'task',    icon: CheckSquareOutlined,  labelKey: 'types.task',    color: '#10b981' },
];

const isEditing = computed(() => !!props.editing);

watch(() => props.open, (open) => {
    if (open) {
        errors.value = {};
        attachmentFile.value = null;
        if (props.editing) {
            Object.assign(form, blank(), {
                type: props.editing.type,
                subject: props.editing.subject ?? '',
                body: props.editing.body ?? '',
                due_at: props.editing.due_at ?? null,
                duration_min: props.editing.duration_min ?? null,
                outcome: props.editing.outcome ?? null,
                location: props.editing.location ?? '',
                priority: props.editing.priority ?? 'medium',
                related_quote_id: props.editing.related_quote_id ?? null,
            });
        } else {
            Object.assign(form, blank());
            // Si vienen pre-fills (ej. desde "Registrar envio" en Quote Show)
            if (props.initialQuoteId) {
                form.type = 'email';
                form.related_quote_id = props.initialQuoteId;
                // Pre-fill subject con referencia del quote
                const q = props.quotes.find(q => q.id === props.initialQuoteId);
                if (q) {
                    form.subject = `Envío de cotización ${q.reference ?? q.name ?? ''}`.trim();
                }
            }
        }
    }
});

// Auto-fill subject cuando se selecciona un quote (solo si subject esta vacio)
watch(() => form.related_quote_id, (newId) => {
    if (!newId || form.subject) return;
    const q = props.quotes.find(q => q.id === newId);
    if (q) {
        form.subject = `Envío de cotización ${q.reference ?? q.name ?? ''}`.trim();
    }
});

const quoteOptions = computed(() => {
    return (props.quotes ?? []).map(q => ({
        value: q.id,
        label: `${q.reference ?? '(sin ref)'} — ${q.name ?? ''} · ${q.currency ?? ''} ${q.total ?? ''}`,
    }));
});
const showQuoteSelector = computed(() => {
    // Solo cuando el parent es Deal Y el tipo es email o meeting Y hay quotes disponibles
    const isDealParent = props.activitable?.type === 'App\\Models\\Deal' || props.editing?.related_quote_id;
    const relevantType = ['email', 'meeting'].includes(form.type);
    return isDealParent && relevantType && (props.quotes?.length ?? 0) > 0;
});

function onFileBeforeUpload(file) {
    attachmentFile.value = file;
    return false; // prevenir upload automatico — lo mandamos junto al submit
}
function removeAttachment() {
    attachmentFile.value = null;
}

function close() {
    emit('update:open', false);
}

function submit() {
    submitting.value = true;
    errors.value = {};

    const payload = { ...form };
    // Limpiar campos no aplicables al tipo
    if (form.type !== 'call') { payload.outcome = null; }
    if (form.type !== 'meeting' && form.type !== 'task') { payload.due_at = null; }
    if (form.type !== 'meeting') { payload.location = null; }
    if (form.type !== 'task') { payload.priority = null; }
    if (form.type !== 'call' && form.type !== 'meeting') { payload.duration_min = null; }

    // Attachment va via FormData si hay archivo
    if (attachmentFile.value) {
        payload.attachment = attachmentFile.value;
    }

    const onError = (e) => { errors.value = e; submitting.value = false; };
    const onSuccess = () => {
        submitting.value = false;
        message.success(isEditing.value
            ? t('activities.saved')
            : t('activities.created'));
        emit('saved');
        emit('update:open', false);
    };

    if (isEditing.value) {
        // Para PUT con archivo, Laravel necesita _method=PUT en POST
        if (attachmentFile.value) {
            router.post(route('crm.activities.update', props.editing.slug), { ...payload, _method: 'PUT' },
                { preserveScroll: true, forceFormData: true, onError, onSuccess });
        } else {
            router.put(route('crm.activities.update', props.editing.slug), payload,
                { preserveScroll: true, onError, onSuccess });
        }
    } else {
        payload.activitable_type = props.activitable?.type;
        payload.activitable_id   = props.activitable?.id;
        router.post(route('crm.activities.store'), payload,
            { preserveScroll: true, forceFormData: !!attachmentFile.value, onError, onSuccess });
    }
}

const outcomeOptions = computed(() => [
    { value: 'answered',  label: t('activities.outcomes.answered') },
    { value: 'voicemail', label: t('activities.outcomes.voicemail') },
    { value: 'no_answer', label: t('activities.outcomes.no_answer') },
    { value: 'rejected',  label: t('activities.outcomes.rejected') },
]);
</script>

<template>
    <Modal
        :open="open"
        :title="isEditing ? $t('activities.edit') : $t('activities.add')"
        :width="640"
        :confirm-loading="submitting"
        :ok-text="$t('activities.save')"
        :cancel-text="$t('activities.cancel')"
        @ok="submit"
        @cancel="close"
    >
        <!-- Type selector visible y claro -->
        <div class="type-selector">
            <button
                v-for="o in typeOptions"
                :key="o.value"
                type="button"
                class="type-btn"
                :class="{ 'is-active': form.type === o.value, 'is-disabled': isEditing }"
                :style="{ '--type-color': o.color }"
                :disabled="isEditing"
                @click="form.type = o.value"
            >
                <component :is="o.icon" class="type-icon" />
                <span>{{ $t(`activities.${o.labelKey}`) }}</span>
            </button>
        </div>

        <Form layout="vertical" style="margin-top: 4px">
            <!-- Subject (no aplica a note) -->
            <FormItem v-if="form.type !== 'note'"
                :required="form.type !== 'call'"
                :validate-status="errors.subject ? 'error' : ''"
                :help="errors.subject?.[0] ?? errors.subject"
            >
                <template #label>
                    <LabelWithHelp :label="$t('activities.subject')" :help="$t('activities.subject_hint')" />
                </template>
                <Input v-model:value="form.subject" :maxlength="200" show-count
                    :placeholder="$t('activities.subject_placeholder')" autofocus />
            </FormItem>

            <!-- Body — required excepto en meeting/task (donde es opcional) -->
            <FormItem
                :required="['note', 'call', 'email'].includes(form.type)"
                :validate-status="errors.body ? 'error' : ''"
                :help="errors.body?.[0] ?? errors.body"
            >
                <template #label>
                    <LabelWithHelp :label="$t('activities.body')" :help="$t('activities.body_hint')" />
                </template>
                <Input.TextArea v-model:value="form.body" :rows="4" :maxlength="10000" show-count
                    :placeholder="$t('activities.body_placeholder')" />
            </FormItem>

            <!-- Call-specific: outcome + duration -->
            <div v-if="form.type === 'call'" class="row-two">
                <FormItem required
                    :validate-status="errors.outcome ? 'error' : ''"
                    :help="errors.outcome?.[0] ?? errors.outcome"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('activities.outcome')" :help="$t('activities.outcome_hint')" />
                    </template>
                    <Select v-model:value="form.outcome" :options="outcomeOptions" />
                </FormItem>
                <FormItem
                    :validate-status="errors.duration_min ? 'error' : ''"
                    :help="errors.duration_min?.[0] ?? errors.duration_min"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('activities.duration_min')" :help="$t('activities.duration_min_hint')" />
                    </template>
                    <InputNumber v-model:value="form.duration_min" :min="0" :max="99999" style="width: 100%" />
                </FormItem>
            </div>

            <!-- Meeting-specific: due_at + duration + location -->
            <template v-if="form.type === 'meeting'">
                <div class="row-two">
                    <FormItem required
                        :validate-status="errors.due_at ? 'error' : ''"
                        :help="errors.due_at?.[0] ?? errors.due_at"
                    >
                        <template #label>
                            <LabelWithHelp :label="$t('activities.due_at')" :help="$t('activities.due_at_hint')" />
                        </template>
                        <DatePicker v-model:value="form.due_at" show-time valueFormat="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
                    </FormItem>
                    <FormItem
                        :validate-status="errors.duration_min ? 'error' : ''"
                        :help="errors.duration_min?.[0] ?? errors.duration_min"
                    >
                        <template #label>
                            <LabelWithHelp :label="$t('activities.duration_min')" :help="$t('activities.duration_min_hint')" />
                        </template>
                        <InputNumber v-model:value="form.duration_min" :min="0" :max="99999" style="width: 100%" />
                    </FormItem>
                </div>
                <FormItem
                    :validate-status="errors.location ? 'error' : ''"
                    :help="errors.location?.[0] ?? errors.location"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('activities.location')" :help="$t('activities.location_hint')" />
                    </template>
                    <Input v-model:value="form.location" :maxlength="500"
                        :placeholder="$t('activities.location_placeholder')" />
                </FormItem>
            </template>

            <!-- Task-specific: due_at + priority -->
            <div v-if="form.type === 'task'" class="row-two">
                <FormItem required
                    :validate-status="errors.due_at ? 'error' : ''"
                    :help="errors.due_at?.[0] ?? errors.due_at"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('activities.due_at')" :help="$t('activities.due_at_hint')" />
                    </template>
                    <DatePicker v-model:value="form.due_at" show-time valueFormat="YYYY-MM-DD HH:mm:ss" style="width: 100%" />
                </FormItem>
                <FormItem
                    :validate-status="errors.priority ? 'error' : ''"
                    :help="errors.priority?.[0] ?? errors.priority"
                >
                    <template #label>
                        <LabelWithHelp :label="$t('activities.priority')" :help="$t('activities.priority_hint')" />
                    </template>
                    <RadioGroup v-model:value="form.priority">
                        <Radio value="low">{{ $t('activities.priorities.low') }}</Radio>
                        <Radio value="medium">{{ $t('activities.priorities.medium') }}</Radio>
                        <Radio value="high">{{ $t('activities.priorities.high') }}</Radio>
                    </RadioGroup>
                </FormItem>
            </div>

            <!-- Quote relacionado (solo para email/meeting cuando hay quotes del deal) -->
            <FormItem v-if="showQuoteSelector"
                :validate-status="errors.related_quote_id ? 'error' : ''"
                :help="errors.related_quote_id?.[0] ?? errors.related_quote_id"
            >
                <template #label>
                    <LabelWithHelp :label="$t('activities.related_quote')" :help="$t('activities.related_quote_hint')" />
                </template>
                <Select v-model:value="form.related_quote_id"
                    :options="quoteOptions"
                    :placeholder="$t('activities.related_quote_placeholder')"
                    allow-clear
                    show-search
                    :filter-option="(input, opt) => (opt.label ?? '').toLowerCase().includes(input.toLowerCase())"
                />
            </FormItem>

            <!-- Attachment (opcional, todos los tipos) -->
            <FormItem
                :validate-status="errors.attachment ? 'error' : ''"
                :help="errors.attachment?.[0] ?? errors.attachment"
            >
                <template #label>
                    <LabelWithHelp :label="$t('activities.attachment')" :help="$t('activities.attachment_hint')" />
                </template>
                <div v-if="attachmentFile" class="attachment-preview">
                    <PaperClipOutlined />
                    <span class="attachment-name">{{ attachmentFile.name }}</span>
                    <span class="attachment-size">({{ (attachmentFile.size / 1024).toFixed(1) }} KB)</span>
                    <Button size="small" type="text" danger @click="removeAttachment"><DeleteOutlined /></Button>
                </div>
                <div v-else-if="props.editing?.attachment_path" class="attachment-preview existing">
                    <PaperClipOutlined />
                    <a :href="`/storage/${props.editing.attachment_path}`" target="_blank">
                        {{ props.editing.attachment_name || $t('activities.download_attachment') }}
                    </a>
                    <span class="muted">{{ $t('activities.replace_attachment_hint') }}</span>
                </div>
                <Upload v-if="!attachmentFile"
                    :before-upload="onFileBeforeUpload"
                    :show-upload-list="false"
                    accept="*"
                >
                    <Button>
                        <PaperClipOutlined /> {{ $t('activities.attach_file') }}
                    </Button>
                </Upload>
            </FormItem>
        </Form>
    </Modal>
</template>

<style scoped>
.row-two {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 16px;
}
@media (max-width: 600px) {
    .row-two { grid-template-columns: 1fr; }
}

.type-selector {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 6px;
    margin: 4px 0 20px 0;
}
.type-btn {
    display: flex; flex-direction: column; align-items: center; justify-content: center;
    gap: 6px; padding: 12px 6px;
    background: var(--color-bg, #fff);
    border: 1.5px solid var(--color-border, #e5e7eb);
    border-radius: 8px;
    cursor: pointer;
    color: var(--color-text-muted, #6b7280);
    font-size: 0.82rem; font-weight: 500;
    transition: all 0.15s ease;
}
.type-btn:hover:not(.is-disabled) {
    border-color: var(--type-color);
    color: var(--type-color);
    transform: translateY(-1px);
}
.type-btn.is-active {
    background: var(--type-color);
    border-color: var(--type-color);
    color: #fff;
    box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
}
.type-btn.is-disabled { cursor: not-allowed; opacity: 0.6; }
.type-icon { font-size: 1.3rem; }

.attachment-preview {
    display: inline-flex; align-items: center; gap: 8px;
    padding: 8px 12px;
    background: var(--color-surface-alt, #f0f5ff);
    border: 1px solid var(--color-border, #d6e4ff);
    border-radius: 6px;
    margin-bottom: 8px;
    font-size: 0.875rem;
}
.attachment-preview.existing { background: #fef3c7; border-color: #fcd34d; }
.attachment-name { font-weight: 500; }
.attachment-size, .muted { color: var(--color-text-muted, #8c8c8c); font-size: 0.78rem; }
.attachment-preview a { color: var(--color-primary, #1677ff); }

@media (max-width: 600px) {
    .type-selector { grid-template-columns: repeat(3, 1fr); }
}
</style>
