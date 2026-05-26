<script setup>
/** Drawer lateral: preview de idioma sin salir del listado. */
import { Drawer, Tag, Descriptions, DescriptionsItem, Button } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { TranslationOutlined, DeleteOutlined, CopyOutlined, EditOutlined } from '@ant-design/icons-vue';
import { useDateFormat } from '@/Composables/useDateFormat';

const { formatDateTime } = useDateFormat();

const props = defineProps({
    open:        { type: Boolean, required: true },
    language:    { type: Object,  default: null },
    width:       { type: [Number, String], default: 480 },
    isMobile:    { type: Boolean, default: false },
    canCreate:   { type: Boolean, default: false },
    canEdit:     { type: Boolean, default: false },
    canDelete:   { type: Boolean, default: false },
    duplicatingId: { type: [Number, null], default: null },
});

const emit = defineEmits(['update:open', 'duplicate']);
</script>

<template>
    <Drawer
        :open="open"
        :title="$t('languages.singular') + ' — ' + $t('global.show')"
        :width="width"
        placement="right"
        @update:open="emit('update:open', $event)"
    >
        <template v-if="language">
            <div class="drawer-hero">
                <div class="drawer-hero__icon">
                    <TranslationOutlined />
                </div>
                <div>
                    <h2>{{ language.name }}</h2>
                    <Tag :color="language.is_active ? 'success' : 'error'" :bordered="false">
                        {{ language.is_active ? $t('global.active') : $t('global.inactive') }}
                    </Tag>
                </div>
            </div>

            <Descriptions :column="1" bordered class="mt-4">
                <DescriptionsItem label="ID">{{ language.id }}</DescriptionsItem>
                <DescriptionsItem label="Slug">
                    <code>{{ language.slug }}</code>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('languages.iso_code')">
                    <code>{{ language.iso_code }}</code>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.created_at')">
                    {{ formatDateTime(language.created_at) }}
                </DescriptionsItem>
                <DescriptionsItem v-if="language.creator" :label="$t('global.created_by')">
                    {{ language.creator.name }}
                    <span class="audit-email">({{ language.creator.email }})</span>
                </DescriptionsItem>
                <DescriptionsItem :label="$t('global.updated_at')">
                    {{ formatDateTime(language.updated_at) }}
                </DescriptionsItem>
            </Descriptions>
        </template>

        <!-- Sticky footer with actions — desktop: right-aligned horizontal,
             mobile: stacked full-width (touch-friendly). -->
        <template #footer>
            <div v-if="language" class="drawer-footer" :class="{ 'drawer-footer--mobile': isMobile }">
                <Link
                    v-if="canDelete"
                    :href="route('system_management.languages.delete', language.slug)"
                >
                    <Button :block="isMobile" ghost danger>
                        <DeleteOutlined /> {{ $t('global.delete') }}
                    </Button>
                </Link>
                <Button
                    v-if="canCreate"
                    :block="isMobile"
                    :loading="duplicatingId === language.id"
                    @click="emit('duplicate', language)"
                >
                    <CopyOutlined /> {{ $t('global.duplicate') }}
                </Button>
                <Link
                    v-if="canEdit"
                    :href="route('system_management.languages.edit', language.slug)"
                >
                    <Button :block="isMobile" type="primary">
                        <EditOutlined /> {{ $t('global.edit') }}
                    </Button>
                </Link>
            </div>
        </template>
    </Drawer>
</template>

<style scoped>
.drawer-hero { display: flex; align-items: center; gap: 14px; padding: 8px 0; }
.drawer-hero__icon {
    width: 48px;
    height: 48px;
    border-radius: 4px;
    background: var(--color-primary);
    color: var(--color-text-on-dark);
    display: flex;
    align-items: center;
    justify-content: center;
    font-size: 1.35rem;
    flex-shrink: 0;
}
.drawer-hero h2 {
    font-size: 1.15rem;
    font-weight: 600;
    margin: 0 0 6px 0;
    color: var(--color-text);
}
.drawer-footer {
    display: flex;
    justify-content: flex-end;
    align-items: center;
    gap: 8px;
}
.drawer-footer--mobile {
    flex-direction: column-reverse;  /* Edit arriba, Delete abajo (primary visible al pulgar) */
    gap: 10px;
    align-items: stretch;
}
.audit-email { color: var(--color-text-muted); font-size: 0.8125rem; margin-left: 4px; }
</style>
