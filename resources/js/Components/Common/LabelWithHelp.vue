<script setup>
/**
 * Label de un FormItem que agrega un icono ? con tooltip de ayuda.
 *
 * NO maneja el asterisco de "requerido" — AntDesign FormItem ya lo agrega
 * automáticamente cuando se le pasa `required`. Duplicar el asterisco
 * (poniendolo manual aca + el de FormItem) genera "* * Label" en pantalla.
 *
 * Uso correcto:
 *   <FormItem required>                        ← AntDesign pone el *
 *     <template #label>
 *       <LabelWithHelp :label="..." :help="..." />
 *     </template>
 *   </FormItem>
 */
import { Tooltip } from 'ant-design-vue';
import { QuestionCircleOutlined } from '@ant-design/icons-vue';

defineProps({
    label: { type: String, required: true },
    help:  { type: String, default: '' },
});
</script>

<template>
    <span class="label-with-help">
        <span>{{ label }}</span>
        <Tooltip v-if="help" :title="help" placement="top">
            <QuestionCircleOutlined class="help-icon" />
        </Tooltip>
    </span>
</template>

<style scoped>
.label-with-help {
    display: inline-flex;
    align-items: center;
    gap: 6px;
}
.help-icon {
    color: var(--color-text-muted, #8c8c8c);
    font-size: 0.85rem;
    cursor: help;
    transition: color 0.15s;
}
.help-icon:hover {
    color: var(--color-primary, #1677ff);
}
</style>
