import { ref } from 'vue';
import { router, usePage } from '@inertiajs/vue3';

/**
 * Multi-tab navigation composable (SAP Fiori-style).
 *
 * Tabs persist across page navigation en sessionStorage para que la barra se
 * sienta estable. **Scopeado por user_id**: si en este navegador cambia el
 * user logueado (logout → login otro), los tabs del usuario anterior se
 * descartan para evitar leak cross-user (super → otro user veía los
 * tabs viejos aunque no podía abrirlos).
 *
 * Para registrar una ruta como "tabbeable" agregala a KNOWN_ROUTES abajo.
 */

// ─── Routes that should appear as tabs ──────────────────────────────────────
// key:        unique tab id
// titleKey:   i18n key shown en el tab (se traduce en TabsBar.vue)
// matcher:    función que decide si una URL pertenece a este tab
const KNOWN_ROUTES = [
    { key: 'regions',       titleKey: 'sidebar.regions',       matcher: (url) => url.includes('/system_management/regions') },
    { key: 'users',         titleKey: 'sidebar.users',         matcher: (url) => url.includes('/user_management/users') },
    { key: 'roles',         titleKey: 'sidebar.roles',         matcher: (url) => url.includes('/user_management/roles') },
    { key: 'customers',     titleKey: 'sidebar.customers',     matcher: (url) => url.includes('/business_management/customers') },
    { key: 'languages',     titleKey: 'sidebar.languages',     matcher: (url) => url.includes('/system_management/languages') },
    { key: 'countries',     titleKey: 'sidebar.countries',     matcher: (url) => url.includes('/system_management/countries') },
    { key: 'locales',       titleKey: 'sidebar.locales',       matcher: (url) => url.includes('/system_management/locales') },
    { key: 'tenants',       titleKey: 'sidebar.tenants',       matcher: (url) => url.includes('/system_management/tenants') },
    { key: 'plans',         titleKey: 'sidebar.plans',         matcher: (url) => url.includes('/system_management/plans') },
    { key: 'automations',   titleKey: 'sidebar.automations',   matcher: (url) => url.includes('/automation_management/automations') },
    { key: 'system_modules', titleKey: 'sidebar.system_modules', matcher: (url) => url.includes('/system_management/system_modules') },
    { key: 'settings',      titleKey: 'sidebar.settings',      matcher: (url) => url.includes('/system_management/settings') },
    { key: 'notifications', titleKey: 'global.notifications',  matcher: (url) => url.includes('/notifications') },
];

const STORAGE_KEY = 'app:tabs';
const tabs = ref([]);
const activeKey = ref(null);
let initialized = false;
let currentUserId = null;

/** Lee el user_id del payload de Inertia. Null si no hay sesión. */
const readUserId = () => {
    try {
        return usePage().props.auth?.user?.id ?? null;
    } catch (e) {
        return null;
    }
};

const persist = () => {
    try {
        sessionStorage.setItem(STORAGE_KEY, JSON.stringify({
            userId:    currentUserId,
            tabs:      tabs.value,
            activeKey: activeKey.value,
        }));
    } catch (e) {}
};

const load = () => {
    try {
        const raw = sessionStorage.getItem(STORAGE_KEY);
        if (!raw) return;
        const parsed = JSON.parse(raw);

        // Cross-user leak guard: si el storage es de OTRO user (típico tras
        // logout → login otro en el mismo navegador), descartamos todo.
        if (parsed.userId != null && parsed.userId !== currentUserId) {
            sessionStorage.removeItem(STORAGE_KEY);
            tabs.value = [];
            activeKey.value = null;
            return;
        }

        tabs.value      = parsed.tabs ?? [];
        activeKey.value = parsed.activeKey ?? null;
    } catch (e) {}
};

const inferFromUrl = (url) => {
    return KNOWN_ROUTES.find(r => r.matcher(url));
};

const registerCurrentUrl = () => {
    const url   = window.location.pathname + window.location.search;
    const route = inferFromUrl(url);
    if (!route) {
        // No es una ruta tabbeable — limpiamos la selección activa.
        activeKey.value = null;
        persist();
        return;
    }

    const existing = tabs.value.find(t => t.key === route.key);
    if (existing) {
        // Update its url to the latest visited (so filters in URL get remembered).
        // Y aseguramos que `titleKey` esté presente — entradas viejas en storage
        // solo tenían `title` (hardcoded) y no se traducían.
        existing.url = url;
        if (route.titleKey) existing.titleKey = route.titleKey;
    } else {
        tabs.value.push({ key: route.key, titleKey: route.titleKey, url });
    }
    activeKey.value = route.key;
    persist();
};

const closeTab = (key) => {
    const idx = tabs.value.findIndex(t => t.key === key);
    if (idx === -1) return;

    tabs.value.splice(idx, 1);
    persist();

    if (activeKey.value === key) {
        // Was active — navigate to the previous tab, or home if none left
        if (tabs.value.length > 0) {
            const next = tabs.value[Math.max(0, idx - 1)];
            router.visit(next.url);
        } else {
            activeKey.value = null;
            persist();
            const locale = document.documentElement.lang || 'es';
            router.visit(`/${locale}/`);
        }
    }
};

const switchTab = (key) => {
    const tab = tabs.value.find(t => t.key === key);
    if (tab) router.visit(tab.url);
};

const init = () => {
    if (initialized) return;
    initialized = true;
    currentUserId = readUserId();
    load();
    registerCurrentUrl();
    router.on('navigate', () => {
        // En cada navegación re-leemos el user — si cambió (logout + login
        // otro user sin recargar la pestaña) descartamos los tabs viejos.
        const newUserId = readUserId();
        if (newUserId !== currentUserId) {
            currentUserId = newUserId;
            tabs.value = [];
            activeKey.value = null;
            sessionStorage.removeItem(STORAGE_KEY);
        }
        registerCurrentUrl();
    });
};

export function useTabs() {
    return { tabs, activeKey, init, closeTab, switchTab };
}
