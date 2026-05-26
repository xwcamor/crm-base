<script setup>
import { ref } from 'vue';
import { Head, router, useForm } from '@inertiajs/vue3';
import { Card, Table, Input, Space, Empty, Button, Popconfirm, Modal, Form, FormItem, Switch, Select } from 'ant-design-vue';
import { ApartmentOutlined, PlusOutlined, EditOutlined, DeleteOutlined } from '@ant-design/icons-vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import SectionHeader from '@/Components/Common/SectionHeader.vue';
defineOptions({ layout: AppLayout });

const props = defineProps({ items: { type: Object, required: true }, filters: { type: Object, required: true }, parentOptions: { type: Array, default: () => [] } });

const open = ref(false);
const editing = ref(null);
const form = useForm({ name: '', parent_id: null, is_active: true });

const openCreate = () => { editing.value = null; form.reset(); form.is_active = true; open.value = true; };
const openEdit = (rec) => { editing.value = rec; form.name = rec.name; form.parent_id = rec.parent_id; form.is_active = !!rec.is_active; open.value = true; };
const save = () => {
    if (editing.value) form.put(route('business_management.industries.update', editing.value.id), { onSuccess: () => open.value = false });
    else form.post(route('business_management.industries.store'), { onSuccess: () => open.value = false });
};
const remove = (rec) => router.delete(route('business_management.industries.destroy', rec.id));

const columns = [
    { title: 'Nombre', dataIndex: 'name', key: 'name' },
    { title: 'Padre', dataIndex: ['parent','name'], key: 'parent', width: 250 },
    { title: '', dataIndex: 'id', key: 'actions', width: 100 },
];
const onSearch = (e) => router.get(route('business_management.industries.index'), { name: e.target.value }, { preserveState: true, replace: true });
</script>
<template>
    <Head title="Industrias" />
    <SectionHeader title="Industrias" subtitle="Catálogo global de industrias (super only).">
        <template #icon><ApartmentOutlined /></template>
        <template #actions><Button type="primary" @click="openCreate"><PlusOutlined /> Nueva</Button></template>
    </SectionHeader>
    <Card>
        <Space style="margin-bottom: 16px"><Input :value="filters.name" @change="onSearch" placeholder="Buscar" allow-clear style="width:240px" /></Space>
        <Table :columns="columns" :data-source="items.data" :pagination="{ current: items.current_page, pageSize: items.per_page, total: items.total, showSizeChanger: false }"
            @change="(p) => router.get(route('business_management.industries.index'), { ...filters, page: p.current }, { preserveState: true })" row-key="id" size="middle">
            <template #bodyCell="{ column, record }">
                <template v-if="column.key === 'parent'">{{ record.parent?.name ?? '—' }}</template>
                <template v-else-if="column.key === 'actions'">
                    <Space>
                        <a @click="openEdit(record)"><EditOutlined /></a>
                        <Popconfirm title="¿Eliminar?" @confirm="remove(record)"><DeleteOutlined style="color:#d4380d; cursor:pointer" /></Popconfirm>
                    </Space>
                </template>
            </template>
            <template #emptyText><Empty description="Sin industrias" /></template>
        </Table>
    </Card>

    <Modal v-model:open="open" :title="editing ? 'Editar industria' : 'Nueva industria'" @ok="save" :confirm-loading="form.processing">
        <Form layout="vertical">
            <FormItem label="Nombre" required :validate-status="form.errors.name ? 'error' : ''" :help="form.errors.name">
                <Input v-model:value="form.name" :maxlength="150" autofocus />
            </FormItem>
            <FormItem label="Industria padre (opcional)">
                <Select v-model:value="form.parent_id" :options="parentOptions" allow-clear show-search :filter-option="(i,o)=>(o.label??'').toLowerCase().includes(i.toLowerCase())" />
            </FormItem>
            <FormItem><Space><Switch v-model:checked="form.is_active" /><span>Activa</span></Space></FormItem>
        </Form>
    </Modal>
</template>
