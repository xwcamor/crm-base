<script setup>
/**
 * DeleteFooter — botones footer estándar (Cancel + Delete) para Delete pages.
 *
 * Uso:
 *   <DeleteFooter
 *       :cancel-href="route('system_management.regions.index')"
 *       :processing="form.processing"
 *       :disabled="form.deleted_description.trim().length < 3"
 *   />
 */
import { Button, Tooltip } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { DeleteOutlined } from '@ant-design/icons-vue';

defineProps({
    cancelHref: { type: String,  required: true },
    processing: { type: Boolean, default: false },
    disabled:   { type: Boolean, default: false },
});
</script>

<template>
    <div class="form-footer">
        <Tooltip :title="$t('global.cancel_hint')">
            <Link :href="cancelHref">
                <Button size="large">{{ $t('global.cancel') }}</Button>
            </Link>
        </Tooltip>
        <Tooltip :title="$t('global.delete_hint')">
            <Button
                type="primary"
                danger
                size="large"
                html-type="submit"
                :loading="processing"
                :disabled="disabled"
            >
                <DeleteOutlined />
                {{ $t('global.delete') }}
            </Button>
        </Tooltip>
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
