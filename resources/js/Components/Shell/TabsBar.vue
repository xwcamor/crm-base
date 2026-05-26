<script setup>
import { CloseOutlined } from '@ant-design/icons-vue';
import { useTabs } from '@/Composables/useTabs';
import { useI18n } from '@/Plugins/i18n';

const { tabs, activeKey, switchTab, closeTab } = useTabs();
const { t } = useI18n();

// Cada tab guarda titleKey (ej. "sidebar.regions"). Lo resolvemos a string
// en cada render — así cambiar el idioma re-pinta los nombres de los tabs
// sin que el usuario tenga que cerrarlos y volver a abrirlos.
// Fallback al `title` legacy si el storage tiene tabs viejos sin titleKey.
const tabLabel = (tab) => tab.titleKey ? t(tab.titleKey) : (tab.title ?? tab.key);

const onTabClick = (tab) => {
    if (tab.key === activeKey.value) return;
    switchTab(tab.key);
};

const onCloseClick = (e, key) => {
    e.stopPropagation();
    closeTab(key);
};
</script>

<template>
    <div v-if="tabs.length > 0" class="tabs-bar">
        <div
            v-for="tab in tabs"
            :key="tab.key"
            class="tab"
            :class="{ 'tab--active': tab.key === activeKey }"
            @click="onTabClick(tab)"
        >
            <span class="tab__title">{{ tabLabel(tab) }}</span>
            <button
                class="tab__close"
                @click="(e) => onCloseClick(e, tab.key)"
                :aria-label="$t('global.close') + ' ' + tabLabel(tab)"
            >
                <CloseOutlined />
            </button>
        </div>
    </div>
</template>

<style scoped>
.tabs-bar {
    display: flex;
    align-items: stretch;
    background: #ffffff;
    border-bottom: 1px solid #E5E5E5;
    padding: 0 8px;
    overflow-x: auto;
    overflow-y: hidden;
    scrollbar-width: thin;
}
.tabs-bar::-webkit-scrollbar { height: 3px; }
.tabs-bar::-webkit-scrollbar-thumb { background: #d9d9d9; border-radius: 2px; }

.tab {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 12px;
    height: 36px;
    border-right: 1px solid #E5E5E5;
    cursor: pointer;
    user-select: none;
    background: #ffffff;
    color: #6A6D70;
    font-size: 0.85rem;
    transition: background 0.12s ease, color 0.12s ease;
    flex-shrink: 0;
    position: relative;
}
.tab:hover { background: #F5F5F5; color: #32363A; }

.tab--active {
    background: #ffffff;
    color: #0A6ED1;
    font-weight: 600;
}
.tab--active::after {
    content: '';
    position: absolute;
    left: 0;
    right: 0;
    bottom: -1px;
    height: 2px;
    background: #0A6ED1;
}

.tab__title {
    white-space: nowrap;
    max-width: 200px;
    overflow: hidden;
    text-overflow: ellipsis;
}
.tab__close {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 18px;
    height: 18px;
    border-radius: 50%;
    border: 0;
    background: transparent;
    cursor: pointer;
    color: #8c8c8c;
    font-size: 0.7rem;
    transition: background 0.12s ease, color 0.12s ease;
    padding: 0;
}
.tab__close:hover {
    background: #d9d9d9;
    color: #32363A;
}

/* Dark mode */
:global(html[data-theme="dark"]) .tabs-bar {
    background: #29313a;
    border-bottom-color: #3f4448;
}
:global(html[data-theme="dark"]) .tab {
    background: #29313a;
    color: #a8aaae;
    border-right-color: #3f4448;
}
:global(html[data-theme="dark"]) .tab:hover {
    background: #313a44;
    color: #e5e6e7;
}
:global(html[data-theme="dark"]) .tab--active {
    color: #4db6e8;
}
:global(html[data-theme="dark"]) .tab--active::after {
    background: #4db6e8;
}
</style>
