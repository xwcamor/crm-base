<script setup>
/** Empty state del Table. Cambia mensaje + CTAs segun hasFilters. */
import { Button, Space } from 'ant-design-vue';
import { Link } from '@inertiajs/vue3';
import { MessageOutlined, PlusOutlined, UploadOutlined } from '@ant-design/icons-vue';

defineProps({
    hasFilters: { type: Boolean, default: false },
});

defineEmits(['clear-filters', 'open-import']);
</script>

<template>
    <div class="empty-state">
        <MessageOutlined class="empty-state__icon" />
        <h3 v-if="hasFilters">{{ $t('global.no_results') }}</h3>
        <h3 v-else>{{ $t('messages.messages_empty_title') }}</h3>
        <p v-if="hasFilters">{{ $t('global.try_adjust_filters') }}</p>
        <p v-else>{{ $t('messages.messages_empty_hint') }}</p>
        <Space wrap>
            <Button v-if="hasFilters" @click="$emit('clear-filters')">
                {{ $t('global.clear') }} {{ $t('global.filters').toLowerCase() }}
            </Button>
            <template v-if="!hasFilters">
                <Link :href="route('communication.messages.create')">
                    <Button type="primary">
                        <PlusOutlined /> {{ $t('messages.new_message') }}
                    </Button>
                </Link>
                <Button @click="$emit('open-import')">
                    <UploadOutlined /> {{ $t('global.import') }}
                </Button>
            </template>
        </Space>
    </div>
</template>

<style scoped>
.empty-state { text-align: center; padding: 56px 20px; display: flex; flex-direction: column; align-items: center; gap: 8px; }
.empty-state__icon { font-size: 56px; color: var(--color-icon-soft); margin-bottom: 8px; }
.empty-state h3 { margin: 0; font-size: 1rem; font-weight: 600; color: var(--color-text); }
.empty-state p { margin: 0 0 12px 0; color: var(--color-text-muted); font-size: 0.875rem; }
</style>
