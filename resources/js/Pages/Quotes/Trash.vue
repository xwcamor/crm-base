<script setup>
import GenericTrashPage from '@/Components/Shared/GenericTrashPage.vue';
import AppLayout from '@/Layouts/AppLayout.vue';
defineOptions({ layout: AppLayout });
defineProps({ items: Object, filters: Object, meta: Object });
</script>
<template>
    <GenericTrashPage title="Quotes" :items="items" :filters="filters" :meta="meta" />
</template>
