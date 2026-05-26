<script setup>
/**
 * EntityShowActions — botones del header del Show page (Audit + Edit + Delete).
 *
 * Reemplaza el copy-paste de los Show.vue de cada módulo.
 *
 * Uso:
 *   <SectionHeader ...>
 *       <template #actions>
 *           <EntityShowActions
 *               module="regions"
 *               :slug="region.slug"
 *               :id="region.id"
 *               :is-deleted="isDeleted"
 *               :can-edit="can('regions.edit')"
 *               :can-delete="can('regions.delete')"
 *               :can-see-audit="canSeeAudit"
 *           />
 *       </template>
 *   </SectionHeader>
 *
 * Tooltips y efectos hover heredados (global CSS + Tooltip de Antd).
 */
import { computed } from 'vue';
import { Button, Tag, Tooltip } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import {
    EditOutlined, DeleteOutlined, AuditOutlined,
} from '@ant-design/icons-vue';

const props = defineProps({
    module:        { type: String,  required: true },
    slug:          { type: [String, Number], required: true },
    id:            { type: [String, Number], default: null }, // para filtrar el audit log
    isDeleted:     { type: Boolean, default: false },
    canEdit:       { type: Boolean, default: false },
    canDelete:     { type: Boolean, default: false },
    canSeeAudit:   { type: Boolean, default: false },
    // Route prefix — default 'system_management'. Override para módulos en
    // otros clusters (user_management.users.edit, etc.).
    routePrefix:   { type: String,  default: 'system_management' },
    // Cuando canEdit=false porque el registro está protegido (no por permisos
    // ni por estar deleted), pasar la i18n key del label para mostrar Tag en
    // lugar de ocultar todo (ej. 'roles.protected' para rol is_system).
    editProtectedKey: { type: String, default: '' },
});

const routes = computed(() => {
    const base = `${props.routePrefix}.${props.module}`;
    return {
        edit:   `${base}.edit`,
        delete: `${base}.delete`,
    };
});
</script>

<template>
    <Tooltip v-if="canSeeAudit" :title="$t('global.open_in_audit_hint')">
        <Link :href="route('system_management.audit_logs.index', { module, auditable_id: id })">
            <Button>
                <AuditOutlined /> {{ $t('global.open_in_audit') }}
            </Button>
        </Link>
    </Tooltip>

    <Tooltip v-if="!isDeleted && canEdit" :title="$t('global.edit_hint')">
        <Link :href="route(routes.edit, slug)">
            <Button type="primary">
                <EditOutlined /> {{ $t('global.edit') }}
            </Button>
        </Link>
    </Tooltip>
    <Tag v-else-if="!isDeleted && editProtectedKey" :bordered="false">
        {{ $t(editProtectedKey) }}
    </Tag>

    <Tooltip v-if="!isDeleted && canDelete" :title="$t('global.delete_hint')">
        <Link :href="route(routes.delete, slug)">
            <Button danger>
                <DeleteOutlined /> {{ $t('global.delete') }}
            </Button>
        </Link>
    </Tooltip>
</template>
