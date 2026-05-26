<script setup>
import { ref } from 'vue';
import { Head, Link, router } from '@inertiajs/vue3';
import { Card, Table, Input, Space, Empty, Button, Popconfirm, Modal, Form, FormItem, message, Alert } from 'ant-design-vue';
import { DeleteOutlined, RollbackOutlined, ExclamationCircleOutlined } from '@ant-design/icons-vue';

defineOptions({ inheritAttrs: false });

const props = defineProps({
    title:    { type: String, required: true },
    subtitle: { type: String, default: 'Registros eliminados (super only). Puedes restaurar o eliminar permanentemente.' },
    items:    { type: Object, required: true },
    filters:  { type: Object, required: true },
    meta:     { type: Object, required: true },
});

const forceOpen = ref(false);
const forceTarget = ref(null);
const forceConfirm = ref('');

const openForce = (rec) => { forceTarget.value = rec; forceConfirm.value = ''; forceOpen.value = true; };
const onForceDelete = () => {
    if (forceConfirm.value.trim() !== forceTarget.value[props.meta.display_col]) {
        message.error('La confirmación no coincide.');
        return;
    }
    router.delete(route(props.meta.route_prefix + '.force_delete', forceTarget.value.slug), {
        data: { name_confirmation: forceConfirm.value },
        onSuccess: () => { forceOpen.value = false; },
    });
};
const onRestore = (rec) => router.post(route(props.meta.route_prefix + '.restore', rec.slug));
const onSearch = (e) => router.get(route(props.meta.route_prefix + '.trash'), { name: e.target.value }, { preserveState: true, replace: true });

const columns = [
    { title: 'Registro', dataIndex: props.meta.display_col, key: 'name' },
    { title: 'Eliminado', dataIndex: 'deleted_at', key: 'deleted_at', width: 180 },
    { title: 'Por', dataIndex: ['deleter','name'], key: 'deleter', width: 200 },
    { title: 'Motivo', dataIndex: 'deleted_description', key: 'reason' },
    { title: '', dataIndex: 'slug', key: 'actions', width: 180 },
];
</script>

<template>
    <Head :title="title + ' — Papelera'" />

    <div>
        <Alert type="warning" show-icon style="margin-bottom: 16px"
            message="Papelera (super only)"
            description="Estos registros están soft-deleted. Puedes restaurarlos o eliminarlos permanentemente. La eliminación permanente NO se puede deshacer." />

        <Link :href="route(meta.route_prefix + '.index')"><Button style="margin-bottom: 16px">← Volver al listado</Button></Link>

        <Card :title="title + ' eliminados'">
            <Space style="margin-bottom: 16px"><Input :value="filters.name" @change="onSearch" placeholder="Buscar" allow-clear style="width:240px" /></Space>

            <Table :columns="columns" :data-source="items.data"
                :pagination="{ current: items.current_page, pageSize: items.per_page, total: items.total, showSizeChanger: false }"
                @change="(p) => router.get(route(meta.route_prefix + '.trash'), { ...filters, page: p.current }, { preserveState: true })"
                row-key="id" size="middle">
                <template #bodyCell="{ column, record }">
                    <template v-if="column.key === 'actions'">
                        <Space>
                            <Popconfirm title="¿Restaurar este registro?" @confirm="onRestore(record)">
                                <Button size="small" type="primary"><RollbackOutlined /> Restaurar</Button>
                            </Popconfirm>
                            <Button danger size="small" @click="openForce(record)"><DeleteOutlined /> Eliminar</Button>
                        </Space>
                    </template>
                </template>
                <template #emptyText><Empty description="Papelera vacía" /></template>
            </Table>
        </Card>

        <Modal v-model:open="forceOpen" :title="'Eliminar permanentemente'" @ok="onForceDelete" ok-text="Eliminar" cancel-text="Cancelar" :ok-button-props="{ danger: true }">
            <p><ExclamationCircleOutlined style="color:#d4380d" /> Esta acción <strong>no se puede deshacer</strong>.</p>
            <p>Para confirmar, escribe exactamente: <strong>{{ forceTarget?.[meta.display_col] }}</strong></p>
            <Form layout="vertical">
                <FormItem>
                    <Input v-model:value="forceConfirm" placeholder="Escribe el nombre/referencia" autofocus />
                </FormItem>
            </Form>
        </Modal>
    </div>
</template>
