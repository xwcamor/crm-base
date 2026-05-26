<script setup>
import { Link, router } from '@inertiajs/vue3';
import { Button, Dropdown, Menu, MenuItem } from 'ant-design-vue';
import { DownloadOutlined, DeleteOutlined, MoreOutlined } from '@ant-design/icons-vue';
import { useAuth } from '@/Composables/useAuth';
const { isSuper } = useAuth();

const props = defineProps({
    routePrefix: { type: String, required: true },
});

const exportCsv = () => {
    router.post(route(props.routePrefix + '.export_csv'), {}, { preserveScroll: true });
};
</script>

<template>
    <Dropdown placement="bottomRight">
        <Button>
            <MoreOutlined />
        </Button>
        <template #overlay>
            <Menu>
                <MenuItem @click="exportCsv">
                    <DownloadOutlined /> Exportar CSV
                </MenuItem>
                <MenuItem v-if="isSuper">
                    <Link :href="route(routePrefix + '.trash')">
                        <DeleteOutlined /> Papelera
                    </Link>
                </MenuItem>
            </Menu>
        </template>
    </Dropdown>
</template>
