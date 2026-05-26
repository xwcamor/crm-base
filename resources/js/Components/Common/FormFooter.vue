<script setup>
/**
 * FormFooter — botones footer estándar (Cancel + Save) para Create/Edit pages.
 *
 * Uso:
 *   <FormFooter
 *       :cancel-href="route('system_management.regions.index')"
 *       :is-edit="isEdit"
 *       :processing="form.processing"
 *       :create-label-key="'regions.new'"
 *   />
 *
 * Si el botón submit necesita lógica custom, usar el slot `submit` en lugar
 * del default.
 */
import { computed } from 'vue';
import { Button, Tooltip } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { SaveOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    cancelHref:     { type: String,  required: true },
    isEdit:         { type: Boolean, default: false },
    processing:     { type: Boolean, default: false },
    // Label del botón cuando es create (ej. 'regions.new'). Default: 'global.create'.
    createLabelKey: { type: String,  default: 'global.create' },
});

const submitLabel = computed(() => props.isEdit ? 'global.save_changes' : props.createLabelKey);
const submitHint  = computed(() => props.isEdit ? 'global.save_changes_hint' : 'global.create_record_hint');
</script>

<template>
    <div class="form-footer">
        <Tooltip :title="$t('global.cancel_hint')">
            <Link :href="cancelHref">
                <!-- html-type="button" CRITICO: sin esto, el browser dentro
                     de un <form> trata al <button> como type=submit y al hacer
                     click ejecuta el handler @submit en vez de navegar.
                     Sintoma: cancelar guarda en vez de descartar. -->
                <Button size="large" html-type="button">{{ $t('global.cancel') }}</Button>
            </Link>
        </Tooltip>

        <slot name="submit">
            <Tooltip :title="$t(submitHint)">
                <Button
                    type="primary"
                    size="large"
                    html-type="submit"
                    :loading="processing"
                >
                    <SaveOutlined />
                    {{ $t(submitLabel) }}
                </Button>
            </Tooltip>
        </slot>
    </div>
</template>

<style scoped>
.form-footer {
    display: flex;
    justify-content: flex-end;
    gap: 12px;
    margin-top: 24px;
    padding-top: 20px;
    border-top: 1px solid var(--color-border-soft, #E5E5E5);
}
@media (max-width: 768px) {
    .form-footer { flex-direction: column-reverse; }
    .form-footer > * { width: 100%; }
    .form-footer :deep(.ant-btn) { width: 100%; }
}
</style>
