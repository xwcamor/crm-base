<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import { Card, Form, FormItem, Input, InputNumber, Select, Row, Col, Table, Tag, Empty, Alert } from 'ant-design-vue';
import { CheckSquareOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
import FormFooter from '@/Components/Common/FormFooter.vue';
import LabelWithHelp from '@/Components/Common/LabelWithHelp.vue';
defineOptions({ layout: AppLayout });

const props = defineProps({
    take:             { type: Object, default: null },
    warehouseOptions: { type: Array, default: () => [] },
    statusOptions:    { type: Array, default: () => [] },
    nextReference:    { type: String, default: '' },
});

const isEdit = computed(() => !!props.take);

const form = useForm({
    reference:    props.take?.reference ?? props.nextReference ?? '',
    warehouse_id: props.take?.warehouse_id ?? null,
    status:       props.take?.status ?? 'in_progress',
    note:         props.take?.note ?? '',
    lines:        props.take?.lines?.map(l => ({
        id: l.id,
        product: l.product,
        qty_system: Number(l.qty_system),
        qty_counted: l.qty_counted !== null ? Number(l.qty_counted) : null,
        variance: Number(l.variance),
        note: l.note ?? '',
    })) ?? [],
});

const submit = () => {
    if (isEdit.value) form.put(route('business_management.stock_takes.update', props.take.slug));
    else form.post(route('business_management.stock_takes.store'));
};

const lineCols = [
    { title: 'Producto', dataIndex: ['product','name'], key: 'product' },
    { title: 'SKU', dataIndex: ['product','sku'], key: 'sku', width: 130 },
    { title: 'Sistema', dataIndex: 'qty_system', key: 'sys', align: 'right', width: 100 },
    { title: 'Contado', dataIndex: 'qty_counted', key: 'counted', align: 'right', width: 140 },
    { title: 'Varianza', dataIndex: 'variance', key: 'var', align: 'right', width: 110 },
    { title: 'Nota', dataIndex: 'note', key: 'note' },
];

const recompute = (idx) => {
    const l = form.lines[idx];
    if (l.qty_counted !== null && l.qty_counted !== '' && !isNaN(l.qty_counted)) {
        l.variance = +(Number(l.qty_counted) - Number(l.qty_system)).toFixed(4);
    } else {
        l.variance = 0;
    }
};
</script>
<template>
    <Head :title="isEdit ? 'Editar conteo' : 'Nuevo conteo'" />
    <SectionHeader :back-href="route('business_management.stock_takes.index')"
        :title="isEdit ? 'Editar conteo' : 'Nuevo conteo físico'" :subtitle="form.reference || 'Nuevo'">
        <template #icon><CheckSquareOutlined /></template>
    </SectionHeader>

    <Card>
        <Form layout="vertical" @submit.prevent="submit">
            <Row :gutter="[16, 0]">
                <Col :xs="12" :md="6"><FormItem>
                    <template #label><LabelWithHelp :label="$t('stock_takes.reference')" :help="$t('stock_takes.reference_hint')" /></template>
                    <Input v-model:value="form.reference" size="large" :maxlength="30" :disabled="isEdit" />
                </FormItem></Col>
                <Col :xs="12" :md="6"><FormItem required>
                    <template #label><LabelWithHelp :label="$t('stock_takes.status')" :help="$t('stock_takes.status_hint')" /></template>
                    <Select v-model:value="form.status" :options="statusOptions" size="large" />
                </FormItem></Col>
                <Col :xs="24" :md="12"><FormItem required :validate-status="form.errors.warehouse_id ? 'error' : ''">
                    <template #label><LabelWithHelp :label="$t('stock_takes.warehouse')" :help="$t('stock_takes.warehouse_hint')" /></template>
                    <Select v-model:value="form.warehouse_id" :options="warehouseOptions" size="large" :disabled="isEdit" />
                </FormItem></Col>
                <Col :xs="24"><FormItem>
                    <template #label><LabelWithHelp :label="$t('stock_takes.note')" :help="$t('stock_takes.note_hint')" /></template>
                    <Input.TextArea v-model:value="form.note" :rows="2" :maxlength="1000" />
                </FormItem></Col>
            </Row>

            <Alert v-if="!isEdit" type="info" show-icon style="margin-top:16px"
                message="Al guardar, el sistema genera automáticamente una línea por producto en stock del almacén seleccionado. Después podrás ingresar las cantidades contadas y al marcar 'completed' se generan los ajustes." />

            <Alert v-if="isEdit && form.status === 'completed'" type="warning" show-icon style="margin-top:16px"
                message="Este conteo ya está completado — los ajustes ya fueron generados al stock." />
        </Form>

        <Card v-if="isEdit && form.lines.length > 0" title="Líneas — ingresa la cantidad contada" :bodyStyle="{padding:0}" style="margin-top:16px">
            <Table :columns="lineCols" :data-source="form.lines" :pagination="false" size="middle" row-key="id">
                <template #bodyCell="{ column, record, index }">
                    <template v-if="column.key === 'sys'">{{ Number(record.qty_system).toFixed(2) }}</template>
                    <template v-else-if="column.key === 'counted'">
                        <InputNumber v-model:value="form.lines[index].qty_counted" :min="0" :step="1" style="width:100%" @change="recompute(index)" :disabled="take?.status === 'completed'" />
                    </template>
                    <template v-else-if="column.key === 'var'">
                        <strong :class="Math.abs(Number(form.lines[index].variance)) < 0.001 ? '' : (Number(form.lines[index].variance) > 0 ? 'text-success' : 'text-danger')">
                            {{ form.lines[index].variance > 0 ? '+' : '' }}{{ Number(form.lines[index].variance).toFixed(2) }}
                        </strong>
                    </template>
                    <template v-else-if="column.key === 'note'">
                        <Input v-model:value="form.lines[index].note" :maxlength="500" :disabled="take?.status === 'completed'" placeholder="Observación (opcional)" />
                    </template>
                </template>
                <template #emptyText><Empty description="Sin líneas" /></template>
            </Table>
        </Card>

        <FormFooter :cancel-href="route('business_management.stock_takes.index')" :is-edit="isEdit" :processing="form.processing" create-label-key="global.save_changes" />
    </Card>
</template>
<style scoped>.text-success{color:#389e0d}.text-danger{color:#d4380d}</style>
