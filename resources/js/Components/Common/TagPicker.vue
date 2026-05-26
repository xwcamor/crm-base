<script setup>
/**
 * TagPicker — gestiona tags polimorficos en Show pages.
 *
 * Muestra los tags actuales del registro como Tag chips removibles. Boton
 * "+ Agregar tag" abre un Select autocomplete que busca tags existentes
 * y permite crear uno nuevo si no existe (typing-and-enter).
 *
 * Uso:
 *   <TagPicker
 *     :taggable="{ type: 'App\\Models\\Company', id: company.id }"
 *     :initial-tags="company.tags ?? []"
 *     :can-edit="can('companies.edit')"
 *   />
 *
 * Cada attach/detach hace POST con preserveScroll. El parent se refresca
 * al final del ciclo de Inertia.
 */
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import { Tag, Select, Button, Modal, Input, message } from 'ant-design-vue';
import { TagOutlined, PlusOutlined, CloseOutlined } from '@ant-design/icons-vue';

const props = defineProps({
    taggable:    { type: Object, required: true },
    initialTags: { type: Array,  default: () => [] },
    canEdit:     { type: Boolean, default: false },
});

const tags = ref([...props.initialTags]);
const adding = ref(false);
const searchQuery = ref('');
const searchResults = ref([]);
const searchLoading = ref(false);
const creating = ref(false);
const newTagModalOpen = ref(false);
const newTagName = ref('');
const newTagColor = ref('#1677ff');

let searchTimer = null;
function onSearch(q) {
    searchQuery.value = q;
    if (searchTimer) clearTimeout(searchTimer);
    if (!q || q.length < 1) {
        searchResults.value = [];
        return;
    }
    searchTimer = setTimeout(async () => {
        searchLoading.value = true;
        try {
            const res = await fetch(route('tags.index') + '?q=' + encodeURIComponent(q), {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                credentials: 'same-origin',
            });
            const data = await res.json();
            // Excluir tags ya asignadas.
            const existingIds = new Set(tags.value.map(t => t.id));
            searchResults.value = (data.tags ?? []).filter(t => !existingIds.has(t.id));
        } finally {
            searchLoading.value = false;
        }
    }, 250);
}

function attachTag(tag) {
    router.post(route('tags.attach'), {
        taggable_type: props.taggable.type,
        taggable_id:   props.taggable.id,
        tag_id:        tag.id,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            if (!tags.value.find(t => t.id === tag.id)) tags.value.push(tag);
            searchQuery.value = '';
            searchResults.value = [];
            adding.value = false;
        },
        onError: () => message.error('No se pudo agregar el tag.'),
    });
}

function detachTag(tag) {
    router.post(route('tags.detach'), {
        taggable_type: props.taggable.type,
        taggable_id:   props.taggable.id,
        tag_id:        tag.id,
    }, {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => {
            tags.value = tags.value.filter(t => t.id !== tag.id);
        },
        onError: () => message.error('No se pudo quitar el tag.'),
    });
}

function openNewTagModal() {
    newTagName.value = searchQuery.value;
    newTagColor.value = '#1677ff';
    newTagModalOpen.value = true;
}

async function createTag() {
    if (!newTagName.value.trim()) return;
    creating.value = true;
    try {
        const res = await fetch(route('tags.store'), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content ?? '',
            },
            credentials: 'same-origin',
            body: JSON.stringify({
                name: newTagName.value.trim(),
                color: newTagColor.value || '#1677ff',
            }),
        });
        if (!res.ok) {
            message.error('No se pudo crear el tag.');
            return;
        }
        const data = await res.json();
        newTagModalOpen.value = false;
        adding.value = false;
        attachTag(data.tag);
    } finally {
        creating.value = false;
    }
}
</script>

<template>
    <div class="tag-picker">
        <div class="tag-picker-list">
            <Tag
                v-for="tag in tags"
                :key="tag.id"
                :color="tag.color || 'default'"
                :closable="canEdit"
                @close="detachTag(tag)"
            >
                <TagOutlined /> {{ tag.name }}
            </Tag>

            <Button
                v-if="canEdit && !adding"
                size="small"
                type="dashed"
                @click="adding = true"
            >
                <PlusOutlined /> {{ $t('tags.add') ?? 'Agregar tag' }}
            </Button>

            <Select
                v-if="adding"
                show-search
                :placeholder="$t('tags.search_placeholder') ?? 'Buscar o crear tag...'"
                :options="searchResults.map(t => ({ label: t.name, value: t.id, raw: t }))"
                :loading="searchLoading"
                :filter-option="false"
                style="min-width: 200px;"
                @search="onSearch"
                @select="(_, opt) => attachTag(opt.raw)"
                @blur="adding = false"
            >
                <template #notFoundContent>
                    <div v-if="searchQuery" class="tag-not-found">
                        <span>{{ $t('tags.not_found_for') ?? 'No existe' }}: "{{ searchQuery }}"</span>
                        <Button size="small" type="primary" @mousedown.prevent="openNewTagModal">
                            <PlusOutlined /> {{ $t('tags.create_new') ?? 'Crear' }}
                        </Button>
                    </div>
                    <span v-else>{{ $t('tags.type_to_search') ?? 'Escribe para buscar' }}</span>
                </template>
            </Select>
        </div>

        <Modal
            v-model:open="newTagModalOpen"
            :title="$t('tags.create_title') ?? 'Crear tag'"
            :ok-text="$t('tags.create') ?? 'Crear'"
            :cancel-text="$t('global.cancel') ?? 'Cancelar'"
            :confirm-loading="creating"
            @ok="createTag"
        >
            <div style="display: flex; flex-direction: column; gap: 12px;">
                <div>
                    <label>{{ $t('tags.name') ?? 'Nombre' }}</label>
                    <Input v-model:value="newTagName" :maxlength="80" />
                </div>
                <div>
                    <label>{{ $t('tags.color') ?? 'Color' }}</label>
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <input type="color" v-model="newTagColor" style="width: 48px; height: 32px; border: 1px solid #d9d9d9; border-radius: 4px; cursor: pointer; padding: 2px;" />
                        <Input v-model:value="newTagColor" :maxlength="16" style="width: 120px;" placeholder="#1677ff" />
                    </div>
                </div>
            </div>
        </Modal>
    </div>
</template>

<style scoped>
.tag-picker-list {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: 6px;
}
.tag-not-found {
    display: flex;
    flex-direction: column;
    gap: 8px;
    padding: 8px;
}
</style>
