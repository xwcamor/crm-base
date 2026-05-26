import './bootstrap';

import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { ZiggyVue } from 'ziggy-js';
import Antd from 'ant-design-vue';
import { ModuleRegistry, AllCommunityModule } from 'ag-grid-community';
import { autoAnimatePlugin } from '@formkit/auto-animate/vue';
import I18nPlugin from '@/Plugins/i18n';

// Register AG Grid Community modules once for the whole app
ModuleRegistry.registerModules([AllCommunityModule]);

const appName = import.meta.env.VITE_APP_NAME || 'Laravel';

createInertiaApp({
    title: (title) => (title ? `${title} - ${appName}` : appName),
    resolve: (name) =>
        import.meta.glob('./Pages/**/*.vue', { eager: false })[`./Pages/${name}.vue`](),
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(Antd)
            .use(autoAnimatePlugin)
            .use(I18nPlugin)
            .mount(el);
    },
    progress: {
        color: '#354A5F',
        showSpinner: true,
    },
});
