<script setup>
import { ref, computed, watch, onMounted, onBeforeUnmount, provide } from 'vue';
import { Link, usePage, router } from '@inertiajs/vue3';
import {
    Layout, LayoutHeader, LayoutSider, LayoutContent,
    Menu, MenuItem, MenuDivider, MenuItemGroup, SubMenu,
    Avatar, Dropdown, Drawer, Badge, Tooltip, Alert, Tag,
    ConfigProvider, theme,
    message, notification,
} from 'ant-design-vue';
import { h } from 'vue';
// import TabsBar from '@/Components/Shell/TabsBar.vue'; // Desactivado: tabs no se acumulan
import RotatePortraitOverlay from '@/Components/Common/RotatePortraitOverlay.vue';
import GlobalSearch from '@/Components/Common/GlobalSearch.vue';
// import { useTabs } from '@/Composables/useTabs'; // Desactivado
import { useI18n } from '@/Plugins/i18n';

const { t } = useI18n();
import {
    DashboardOutlined,
    GlobalOutlined,
    UserOutlined,
    SettingOutlined,
    BankOutlined, SafetyCertificateOutlined,
    FunnelPlotOutlined, DollarOutlined,
    FileDoneOutlined, FileTextOutlined, CreditCardOutlined,
    ShoppingCartOutlined, ShopOutlined, ContainerOutlined,
    TagsOutlined, CalculatorOutlined, CompassOutlined, ApartmentOutlined,
    PercentageOutlined, AppstoreAddOutlined, CarOutlined, CheckSquareOutlined,
    SwapOutlined,
    LogoutOutlined,
    HistoryOutlined,
    DownOutlined,
    MenuOutlined,
    BellOutlined,
    MailOutlined,
    DesktopOutlined,
    TranslationOutlined,
    FlagOutlined,
    ClockCircleOutlined,
    ReadOutlined,
    AuditOutlined,
    AppstoreOutlined,
    IdcardOutlined,
    CheckOutlined,
    DownloadOutlined,
    DeleteOutlined,
    FileExcelOutlined,
    FilePdfOutlined,
    FileWordOutlined,
    FileOutlined,
    LoadingOutlined,
    CloseCircleFilled,
    ShoppingOutlined,
    TeamOutlined,
    BranchesOutlined, NotificationOutlined,
    InboxOutlined,
    MessageOutlined,
    BarChartOutlined, LineChartOutlined, PieChartOutlined, FundOutlined,
} from '@ant-design/icons-vue';

import { usePlanFeatures } from '@/Composables/usePlanFeatures';
const { canUse: canUsePlanFeature } = usePlanFeatures();

// Inline SVG icons (Lucide-style) — Ant Design Vue no incluye Sun/Moon outlined.
// Reutilizamos el mismo set del AuthLayout para que el switcher se vea idéntico
// dentro y fuera de la sesión.
const sunSvg  = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M4.93 19.07l1.41-1.41M17.66 6.34l1.41-1.41"/></svg>`;
const moonSvg = `<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" width="18" height="18" aria-hidden="true"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg>`;

const page = usePage();

const user    = computed(() => page.props.auth?.user);
const appName = computed(() => page.props.app?.name ?? 'Base App');
const appLogo = computed(() => page.props.appLogoUrl || null);
const locale  = computed(() => page.props.locale ?? 'es');

// Para mostrar el badge del tenant en notifs de automation cuando el receptor
// es super (necesita distinguir de que workspace viene). Admin no necesita
// el badge porque solo ve las de su propio tenant.
const isSuperUser = computed(() => (page.props.auth?.user?.roles ?? []).includes('super'));

// Flash messages → toast.
// 2 defensas combinadas:
// 1. Backend `pull()` consume el flash, garantiza que solo viene la primera vez.
// 2. Frontend compara contra oldValue para no mostrar el mismo toast dos veces
//    en caso de partial reload con el mismo flash (defensive).
// `immediate: true` necesario para mostrar el toast del request post-redirect
// donde AppLayout se monta con el flash ya populated.
// Disparamos el toast cuando llega un flash y lo CONSUMIMOS (lo seteamos a
// null en el state reactivo). Asi, si el usuario provoca el MISMO error dos
// veces seguidas, el watch vuelve a disparar porque el valor paso por null
// entre medio. Sin esto, el watch comparaba string === string y silenciaba
// el segundo toast.
watch(
    () => page.props.flash,
    (flash) => {
        if (!flash) return;
        if (flash.success) {
            message.success(flash.success);
            flash.success = null;
        }
        if (flash.error) {
            message.error(flash.error);
            flash.error = null;
        }
    },
    { deep: true, immediate: true },
);

// ─── Notifications bell (inbox) ──────────────────────────────────────────
// El backend comparte `page.props.inbox` con { recent[], unread, processing }.
// Usamos `inbox` (no `notifications`) para evitar colisión con el page-prop
// `notifications` que el listado completo usa.
//
// Cada item del recent[] tiene un `kind` (download/task/alert/etc.) — hoy
// solo download. El badge muestra `unread`; el polling auto-refresca cada
// 8s mientras haya jobs en `processing`.
// Overrides optimistas: cuando el user marca una notif app como leida desde
// el bell, no esperamos el round-trip al server para bajar el badge — el
// numero cae al instante y los items aparecen como read. Cuando llega un
// `inbox` nuevo del backend (refreshInbox), los overrides se resetean porque
// el server ya tiene el estado autoritativo.
const locallyReadAppIds = ref(new Set());
const localUnreadOffset = ref(0);

const inbox = computed(() => {
    const raw = page.props.inbox ?? { recent: [], unread: 0, processing: 0, unread_messages: 0, messages: [] };
    return {
        ...raw,
        unread: Math.max(0, (raw.unread ?? 0) - localUnreadOffset.value),
        recent: (raw.recent ?? []).map(item =>
            item.kind === 'app' && locallyReadAppIds.value.has(item.raw_id)
                ? { ...item, status: 'read' }
                : item
        ),
    };
});

// Cuando llega un inbox nuevo del backend, descartamos los overrides locales
// — el server ya refleja la realidad.
watch(() => page.props.inbox, () => {
    if (locallyReadAppIds.value.size === 0 && localUnreadOffset.value === 0) return;
    locallyReadAppIds.value = new Set();
    localUnreadOffset.value = 0;
}, { deep: false });

// Preview de los últimos 5 mensajes (leídos o no) para el dropdown del sobre.
// Separado del recent[] del bell — los dos iconos son distintos:
//   - Bell (BellOutlined): notificaciones de SISTEMA (downloads + automations)
//   - Sobre (MailOutlined): MENSAJES del módulo Communication
const messagesInBell = computed(() => inbox.value.messages ?? []);

const goToInboxMessage = (m) => {
    router.visit(route('communication.inbox.show', m.slug));
};

// Banner global de suscripción — solo se renderiza si el backend lo manda
// (días_restantes <= 7 OR trial). super nunca lo ve.
const subscriptionWarning = computed(() => page.props.subscription ?? null);

const refreshInbox = () => {
    router.reload({ only: ['inbox'], preserveScroll: true, preserveState: true });
};

// Recientes — los últimos N registros vistos por el usuario, vienen del
// shared prop `recentViews` que pobla HandleInertiaRequests. Cada item ya
// trae { id, name, module, url }.
const recentViews = computed(() => page.props.recentViews ?? []);
const goToRecent = (item) => {
    if (item?.url) router.visit(item.url);
};

const goToProfile = () => router.visit(route('profile.show'));
const goToProfilePrefs = () => router.visit(route('profile.show') + '#preferences');

let inboxPollTimer = null;
const startInboxPolling = () => {
    if (inboxPollTimer) return;
    // El intervalo lo lee desde el setting `notifications.poll_interval_seconds`
    // que el backend comparte como prop. Default 4s. Configurable desde el
    // modulo Settings sin redeploy. Clamp a [1, 60] por seguridad.
    const fromSettings = Number(page.props.notificationsPollInterval ?? 4);
    const seconds = Math.min(60, Math.max(1, Number.isFinite(fromSettings) ? fromSettings : 4));
    inboxPollTimer = setInterval(() => {
        refreshInbox();
    }, seconds * 1000);
};
const stopInboxPolling = () => {
    if (inboxPollTimer) {
        clearInterval(inboxPollTimer);
        inboxPollTimer = null;
    }
};

// Polling SIEMPRE activo mientras la pagina este montada. Antes solo arrancaba
// con downloads en `processing` — pero eso significa que si llegaba una notif
// app (automation ejecutada, alerta de seguridad), el bell no la veia hasta
// que el user abria el dropdown manualmente. Ahora el badge se actualiza solo
// cada N segundos (setting `notifications.poll_interval_seconds`, default 4s).
//
// El user puede subirlo a 60s desde Settings si le preocupa el trafico.
startInboxPolling();

// ── Toast notifications cuando un download cambia de estado ──────────────
// Track de los IDs ya conocidos en cada bucket. En la PRIMERA observación
// solo registramos (sin toast) para no inundar al usuario al cargar la
// página con cosas que ya sabía. De ahí en adelante, comparamos con la
// observación previa y disparamos toast por cada nueva transición.
const knownReadyIds      = new Set();
const knownFailedIds     = new Set();
let firstInboxObservation = true;

watch(
    () => inbox.value.recent,
    (items) => {
        if (!Array.isArray(items)) return;

        const currentReadyIds  = new Set(items.filter(n => n.status === 'ready'  && !n.downloaded_at).map(n => n.id));
        const currentFailedIds = new Set(items.filter(n => n.status === 'failed').map(n => n.id));

        if (!firstInboxObservation) {
            // Recién listos
            for (const id of currentReadyIds) {
                if (knownReadyIds.has(id)) continue;
                const n = items.find(x => x.id === id);
                if (!n) continue;
                notification.success({
                    message: t('global.download_ready'),
                    description: n.filename,
                    placement: 'topRight',
                    duration: 8,
                    btn: () => h('button', {
                        onClick: () => triggerDownload(n),
                        style: 'background:#0A6ED1;color:#fff;border:0;padding:6px 14px;border-radius:4px;cursor:pointer;font-weight:500;font-size:0.8rem;',
                    }, t('notifications.download')),
                });
            }
            // Recién fallados
            for (const id of currentFailedIds) {
                if (knownFailedIds.has(id)) continue;
                const n = items.find(x => x.id === id);
                if (!n) continue;
                notification.error({
                    message: t('global.download_failed'),
                    description: (n.error_message || n.filename || t('global.unknown_error')),
                    placement: 'topRight',
                    duration: 10,
                });
            }
        }

        // Actualizamos los conocidos para la próxima comparación.
        knownReadyIds.clear();
        currentReadyIds.forEach(id => knownReadyIds.add(id));
        knownFailedIds.clear();
        currentFailedIds.forEach(id => knownFailedIds.add(id));

        firstInboxObservation = false;
    },
    { deep: true, immediate: true },
);

// Helpers de UI por tipo de archivo (solo aplica al kind 'download')
const downloadFileIcon = (type) => {
    switch (type) {
        case 'excel': return { icon: FileExcelOutlined, color: '#1D7044' };
        case 'pdf':   return { icon: FilePdfOutlined,   color: '#C8281D' };
        case 'word':  return { icon: FileWordOutlined,  color: '#185ABD' };
        default:      return { icon: FileOutlined,      color: '#6A6D70' };
    }
};

const downloadStatusLabel = (status) => {
    switch (status) {
        case 'processing': return t('notifications.status_processing');
        case 'ready':      return t('notifications.status_ready');
        case 'failed':     return t('notifications.status_failed');
        case 'expired':    return t('notifications.status_expired');
        default:           return status;
    }
};

const triggerDownload = (n) => {
    if (n.kind !== 'download' || n.status !== 'ready') return;
    window.location.href = route('notifications.download', n.id);
    // Optimistic refresh para que el badge baje al instante.
    setTimeout(refreshInbox, 800);
};

const dismissNotification = (n) => {
    router.delete(
        route('notifications.delete', n.id),
        { preserveScroll: true, preserveState: true, onFinish: refreshInbox },
    );
};

// Marca una notificacion `kind:'app'` como leida sin navegar a otra pagina.
// Optimistic update: actualiza el estado local AL INSTANTE (badge baja, item
// pasa a read) y dispara el POST en background. Cuando refreshInbox trae el
// payload nuevo, los overrides locales se descartan en el watcher.
const markAppNotificationRead = (n) => {
    if (n.kind !== 'app' || n.status !== 'unread') return;
    if (locallyReadAppIds.value.has(n.raw_id)) return;

    locallyReadAppIds.value.add(n.raw_id);
    localUnreadOffset.value++;

    router.post(
        route('notifications.app.read', n.raw_id),
        {},
        { preserveScroll: true, preserveState: true, onFinish: refreshInbox },
    );
};

// Icono y color por categoria de notif app — fallback a BellOutlined.
// Para automations distinguimos channel: si es 'email' usamos icono de
// sobre (confirmacion de envio); si es 'in_app' usamos megafono
// (notificacion interna del sistema — semanticamente correcto).
// El rayo queda reservado para el item del sidebar "Automatizaciones".
const appNotifIcon = (n) => {
    if (n.type === 'automation') {
        return n.channel === 'email' ? MailOutlined : NotificationOutlined;
    }
    const map = {
        security:    BellOutlined,
        plan_change: BellOutlined,
    };
    return map[n.type] ?? BellOutlined;
};
const appNotifColor = (n) => {
    if (n.type === 'automation') {
        return n.channel === 'email' ? '#1677ff' : '#fa8c16';
    }
    const map = {
        security:    '#cf1322',
        plan_change: '#1677ff',
    };
    return map[n.type] ?? '#0A6ED1';
};

const goToNotificationsPage = () => {
    router.visit(route('notifications.index'));
};

// Responsive
const isMobile = ref(false);
const checkMobile = () => { isMobile.value = window.innerWidth < 992; };
onMounted(() => { checkMobile(); window.addEventListener('resize', checkMobile); });
onBeforeUnmount(() => {
    window.removeEventListener('resize', checkMobile);
    stopInboxPolling();
});

const collapsed   = ref(false);
const drawerOpen  = ref(false);
const toggleSidebar = () => {
    if (isMobile.value) drawerOpen.value = !drawerOpen.value;
    else                collapsed.value  = !collapsed.value;
};

// Active menu key — uses Inertia's reactive page.url so the highlight follows
// SPA navigation (window.location.pathname is NOT reactive in Vue/Inertia).
// Order matters: more specific patterns first (e.g. system_modules before users).
const selectedKey = computed(() => {
    const url = page.url ?? '';
    // ORDEN CRITICO: paths mas especificos PRIMERO (sino url.includes()
    // matchea el sufijo equivocado — ej. "/product_categories" matchearia
    // como "products" porque contiene "/products" en su prefijo... wait,
    // no lo contiene. Pero "/stock_takes" matchea "/stock" SI lo pongo
    // "/stock" antes — porque /business_management/stock_takes incluye
    // "/stock". Asi que /stock_takes va antes que /stock).
    const matchers = [
        ['audit_logs',     '/audit_logs'],
        ['system_modules', '/system_modules'],
        ['automations',    '/automations'],
        ['messages',       '/communication/messages'],
        ['inbox',          '/communication/inbox'],
        ['tenants',        '/tenants'],
        ['plans',          '/plans'],
        ['regions',        '/regions'],
        ['languages',      '/languages'],
        ['countries',      '/countries'],
        ['locales',        '/locales'],
        ['settings',       '/settings'],
        ['roles',          '/roles'],
        ['users',          '/users'],
        ['customers',      '/customers'],

        // CRM
        ['companies',      '/crm/companies'],
        ['contacts',       '/crm/contacts'],
        ['pipelines',      '/crm/pipelines'],
        ['deals',          '/crm/deals'],
        ['activities',     '/crm/activities'],

        // Negocio (orden: especificos primero)
        ['product_categories', '/business_management/product_categories'],
        ['product_variants',   '/business_management/product_variants'],
        ['products',           '/business_management/products'],
        ['quotes',             '/business_management/quotes'],
        ['invoices',           '/business_management/invoices'],
        ['sales_orders',       '/business_management/sales_orders'],
        ['purchase_orders',    '/business_management/purchase_orders'],
        ['payment_methods',    '/business_management/payment_methods'],
        ['payments',           '/business_management/payments'],
        ['deliveries',         '/business_management/deliveries'],
        ['warehouses',         '/business_management/warehouses'],
        ['stock_takes',        '/business_management/stock_takes'],
        ['stock',              '/business_management/stock'],
        ['tax_classes',        '/business_management/tax_classes'],
        ['discounts',          '/business_management/discounts'],
        ['price_lists',        '/business_management/price_lists'],
        ['lead_sources',       '/business_management/lead_sources'],
        ['industries',         '/business_management/industries'],
        ['exchange_rates',     '/business_management/exchange_rates'],
        ['subscriptions',      '/business_management/subscriptions'],

        ['dashboard',      '/dashboard_management/dashboards'],
        ['dashboard',      '/dashboard'],  // legacy fallback

        // Reportes (rutas /reports/* — fuera de business_management)
        ['reports_sales_pipeline', '/reports/sales-pipeline'],
        ['reports_win_rate',       '/reports/win-rate'],
        ['reports_revenue',        '/reports/revenue'],
        ['reports_activity',       '/reports/activity'],
        ['reports_operations',     '/reports/operations'],
    ];
    for (const [key, pattern] of matchers) {
        if (url.includes(pattern)) return key;
    }
    return '';
});

// Theme switcher
const themeMode = ref('auto');
let mql = null;

const effectiveTheme = computed(() => {
    if (themeMode.value === 'auto') return mql && mql.matches ? 'dark' : 'light';
    return themeMode.value;
});

const applyTheme = () => {
    document.documentElement.setAttribute('data-theme', effectiveTheme.value);
    document.body.classList.toggle('dark-mode', effectiveTheme.value === 'dark');
};

const setTheme = (mode) => {
    themeMode.value = mode;
    try { localStorage.setItem('theme-mode', mode); } catch (e) {}
    applyTheme();
};

// El ícono visible depende del MODO seleccionado (no del tema efectivo):
// auto → monitor, light → sol, dark → luna. Así el botón comunica la elección.
const themeIconSvg = computed(() => {
    if (themeMode.value === 'light') return sunSvg;
    if (themeMode.value === 'dark')  return moonSvg;
    return null;
});

// Ant Design Vue theme config — lee los tokens de app.css en runtime para
// mantener UNA sola fuente de verdad de colores de marca. Si cambias
// `--color-primary` en app.css, los botones de Ant Design (Crear, Guardar,
// Restaurar, etc.) reflejan el cambio sin tocar este archivo.
//
// `effectiveTheme.value` está como dependencia para que el computed se
// re-evalúe al cambiar tema y getComputedStyle lea los nuevos valores del
// bloque `html[data-theme="dark"]`.
const readCssToken = (name, fallback) => {
    if (typeof document === 'undefined') return fallback;
    const v = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
    return v || fallback;
};

const antdTheme = computed(() => {
    const isDark = effectiveTheme.value === 'dark';
    return {
        algorithm: isDark ? theme.darkAlgorithm : theme.defaultAlgorithm,
        token: {
            colorPrimary:   readCssToken('--color-primary',   isDark ? '#4db6e8' : '#0A6ED1'),
            colorError:     readCssToken('--color-danger',    '#BB0000'),
            colorWarning:   readCssToken('--color-warning',   '#f59e0b'),
            colorTextBase:  readCssToken('--color-text',      isDark ? '#e5e6e7' : '#32363A'),
            borderRadius:   4,
            fontFamily:     "'Inter', -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif",
        },
    };
});

// Provide effective theme to descendants (AG Grid, custom components)
provide('theme', effectiveTheme);

onMounted(() => {
    try { themeMode.value = localStorage.getItem('theme-mode') || 'auto'; } catch (e) {}
    mql = window.matchMedia('(prefers-color-scheme: dark)');
    applyTheme();
    const handler = () => { if (themeMode.value === 'auto') applyTheme(); };
    mql.addEventListener ? mql.addEventListener('change', handler) : mql.addListener(handler);

    // TabsBar (multi-tab navigation) deshabilitado por preferencia del usuario.
    // useTabs().init();
});

// Languages — viene del shared prop `availableLocales` (intersección de
// laravellocalization.supportedLocales ∩ Language::active). Single source of
// truth: lo que active super desde el módulo Languages.
const languages = computed(() => page.props.availableLocales ?? []);

const switchLang = (code) => {
    // Strippeamos cualquier prefijo de locale soportado, no solo es|en|pt.
    const validCodes = languages.value.map(l => l.code).join('|');
    const re = new RegExp('^/(' + validCodes + ')(/|$)');
    const path = window.location.pathname.replace(re, '/');
    window.location.href = `/${code}${path === '/' ? '/' : path}${window.location.search}`;
};

// Logout
const logout = () => router.post(route('logout'));

// ─── Permission/role helpers ───────────────────────────────────────────────
// Mirrors backend Gate::before — super always passes.
const can = (perm) => {
    const u = page.props.auth?.user;
    if (!u) return false;
    if (u.roles?.includes('super')) return true;
    return u.permissions?.includes(perm) ?? false;
};
const hasRole = (...names) => {
    const userRoles = page.props.auth?.user?.roles ?? [];
    return names.some(n => userRoles.includes(n));
};

// Sidebar structure — sections (groups) with items inside.
// Each item declares a `visible` predicate. Disabled items render greyed out
// with a "coming soon" tooltip (used for routes not built yet).
const menuStructure = computed(() => [
    // ── Dashboard (primer item del sidebar, arriba de los grupos) ─────────
    {
        kind: 'item',
        key: 'dashboard', label: t('sidebar.dashboard'), icon: DashboardOutlined,
        href: route('dashboard_management.dashboards.index'), inertia: true,
        visible: () => true,
    },

    // ── Grupo: Accesos ────────────────────────────────────────────────────
    {
        kind: 'group',
        key: 'group-accesos', title: t('sidebar.group_access'),
        items: [
            // Users + Roles = "Equipos de trabajo" — gated por plan_feature.
            // free/basic son operacion de 1 persona y no ven estos modulos.
            // super bypassa el gate de plan (usePlanFeatures lo maneja).
            {
                key: 'users', label: t('sidebar.users'), icon: UserOutlined,
                href: route('user_management.users.index'), inertia: true,
                visible: () => can('users.view') && canUsePlanFeature('team_management'),
            },
            {
                key: 'roles', label: t('sidebar.roles'), icon: IdcardOutlined,
                href: route('user_management.roles.index'), inertia: true,
                visible: () => hasRole('super', 'admin') && canUsePlanFeature('team_management'),
            },
        ],
    },

    // ── Grupo: Negocio (módulos de empresa) ──────────────────────────────
    {
        kind: 'group',
        key: 'group-business', title: t('sidebar.group_business'),
        items: [
            {
                key: 'products', label: t('sidebar.products'), icon: AppstoreOutlined,
                href: route('business_management.products.index'), inertia: true,
                visible: () => can('products.view'),
            },
            {
                key: 'quotes', label: t('sidebar.quotes'), icon: FileDoneOutlined,
                href: route('business_management.quotes.index'), inertia: true,
                visible: () => can('quotes.view'),
            },
            {
                key: 'invoices', label: t('sidebar.invoices'), icon: FileTextOutlined,
                href: route('business_management.invoices.index'), inertia: true,
                visible: () => can('invoices.view'),
            },
            {
                key: 'sales_orders', label: t('sidebar.sales_orders'), icon: ShoppingCartOutlined,
                href: route('business_management.sales_orders.index'), inertia: true,
                visible: () => can('sales_orders.view'),
            },
            {
                key: 'purchase_orders', label: t('sidebar.purchase_orders'), icon: InboxOutlined,
                href: route('business_management.purchase_orders.index'), inertia: true,
                visible: () => can('purchase_orders.view'),
            },
            {
                key: 'payments', label: t('sidebar.payments'), icon: CreditCardOutlined,
                href: route('business_management.payments.index'), inertia: true,
                visible: () => can('payments.view'),
            },
            {
                key: 'warehouses', label: t('sidebar.warehouses'), icon: ShopOutlined,
                href: route('business_management.warehouses.index'), inertia: true,
                visible: () => can('warehouses.view'),
            },
            {
                key: 'stock', label: t('sidebar.stock'), icon: ContainerOutlined,
                href: route('business_management.stock.index'), inertia: true,
                visible: () => can('stock.view'),
            },
        ],
    },

    // ── Grupo: Catálogos (configuración del workspace) ───────────────────
    {
        kind: 'group',
        key: 'group-catalogs', title: t('sidebar.group_catalogs'),
        items: [
            {
                key: 'product_categories', label: t('sidebar.product_categories'), icon: TagsOutlined,
                href: route('business_management.product_categories.index'), inertia: true,
                visible: () => can('product_categories.view'),
            },
            {
                key: 'tax_classes', label: t('sidebar.tax_classes'), icon: CalculatorOutlined,
                href: route('business_management.tax_classes.index'), inertia: true,
                visible: () => can('tax_classes.view'),
            },
            {
                key: 'lead_sources', label: t('sidebar.lead_sources'), icon: CompassOutlined,
                href: route('business_management.lead_sources.index'), inertia: true,
                visible: () => can('lead_sources.view'),
            },
            {
                key: 'payment_methods', label: t('sidebar.payment_methods'), icon: CreditCardOutlined,
                href: route('business_management.payment_methods.index'), inertia: true,
                visible: () => can('payment_methods.view'),
            },
            {
                key: 'industries', label: t('sidebar.industries'), icon: ApartmentOutlined,
                href: route('business_management.industries.index'), inertia: true,
                // Solo super: el route esta gated por role:super (catalogo global,
                // no per-tenant). Mostrar el link a admins genera 403 silencioso.
                visible: () => isSuperUser.value,
            },
            {
                key: 'discounts', label: t('sidebar.discounts'), icon: PercentageOutlined,
                href: route('business_management.discounts.index'), inertia: true,
                visible: () => can('discounts.view'),
            },
            {
                key: 'price_lists', label: t('sidebar.price_lists'), icon: TagsOutlined,
                href: route('business_management.price_lists.index'), inertia: true,
                visible: () => can('price_lists.view'),
            },
            {
                key: 'product_variants', label: t('sidebar.product_variants'), icon: AppstoreAddOutlined,
                href: route('business_management.product_variants.index'), inertia: true,
                visible: () => can('product_variants.view'),
            },
            {
                key: 'deliveries', label: t('sidebar.deliveries'), icon: CarOutlined,
                href: route('business_management.deliveries.index'), inertia: true,
                visible: () => can('deliveries.view'),
            },
            {
                key: 'stock_takes', label: t('sidebar.stock_takes'), icon: CheckSquareOutlined,
                href: route('business_management.stock_takes.index'), inertia: true,
                visible: () => can('stock_takes.view'),
            },
            {
                key: 'exchange_rates', label: t('sidebar.exchange_rates'), icon: SwapOutlined,
                href: route('business_management.exchange_rates.index'), inertia: true,
                visible: () => can('exchange_rates.view'),
            },
            {
                key: 'subscriptions', label: t('sidebar.subscriptions'), icon: SafetyCertificateOutlined,
                href: route('business_management.subscriptions.index'), inertia: true,
                visible: () => isSuperUser.value,
            },
        ],
    },

    // ── Grupo: CRM (Companies, Contacts, Deals, etc.) ────────────────────
    {
        kind: 'group',
        key: 'group-crm', title: t('sidebar.group_crm'),
        items: [
            {
                key: 'companies', label: t('sidebar.companies'), icon: BankOutlined,
                href: route('crm.companies.index'), inertia: true,
                visible: () => can('companies.view'),
            },
            {
                key: 'contacts', label: t('sidebar.contacts'), icon: TeamOutlined,
                href: route('crm.contacts.index'), inertia: true,
                visible: () => can('contacts.view'),
            },
            {
                key: 'pipelines', label: t('sidebar.pipelines'), icon: FunnelPlotOutlined,
                href: route('crm.pipelines.index'), inertia: true,
                visible: () => can('pipelines.view'),
            },
            {
                key: 'deals', label: t('sidebar.deals'), icon: DollarOutlined,
                href: route('crm.deals.index'), inertia: true,
                visible: () => can('deals.view'),
            },
            {
                key: 'activities', label: t('sidebar.activities'), icon: CheckSquareOutlined,
                href: route('crm.activities.index'), inertia: true,
                visible: () => can('activities.view'),
            },
        ],
    },

    // ── Grupo: Reportes (agregaciones read-only sobre CRM/ventas/stock) ──
    {
        kind: 'group',
        key: 'group-reports', title: t('sidebar.group_reports'),
        items: [
            {
                key: 'reports_sales_pipeline', label: t('sidebar.reports_sales_pipeline'), icon: FunnelPlotOutlined,
                href: route('reports.sales_pipeline'), inertia: true,
                visible: () => can('reports.view'),
            },
            {
                key: 'reports_win_rate', label: t('sidebar.reports_win_rate'), icon: PieChartOutlined,
                href: route('reports.win_rate'), inertia: true,
                visible: () => can('reports.view'),
            },
            {
                key: 'reports_revenue', label: t('sidebar.reports_revenue'), icon: LineChartOutlined,
                href: route('reports.revenue'), inertia: true,
                visible: () => can('reports.view'),
            },
            {
                key: 'reports_activity', label: t('sidebar.reports_activity'), icon: BarChartOutlined,
                href: route('reports.activity'), inertia: true,
                visible: () => can('reports.view'),
            },
            {
                key: 'reports_operations', label: t('sidebar.reports_operations'), icon: FundOutlined,
                href: route('reports.operations'), inertia: true,
                visible: () => can('reports.view'),
            },
        ],
    },

    // ── Grupo: Comunicacion (Mensajes + Bandeja) ─────────────────────────
    // Mensajes: solo super (envia anuncios/avisos/debates a la audiencia)
    // Bandeja: todos los users autenticados (lee los mensajes recibidos)
    {
        kind: 'group',
        key: 'group-communication', title: t('sidebar.group_communication'),
        items: [
            {
                key: 'messages', label: t('sidebar.messages'), icon: MessageOutlined,
                href: route('communication.messages.index'), inertia: true,
                visible: () => hasRole('super'),
            },
            {
                key: 'inbox', label: t('sidebar.inbox'), icon: InboxOutlined,
                href: route('communication.inbox.index'), inertia: true,
                visible: () => true,
            },
        ],
    },

    // ── Grupo: Automatizaciones (solo planes con la feature activa) ───────
    {
        kind: 'group',
        key: 'group-automation', title: t('sidebar.group_automation'),
        // Doble gate: rol (super/admin) + feature de plan. Los workers (roles
        // custom como "Customer Editor") NO ven automations aunque su tenant
        // tenga el plan — automations es admin-only. super siempre.
        visible: () => hasRole('super', 'admin') && canUsePlanFeature('automations'),
        items: [
            {
                key: 'automations', label: t('sidebar.automations'), icon: BranchesOutlined,
                href: route('automation_management.automations.index'), inertia: true,
                visible: () => hasRole('super', 'admin') && canUsePlanFeature('automations'),
            },
        ],
    },

    // ── Grupo: Auditoría ──────────────────────────────────────────────────
    {
        kind: 'group',
        key: 'group-audit', title: t('sidebar.group_audit'),
        items: [
            {
                key: 'audit_logs', label: t('sidebar.audit_logs'), icon: AuditOutlined,
                href: route('system_management.audit_logs.index'), inertia: true,
                visible: () => hasRole('super', 'admin') && canUsePlanFeature('audit_log_view'),
            },
        ],
    },

    // ── Grupo: Configuración del sistema (super only) ───────────────
    {
        kind: 'group',
        key: 'group-system', title: t('sidebar.group_system'),
        items: [
            {
                key: 'tenants', label: t('sidebar.tenants'), icon: BankOutlined,
                href: route('system_management.tenants.index'), inertia: true,
                visible: () => hasRole('super'),
            },
            {
                key: 'plans', label: t('sidebar.plans'), icon: SafetyCertificateOutlined,
                href: route('system_management.plans.index'), inertia: true,
                visible: () => hasRole('super'),
            },
            {
                key: 'system_modules', label: t('sidebar.system_modules'), icon: AppstoreOutlined,
                href: route('system_management.system_modules.index'), inertia: true,
                visible: () => hasRole('super'),
            },
            {
                key: 'regions', label: t('sidebar.regions'), icon: GlobalOutlined,
                href: route('system_management.regions.index'), inertia: true,
                visible: () => hasRole('super'),
            },
            {
                key: 'languages', label: t('sidebar.languages'), icon: TranslationOutlined,
                href: route('system_management.languages.index'), inertia: true,
                visible: () => hasRole('super'),
            },
            {
                key: 'countries', label: t('sidebar.countries'), icon: FlagOutlined,
                href: route('system_management.countries.index'), inertia: true,
                visible: () => hasRole('super'),
            },
            {
                key: 'locales', label: t('sidebar.locales'), icon: ReadOutlined,
                href: route('system_management.locales.index'), inertia: true,
                visible: () => hasRole('super'),
            },
            {
                key: 'settings', label: t('sidebar.settings'), icon: SettingOutlined,
                href: route('system_management.settings.index'), inertia: true,
                visible: () => hasRole('super'),
            },
        ],
    },
]);

// Computed: filter items inside groups; drop empty groups; keep ungrouped items.
const visibleStructure = computed(() => {
    return menuStructure.value
        .map(section => {
            if (section.kind === 'item') {
                return section.visible() ? section : null;
            }
            // Group — keep only visible items
            const items = section.items.filter(i => i.visible());
            return items.length > 0 ? { ...section, items } : null;
        })
        .filter(Boolean);
});

// Flat list of clickable items (used by mobile drawer + key resolution).
const flatItems = computed(() =>
    visibleStructure.value.flatMap(s => s.kind === 'item' ? [s] : s.items)
);

// ─── Grupos colapsables del sidebar ────────────────────────────────────────
// Cada grupo (Accesos, Negocio, Configuración del sistema, etc.) se puede
// plegar/expandir. El estado se persiste por usuario en localStorage para que
// la próxima sesión recuerde qué grupos dejó cerrados. Default: todo abierto.
const SIDEBAR_GROUPS_KEY = 'sidebar:collapsed-groups';
const collapsedGroups = ref({});
try {
    const raw = localStorage.getItem(SIDEBAR_GROUPS_KEY);
    if (raw) collapsedGroups.value = JSON.parse(raw) || {};
} catch (e) { /* localStorage no disponible — default todo abierto */ }

const isGroupCollapsed = (key) => !!collapsedGroups.value[key];

const toggleGroup = (key) => {
    collapsedGroups.value = {
        ...collapsedGroups.value,
        [key]: !collapsedGroups.value[key],
    };
    try {
        localStorage.setItem(SIDEBAR_GROUPS_KEY, JSON.stringify(collapsedGroups.value));
    } catch (e) { /* no-op */ }
};

const navigateTo = (item) => {
    drawerOpen.value = false;
    if (item.inertia) router.visit(item.href);
    else              window.location.href = item.href;
};
</script>

<template>
  <ConfigProvider :theme="antdTheme">
    <!-- Overlay para "rotá el celu" — solo aparece en celulares en landscape. -->
    <RotatePortraitOverlay />
    <Layout class="app-shell">
        <!-- Shell Bar (full width, oscuro tipo SAP Fiori) -->
        <LayoutHeader class="shell-bar">
            <div class="shell-bar__left">
                <button class="icon-btn" @click="toggleSidebar" aria-label="menu" :title="$t('global.tools')">
                    <MenuOutlined />
                </button>
                <div class="brand">
                    <div class="brand-logo">
                        <img v-if="appLogo" :src="appLogo" :alt="appName" class="brand-logo-img" />
                        <template v-else>{{ appName.charAt(0) }}</template>
                    </div>
                    <span class="brand-text">{{ appName }}</span>
                </div>
            </div>

            <!-- Busqueda global cross-entidad: Companies / Contacts / Deals /
                 Quotes / Invoices. Scoped por tenant + permisos del user. -->
            <div class="shell-bar__center">
                <GlobalSearch />
            </div>

            <div class="shell-bar__right">
                <!-- Plan info vive ahora SOLO en el dropdown del avatar (debajo
                     del timezone). El topbar quedaba demasiado ruidoso con el
                     badge al lado de las notificaciones. -->

<!-- ICONO 1: Bell de notificaciones del sistema — SOLO descargas + alertas
                     de automatizaciones. NO incluye mensajes (eso vive en el sobre). -->
                <Dropdown
                    :trigger="['click']"
                    overlayClassName="shell-menu-overlay shell-notifications-overlay"
                    placement="bottomRight"
                    @open-change="(open) => open && refreshInbox()"
                >
                    <Badge
                        :count="inbox.unread"
                        :offset="[-6, 6]"
                        size="small"
                        :overflow-count="9"
                    >
                        <button
                            class="icon-btn notif-bell-btn"
                            :class="{ 'notif-bell-btn--alert': inbox.unread > 0 }"
                            :title="$t('global.notifications')"
                            :aria-label="$t('global.notifications')"
                        >
                            <BellOutlined />
                        </button>
                    </Badge>
                    <template #overlay>
                        <div class="notifications-menu">
                            <div class="notifications-menu__header">
                                <span class="notifications-menu__title">{{ $t('global.notifications') }}</span>
                                <span v-if="inbox.processing > 0" class="notifications-menu__pulse">
                                    <LoadingOutlined /> {{ $t('global.generating') }} {{ inbox.processing }}
                                </span>
                            </div>

                            <div v-if="inbox.recent.length === 0" class="notifications-menu__empty">
                                <BellOutlined style="font-size: 1.6rem; color: #cbd5e1;" />
                                <p>{{ $t('global.no_notifications') }}</p>
                                <small>{{ $t('global.no_notifications_hint') }}</small>
                            </div>

                            <ul v-else class="notifications-menu__list">
                                <li
                                    v-for="n in inbox.recent"
                                    :key="n.id"
                                    class="notifications-item"
                                    :class="{
                                        'notifications-item--unread':
                                            (n.kind === 'download' && n.status === 'ready' && !n.downloaded_at)
                                            || (n.kind === 'app' && n.status === 'unread'),
                                    }"
                                    @click="n.kind === 'download' ? triggerDownload(n) : markAppNotificationRead(n)"
                                >
                                    <template v-if="n.kind === 'download'">
                                        <component
                                            :is="downloadFileIcon(n.type).icon"
                                            class="notifications-item__icon"
                                            :style="{ color: downloadFileIcon(n.type).color }"
                                        />
                                        <div class="notifications-item__body">
                                            <div class="notifications-item__name">{{ n.filename }}</div>
                                            <div class="notifications-item__status" :class="`is-${n.status}`">
                                                <LoadingOutlined v-if="n.status === 'processing'" />
                                                <CloseCircleFilled v-else-if="n.status === 'failed'" />
                                                {{ downloadStatusLabel(n.status) }}
                                            </div>
                                        </div>
                                        <div class="notifications-item__actions" @click.stop>
                                            <Tooltip v-if="n.status === 'ready'" :title="$t('global.download')">
                                                <button class="notifications-item__btn" @click="triggerDownload(n)">
                                                    <DownloadOutlined />
                                                </button>
                                            </Tooltip>
                                            <Tooltip :title="$t('global.remove')">
                                                <button class="notifications-item__btn notifications-item__btn--danger" @click="dismissNotification(n)">
                                                    <DeleteOutlined />
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </template>

                                    <template v-else-if="n.kind === 'app'">
                                        <component
                                            :is="appNotifIcon(n)"
                                            class="notifications-item__icon"
                                            :style="{ color: appNotifColor(n) }"
                                        />
                                        <div class="notifications-item__body">
                                            <div class="notifications-item__name">
                                                <span>{{ n.title || $t('global.notification') }}</span>
                                                <Tag
                                                    v-if="isSuperUser && n.tenant_name && n.type === 'automation'"
                                                    color="blue" :bordered="false"
                                                    class="notifications-item__tenant-badge"
                                                >
                                                    {{ n.tenant_name }}
                                                </Tag>
                                            </div>
                                            <div class="notifications-item__app-body">{{ (n.body || '').replace(/\s+/g, ' ').trim() }}</div>
                                        </div>
                                        <!-- Solo notifs que requieren ack (security, plan_change, etc.)
                                             muestran botón eliminar. Las de automation se autoborran
                                             a las 12h (PurgeAutomationNotifications) — sin clutter. -->
                                        <div v-if="n.type !== 'automation'" class="notifications-item__actions" @click.stop>
                                            <Tooltip :title="$t('global.remove')">
                                                <button class="notifications-item__btn notifications-item__btn--danger" @click="dismissNotification(n)">
                                                    <DeleteOutlined />
                                                </button>
                                            </Tooltip>
                                        </div>
                                    </template>
                                </li>
                            </ul>

                            <div class="notifications-menu__footer">
                                <button class="notifications-menu__view-all" @click="goToNotificationsPage">
                                    {{ $t('global.all_notifications') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </Dropdown>

                <!-- ICONO 2: Sobre de mensajes — SOLO Communication module.
                     Dropdown propio con preview de últimos 5 mensajes del user.
                     Click en uno → /inbox/{slug} (auto-marca como leído). -->
                <Dropdown
                    :trigger="['click']"
                    overlayClassName="shell-menu-overlay shell-notifications-overlay"
                    placement="bottomRight"
                    @open-change="(open) => open && refreshInbox()"
                >
                    <Badge
                        :count="inbox.unread_messages"
                        :offset="[-6, 6]"
                        size="small"
                        :overflow-count="9"
                    >
                        <button
                            class="icon-btn notif-bell-btn"
                            :class="{ 'notif-bell-btn--alert': inbox.unread_messages > 0 }"
                            :title="$t('sidebar.messages')"
                            :aria-label="$t('sidebar.messages')"
                        >
                            <MailOutlined />
                        </button>
                    </Badge>
                    <template #overlay>
                        <div class="notifications-menu">
                            <div class="notifications-menu__header">
                                <span class="notifications-menu__title">{{ $t('sidebar.messages') }}</span>
                                <span v-if="inbox.unread_messages > 0" class="notifications-menu__pulse">
                                    {{ inbox.unread_messages }} {{ $t('messages.unread').toLowerCase() }}
                                </span>
                            </div>

                            <div v-if="messagesInBell.length === 0" class="notifications-menu__empty">
                                <MailOutlined style="font-size: 1.6rem; color: #cbd5e1;" />
                                <p>{{ $t('messages.empty_bell') }}</p>
                                <small>{{ $t('messages.empty_bell_hint') }}</small>
                            </div>

                            <ul v-else class="notifications-menu__list">
                                <li
                                    v-for="m in messagesInBell"
                                    :key="m.id"
                                    class="notifications-item"
                                    :class="{ 'notifications-item--unread': m.status === 'unread' }"
                                    @click="goToInboxMessage(m)"
                                >
                                    <MailOutlined
                                        class="notifications-item__icon"
                                        :style="{ color: m.status === 'unread' ? '#0A6ED1' : '#8c8c8c' }"
                                    />
                                    <div class="notifications-item__body">
                                        <div class="notifications-item__name">{{ m.subject }}</div>
                                        <div class="notifications-item__status" :class="m.status === 'unread' ? 'is-ready' : 'is-read'">
                                            {{ m.status === 'unread' ? $t('messages.unread') : $t('messages.read') }}
                                        </div>
                                    </div>
                                </li>
                            </ul>

                            <div class="notifications-menu__footer">
                                <button class="notifications-menu__view-all" @click="router.visit(route('communication.inbox.index'))">
                                    {{ $t('messages.view_inbox') }}
                                </button>
                            </div>
                        </div>
                    </template>
                </Dropdown>

                <!-- Tema -->
                <Dropdown :trigger="['click']" overlayClassName="shell-menu-overlay">
                    <button class="icon-btn" :title="$t('global.change_theme')" :aria-label="$t('global.change_theme')">
                        <DesktopOutlined v-if="themeMode === 'auto'" />
                        <span v-else class="icon-btn__svg" v-html="themeIconSvg" />
                    </button>
                    <template #overlay>
                        <Menu :selectedKeys="[themeMode]" class="shell-menu">
                            <MenuItem key="auto" @click="setTheme('auto')">
                                <DesktopOutlined /> <span>{{ $t('global.theme_auto') }}</span>
                                <CheckOutlined v-if="themeMode === 'auto'" class="shell-menu__check" />
                            </MenuItem>
                            <MenuItem key="light" @click="setTheme('light')">
                                <span class="shell-menu__svg" v-html="sunSvg" /> <span>{{ $t('global.theme_light') }}</span>
                                <CheckOutlined v-if="themeMode === 'light'" class="shell-menu__check" />
                            </MenuItem>
                            <MenuItem key="dark" @click="setTheme('dark')">
                                <span class="shell-menu__svg" v-html="moonSvg" /> <span>{{ $t('global.theme_dark') }}</span>
                                <CheckOutlined v-if="themeMode === 'dark'" class="shell-menu__check" />
                            </MenuItem>
                        </Menu>
                    </template>
                </Dropdown>

                <!-- Idiomas — Fiori-style: solo ícono globo, dropdown con nombres. -->
                <Dropdown :trigger="['click']" overlayClassName="shell-menu-overlay">
                    <button class="icon-btn" :title="$t('global.change_language')" :aria-label="$t('global.change_language')">
                        <GlobalOutlined />
                    </button>
                    <template #overlay>
                        <Menu :selectedKeys="[locale]" class="shell-menu">
                            <MenuItem
                                v-for="l in languages"
                                :key="l.code"
                                @click="switchLang(l.code)"
                            >
                                <span>{{ l.label }}</span>
                                <CheckOutlined v-if="locale === l.code" class="shell-menu__check" />
                            </MenuItem>
                        </Menu>
                    </template>
                </Dropdown>

                <div class="divider" />

                <!-- User -->
                <Dropdown :trigger="['click']" overlayClassName="shell-menu-overlay" placement="bottomRight">
                    <button class="user-trigger">
                        <Avatar
                            :src="user?.photo_url || undefined"
                            :style="{ background: '#0A6ED1' }"
                            :size="28"
                        >
                            {{ user?.name?.charAt(0)?.toUpperCase() }}
                        </Avatar>
                        <span class="user-name">{{ user?.name }}</span>
                        <DownOutlined style="font-size: 0.7rem; opacity: 0.7;" />
                    </button>
                    <template #overlay>
                        <Menu class="shell-menu shell-menu--user">
                            <div class="shell-menu__header">
                                <Avatar
                                    :src="user?.photo_url || undefined"
                                    :style="{ background: '#0A6ED1' }"
                                    :size="36"
                                >
                                    {{ user?.name?.charAt(0)?.toUpperCase() }}
                                </Avatar>
                                <div class="shell-menu__user">
                                    <span class="shell-menu__user-name">{{ user?.name }}</span>
                                    <span class="shell-menu__user-email">{{ user?.email }}</span>
                                </div>
                            </div>

                            <!-- Timezone effective — muestra al user en qué TZ está
                                 viendo todas las fechas (resuelto por backend en
                                 Tz::for($user)). Reduce la confusión "¿por qué
                                 este timestamp dice 14:00 si son las 09:00?". -->
                            <div v-if="user?.timezone" class="shell-menu__plan">
                                <div class="shell-menu__plan-row">
                                    <span class="shell-menu__plan-label">
                                        <ClockCircleOutlined /> {{ $t('global.timezone') }}
                                    </span>
                                    <Tooltip :title="$t('global.timezone_hint')">
                                        <span class="shell-menu__plan-value">{{ user.timezone }}</span>
                                    </Tooltip>
                                </div>
                            </div>

                            <!-- Plan info — ubicado debajo del timezone (lugar
                                 secundario, no compite con notificaciones). Solo
                                 visible si el user tiene tenant. -->
                            <div v-if="user?.plan_info" class="shell-menu__plan">
                                <div class="shell-menu__plan-row">
                                    <span class="shell-menu__plan-label">{{ $t('plans.singular') }}</span>
                                    <Tag :color="user.plan_info.color || 'default'" :bordered="false">
                                        {{ user.plan_info.name }}
                                    </Tag>
                                </div>
                                <div v-if="user.plan_info.days_remaining !== null" class="shell-menu__plan-row">
                                    <span class="shell-menu__plan-label">{{ $t('subscriptions.days_remaining') }}</span>
                                    <span class="shell-menu__plan-value" :class="{ 'is-urgent': user.plan_info.days_remaining <= 7 }">
                                        {{ user.plan_info.days_remaining }}
                                    </span>
                                </div>
                            </div>

                            <MenuDivider />
                            <MenuItem key="profile" @click="goToProfile">
                                <UserOutlined /> <span>{{ $t('global.my_profile') }}</span>
                            </MenuItem>
                            <MenuItem key="settings" @click="goToProfilePrefs">
                                <SettingOutlined /> <span>{{ $t('global.settings') }}</span>
                            </MenuItem>

                            <!-- Recientes — últimos 10 registros vistos por
                                 el usuario (cualquier módulo). Aparece solo
                                 si hay elementos. Usamos un sub-menu para
                                 no inflar el dropdown principal. -->
                            <SubMenu key="recents">
                                <template #icon><HistoryOutlined /></template>
                                <template #title>{{ $t('global.recent_items') }}</template>
                                <MenuItem v-if="recentViews.length === 0" key="no-recent" disabled>
                                    <span style="color: #94a3b8;">{{ $t('global.no_recent_items') }}</span>
                                </MenuItem>
                                <MenuItem
                                    v-for="r in recentViews"
                                    :key="`recent-${r.module}-${r.id}`"
                                    @click="goToRecent(r)"
                                >
                                    <span class="recent-item">
                                        <span class="recent-item__name">{{ r.name }}</span>
                                        <span class="recent-item__module">{{ r.module }}</span>
                                    </span>
                                </MenuItem>
                            </SubMenu>

                            <MenuDivider />
                            <MenuItem key="logout" @click="logout" class="shell-menu__logout">
                                <LogoutOutlined /> <span>{{ $t('global.logout') }}</span>
                            </MenuItem>
                        </Menu>
                    </template>
                </Dropdown>
            </div>
        </LayoutHeader>

        <!-- Below the shell: sidebar + content -->
        <Layout class="below-shell">
            <!-- Sidebar desktop -->
            <LayoutSider
                v-if="!isMobile"
                v-model:collapsed="collapsed"
                :width="240"
                :collapsed-width="64"
                :trigger="null"
                class="app-sider"
            >
                <Menu
                    mode="inline"
                    :selectedKeys="[selectedKey]"
                    :style="{ background: 'transparent', borderRight: 0 }"
                >
                    <template v-for="section in visibleStructure" :key="section.key">
                        <!-- Standalone item -->
                        <MenuItem
                            v-if="section.kind === 'item'"
                            :key="section.key"
                            @click="navigateTo(section)"
                        >
                            <component :is="section.icon" />
                            <span>{{ section.label }}</span>
                        </MenuItem>

                        <!-- Grouped items — header clickeable que pliega/expande -->
                        <MenuItemGroup v-else>
                            <template #title>
                                <button
                                    type="button"
                                    class="group-header"
                                    :class="{ 'group-header--collapsed': isGroupCollapsed(section.key) }"
                                    @click="toggleGroup(section.key)"
                                >
                                    <span class="group-header__label">{{ section.title }}</span>
                                    <DownOutlined class="group-header__chevron" />
                                </button>
                            </template>
                            <!-- En modo iconos (sidebar colapsado) ignoramos el
                                 plegado de grupos: mostramos todos los iconos.
                                 El plegado solo aplica en modo expandido. -->
                            <template v-if="collapsed || !isGroupCollapsed(section.key)">
                                <MenuItem
                                    v-for="item in section.items"
                                    :key="item.key"
                                    :disabled="item.disabled"
                                    @click="!item.disabled && navigateTo(item)"
                                >
                                    <Tooltip v-if="item.tooltip" :title="item.tooltip" placement="right">
                                        <span class="menu-row">
                                            <component :is="item.icon" />
                                            <span>{{ item.label }}</span>
                                        </span>
                                    </Tooltip>
                                    <template v-else>
                                        <component :is="item.icon" />
                                        <span>{{ item.label }}</span>
                                    </template>
                                </MenuItem>
                            </template>
                        </MenuItemGroup>
                    </template>
                </Menu>
            </LayoutSider>

            <!-- Sidebar mobile (Drawer) — fullscreen con header (título + close).
                 Patrón Yape/iOS Mail: el menú toma toda la pantalla, con título
                 claro y X grande para cerrar (touch target 44px). -->
            <Drawer
                v-model:open="drawerOpen"
                placement="left"
                width="100%"
                :title="$t('global.menu')"
                :closable="true"
                :body-style="{ padding: 0, background: 'var(--color-surface)' }"
                wrap-class-name="sidebar-mobile-drawer"
            >
                <Menu
                    mode="inline"
                    :selectedKeys="[selectedKey]"
                    :style="{ background: 'transparent', borderRight: 0 }"
                >
                    <template v-for="section in visibleStructure" :key="section.key">
                        <MenuItem
                            v-if="section.kind === 'item'"
                            :key="section.key"
                            @click="navigateTo(section)"
                        >
                            <component :is="section.icon" />
                            <span>{{ section.label }}</span>
                        </MenuItem>

                        <MenuItemGroup v-else>
                            <template #title>
                                <button
                                    type="button"
                                    class="group-header"
                                    :class="{ 'group-header--collapsed': isGroupCollapsed(section.key) }"
                                    @click="toggleGroup(section.key)"
                                >
                                    <span class="group-header__label">{{ section.title }}</span>
                                    <DownOutlined class="group-header__chevron" />
                                </button>
                            </template>
                            <template v-if="!isGroupCollapsed(section.key)">
                                <MenuItem
                                    v-for="item in section.items"
                                    :key="item.key"
                                    :disabled="item.disabled"
                                    @click="!item.disabled && navigateTo(item)"
                                >
                                    <component :is="item.icon" />
                                    <span>{{ item.label }}</span>
                                </MenuItem>
                            </template>
                        </MenuItemGroup>
                    </template>
                </Menu>
            </Drawer>

            <Layout class="content-layout">
                <!-- TabsBar (estilo SAP Fiori) deshabilitado por preferencia del usuario.
                     Cada navegación reemplaza la página actual (patrón estándar Inertia).
                     <TabsBar /> -->


                <!-- Banner global de suscripción. Solo si backend mandó props.subscription
                     (trial activo OR <= 7 días para expirar). super no lo ve. -->
                <Alert
                    v-if="subscriptionWarning"
                    :type="subscriptionWarning.days_remaining <= 0 ? 'error' : 'warning'"
                    show-icon
                    banner
                    closable
                    class="subscription-banner"
                >
                    <template #message>
                        <template v-if="subscriptionWarning.is_trial">
                            {{ $t('subscriptions.status_trial') }} ·
                            {{ $t('subscriptions.days_remaining_n', { count: subscriptionWarning.days_remaining }, subscriptionWarning.days_remaining) }}
                        </template>
                        <template v-else-if="subscriptionWarning.days_remaining > 0">
                            {{ $t('subscriptions.expires_in_warning', { days: subscriptionWarning.days_remaining }) }}
                        </template>
                        <template v-else>
                            {{ $t('subscriptions.expired_warning') }}
                        </template>
                    </template>
                </Alert>

                <LayoutContent class="content">
                    <slot />
                </LayoutContent>
            </Layout>
        </Layout>
    </Layout>
  </ConfigProvider>
</template>

<style scoped>
/* =========================================================================
   SAP Fiori palette (Quartz Light)
   --shell:    #354A5F (top bar)
   --brand:    #0A6ED1 (SAP Blue)
   --brand-soft: #E6F1FB (selección)
   --page-bg:  #F7F7F7
   --text:     #32363A
   --text-soft:#6A6D70
   --border:   #D9D9D9
   --border-soft: #E5E5E5
   ========================================================================= */

.app-shell { min-height: 100vh; }

/* ─── Shell Bar (full width arriba) ─────────────────────────────────────── */
.shell-bar {
    background: #354A5F;
    padding: 0 8px;
    height: 44px;
    line-height: 44px;
    display: flex;
    align-items: center;
    justify-content: space-between;
    border-bottom: 0;
    position: sticky;
    top: 0;
    z-index: 100;
}
.shell-bar__left, .shell-bar__right { display: flex; align-items: center; gap: 2px; }
.shell-bar__center {
    flex: 1;
    display: flex;
    justify-content: center;
    padding: 0 16px;
    max-width: 480px;
    margin: 0 auto;
}
@media (max-width: 640px) {
    .shell-bar__center { display: none; }
}

.brand {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 0 12px;
    color: #ffffff;
}
.brand-logo {
    width: 26px;
    height: 26px;
    border-radius: 4px;
    background: #0A6ED1;
    display: flex;
    align-items: center;
    justify-content: center;
    font-weight: 700;
    font-size: 0.8rem;
    flex-shrink: 0;
    color: #ffffff;
    overflow: hidden;
}
.brand-logo:has(.brand-logo-img) { background: transparent; }
.brand-logo-img {
    width: 100%;
    height: 100%;
    object-fit: contain;
}
.brand-text {
    font-size: 0.95rem;
    font-weight: 400;
    letter-spacing: 0.01em;
    white-space: nowrap;
    color: #ffffff;
}

/* Botones del shell bar — densidad SAP Fiori "default" (36px) */
.icon-btn {
    background: transparent;
    border: 0;
    width: 36px;
    height: 36px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: rgba(255, 255, 255, 0.92);
    font-size: 1rem;
    transition: background 0.15s ease, color 0.15s ease;
    padding: 0;
}
.icon-btn :deep(.anticon) { font-size: 18px; }
.icon-btn:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #ffffff;
}
.icon-btn:active {
    background: rgba(255, 255, 255, 0.18);
}

/* SVG inline (sun/moon) dentro de .icon-btn — hereda el color del botón */
.icon-btn__svg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
}
.icon-btn__svg :deep(svg),
.icon-btn__svg svg {
    width: 18px;
    height: 18px;
    display: block;
}

.divider {
    width: 1px;
    height: 20px;
    background: rgba(255, 255, 255, 0.20);
    margin: 0 8px;
}

.user-trigger {
    display: flex;
    align-items: center;
    gap: 8px;
    cursor: pointer;
    padding: 0 10px 0 4px;
    border: 0;
    background: transparent;
    border-radius: 2px;
    transition: background 0.12s ease;
    height: 36px;
    color: #ffffff;
}
.user-trigger:hover { background: rgba(255, 255, 255, 0.10); }
.user-name { font-weight: 500; color: #ffffff; font-size: 0.85rem; }

.shell-bar :deep(.ant-badge-count) {
    box-shadow: 0 0 0 1px #354A5F;
}

/* ─── Below shell: sidebar + content ──────────────────────────────────── */
.subscription-banner {
    border-radius: 0;
    margin: 0;
}
.below-shell {
    flex-direction: row;
    min-height: calc(100vh - 44px);
}

.app-sider {
    background: #ffffff !important;
    border-right: 1px solid #E5E5E5;
    box-shadow: none;
}

/* Sidebar menu items — Fiori Launchpad */
.app-sider :deep(.ant-menu-light) {
    padding-top: 8px;
}
.app-sider :deep(.ant-menu-light .ant-menu-item) {
    color: #32363A;
    margin: 2px 0 !important;
    border-radius: 0 !important;
    width: 100% !important;
    padding-left: 16px !important;
    height: 40px;
    line-height: 40px;
    font-size: 0.875rem;
}
.app-sider :deep(.ant-menu-light .ant-menu-item .anticon) {
    color: #6A6D70;
    font-size: 16px;
    margin-right: 12px;
    vertical-align: -2px;
}
.app-sider :deep(.ant-menu-light .ant-menu-item:hover) {
    background-color: #F0F6FB !important;
    color: #0A6ED1;
}
.app-sider :deep(.ant-menu-light .ant-menu-item:hover .anticon) {
    color: #0A6ED1;
}
.app-sider :deep(.ant-menu-light .ant-menu-item-selected) {
    background-color: #E6F1FB !important;
    color: #0A6ED1 !important;
    border-left: 3px solid #0A6ED1;
    padding-left: 13px !important;
    font-weight: 600;
}
.app-sider :deep(.ant-menu-light .ant-menu-item-selected .anticon) {
    color: #0A6ED1 !important;
}
.app-sider :deep(.ant-menu-light .ant-menu-item-selected::after) {
    display: none;
}

/* ─── Group titles (Accesos / Auditoría / Configuración del sistema) ─── */
/* El título del grupo es un botón clickeable que pliega/expande sus ítems.
   Reseteamos el padding del wrapper de Ant para que el botón lo controle. */
.app-sider :deep(.ant-menu-item-group-title),
.sidebar-mobile-drawer :deep(.ant-menu-item-group-title) {
    padding: 0 !important;
}
.group-header {
    width: 100%;
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 8px;
    background: transparent;
    border: 0;
    cursor: pointer;
    padding: 14px 16px 6px;
    font-size: 0.7rem;
    text-transform: uppercase;
    letter-spacing: 0.08em;
    color: #94a3b8;
    font-weight: 600;
    line-height: 1.2;
    transition: color 0.12s ease;
}
.group-header:hover { color: #0A6ED1; }
.group-header__label { flex: 1; text-align: left; }
.group-header__chevron {
    font-size: 0.6rem !important;
    opacity: 0.6;
    transition: transform 0.18s ease;
}
/* Colapsado → chevron apunta a la derecha (rota -90deg). Expandido → abajo. */
.group-header--collapsed .group-header__chevron {
    transform: rotate(-90deg);
}
/* Modo iconos (sidebar a 64px): el texto del header de grupo no entra y se ve
   apretado. Lo ocultamos — en modo iconos solo se ven los iconos de los items.
   Dejamos un separador fino para mantener la division visual entre grupos. */
.app-sider :deep(.ant-menu-inline-collapsed .ant-menu-item-group-title) {
    padding: 0 !important;
    height: 1px;
    overflow: hidden;
    margin: 6px 12px;
    background: var(--color-border-strong);
}
.app-sider :deep(.ant-menu-inline-collapsed) .group-header {
    display: none;
}

/* Disabled items (Profiles / Permissions — coming soon) */
.app-sider :deep(.ant-menu-item-disabled) {
    color: #cbd5e1 !important;
    cursor: not-allowed !important;
}
.app-sider :deep(.ant-menu-item-disabled .anticon) {
    color: #cbd5e1 !important;
}

/* ─── Content ─────────────────────────────────────────────────────────── */
.content {
    padding: 24px;
    background: #F7F7F7;
}

/* ─── Responsive ──────────────────────────────────────────────────────── */
@media (max-width: 991px) {
    .shell-bar { padding: 0 4px; }
    .brand-text { display: none; }
    .user-name { display: none; }
    .content { padding: 16px; }
}

/* ─── Badge sobre el icon-btn (alineado a la esquina) ─────────────────── */
.shell-bar :deep(.ant-badge) { display: inline-flex; }
.shell-bar :deep(.ant-badge-count) {
    box-shadow: 0 0 0 1.5px #354A5F !important;
    font-size: 0.65rem !important;
    height: 16px !important;
    min-width: 16px !important;
    line-height: 16px !important;
    padding: 0 4px !important;
}
</style>

<!-- =========================================================================
     GLOBAL styles (NOT scoped) — aplica al document.documentElement
     ========================================================================= -->
<style>
/* Dark mode (SAP Quartz Dark) — aplicado vía data-theme="dark" en <html> */
html[data-theme="dark"] body { background: #1d2126; color: #e5e6e7; }

html[data-theme="dark"] .shell-bar { background: #1c2228 !important; }

html[data-theme="dark"] .app-sider { background: #29313a !important; }
html[data-theme="dark"] .app-sider .ant-menu-light .ant-menu-item { color: #e5e6e7; }
html[data-theme="dark"] .app-sider .ant-menu-light .ant-menu-item .anticon { color: #a8aaae; }
html[data-theme="dark"] .app-sider .ant-menu-light .ant-menu-item:hover {
    background-color: #313a44 !important;
    color: #4db6e8 !important;
}
html[data-theme="dark"] .app-sider .ant-menu-light .ant-menu-item:hover .anticon { color: #4db6e8 !important; }
html[data-theme="dark"] .app-sider .ant-menu-light .ant-menu-item-selected {
    background-color: rgba(77, 182, 232, 0.12) !important;
    color: #4db6e8 !important;
    border-left-color: #4db6e8 !important;
}
html[data-theme="dark"] .app-sider .ant-menu-light .ant-menu-item-selected .anticon { color: #4db6e8 !important; }
html[data-theme="dark"] .app-sider .ant-menu-item-group-title { color: #6b7785 !important; }
html[data-theme="dark"] .app-sider .ant-menu-item-disabled { color: #4b5563 !important; }
html[data-theme="dark"] .app-sider .ant-menu-item-disabled .anticon { color: #4b5563 !important; }

html[data-theme="dark"] .content { background: #1d2126 !important; color: #e5e6e7; }

/* Adjust shell-bar's badge ring color in dark mode */
html[data-theme="dark"] .shell-bar .ant-badge-count { box-shadow: 0 0 0 1.5px #1c2228 !important; }

/* Tabs bar dark mode (the component already handles its own, but reinforce here) */
html[data-theme="dark"] .tabs-bar { background: #29313a !important; border-bottom-color: #3f4448 !important; }

/* ─── Shell dropdowns (tema / idioma) — Fiori-style menu ─────────────────
   Vive en un portal fuera de .app-shell, por eso NO va scoped. */
.shell-menu-overlay .ant-dropdown-menu {
    border-radius: 6px !important;
    padding: 6px !important;
    min-width: 180px;
    box-shadow: 0 8px 28px rgba(15, 23, 42, 0.18) !important;
}
.shell-menu .ant-dropdown-menu-item {
    border-radius: 4px !important;
    padding: 8px 12px !important;
    font-size: 0.875rem;
}
/* Ant envuelve el contenido en .ant-dropdown-menu-title-content; ahí va el flex
   para que margin-left:auto del check funcione. */
.shell-menu .ant-dropdown-menu-item .ant-dropdown-menu-title-content {
    display: flex !important;
    align-items: center;
    width: 100%;
}
.shell-menu .ant-dropdown-menu-title-content > .anticon,
.shell-menu .ant-dropdown-menu-title-content > .shell-menu__svg {
    margin-right: 10px;
    flex-shrink: 0;
}
.shell-menu__svg {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 14px;
    height: 14px;
}
.shell-menu__svg svg { width: 14px; height: 14px; }
.shell-menu .ant-dropdown-menu-title-content > .shell-menu__check {
    margin-left: auto;
    margin-right: 0;
    color: #0A6ED1;
    font-size: 0.8rem;
}
.shell-menu .ant-dropdown-menu-item-selected {
    background: rgba(10, 110, 209, 0.08) !important;
    color: #0A6ED1 !important;
    font-weight: 500;
}

/* User dropdown — header con avatar grande + email */
.shell-menu--user { min-width: 240px !important; }
.shell-menu__header {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 12px 12px 10px;
}
.shell-menu__user {
    display: flex;
    flex-direction: column;
    line-height: 1.25;
    min-width: 0;
}
.shell-menu__user-name {
    font-size: 0.875rem;
    font-weight: 600;
    color: #1f2937;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.shell-menu__user-email {
    font-size: 0.75rem;
    color: #6A6D70;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.shell-menu__plan {
    padding: 8px 12px;
    background: #F8FAFC;
    border-top: 1px solid #E5E7EB;
    border-bottom: 1px solid #E5E7EB;
}
.shell-menu__plan-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 0.8125rem;
    padding: 2px 0;
}
.shell-menu__plan-label { color: #6A6D70; }
.shell-menu__plan-value { font-weight: 600; color: #1f2937; }
.shell-menu__plan-value.is-urgent { color: #ea580c; }
html[data-theme="dark"] .shell-menu__plan { background: #2c3034; border-color: #3f4448; }
html[data-theme="dark"] .shell-menu__plan-label { color: #a8aaae; }
html[data-theme="dark"] .shell-menu__plan-value { color: #e5e6e7; }
.shell-menu__logout .ant-dropdown-menu-title-content {
    color: #BB0000;  /* SAP Fiori semantic red */
}
.shell-menu__logout:hover .ant-dropdown-menu-title-content,
.shell-menu__logout:hover .ant-dropdown-menu-title-content > .anticon {
    color: #BB0000 !important;
}
.shell-menu__logout:hover {
    background: rgba(187, 0, 0, 0.06) !important;
}

/* Recent items en el dropdown del avatar — nombre principal en negro,
   módulo en gris pequeño abajo a modo subtítulo. Ant Design no permite
   styling rico en MenuItem por default, así que nuestro <span class="recent-item"
   /> aplica flex column. */
.recent-item {
    display: flex;
    flex-direction: column;
    line-height: 1.2;
    overflow: hidden;
    max-width: 220px;
}
.recent-item__name {
    font-size: 0.875rem;
    color: #1f2937;
    font-weight: 500;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.recent-item__module {
    font-size: 0.7rem;
    color: #94a3b8;
    text-transform: uppercase;
    letter-spacing: 0.04em;
    margin-top: 2px;
}
html[data-theme="dark"] .recent-item__name { color: #e5e6e7; }
html[data-theme="dark"] .recent-item__module { color: #6b7785; }

/* Dark mode del dropdown — portal vive fuera de .app-shell */
html[data-theme="dark"] .shell-menu-overlay .ant-dropdown-menu {
    background: #2c3034 !important;
    box-shadow: 0 8px 28px rgba(0, 0, 0, 0.5) !important;
}
html[data-theme="dark"] .shell-menu .ant-dropdown-menu-item { color: #e5e6e7 !important; }
html[data-theme="dark"] .shell-menu .ant-dropdown-menu-item:hover {
    background: #313a44 !important;
    color: #4db6e8 !important;
}
html[data-theme="dark"] .shell-menu .ant-dropdown-menu-item-selected {
    background: rgba(77, 182, 232, 0.12) !important;
    color: #4db6e8 !important;
}
html[data-theme="dark"] .shell-menu .ant-dropdown-menu-title-content > .shell-menu__check {
    color: #4db6e8;
}
html[data-theme="dark"] .shell-menu__user-name { color: #e5e6e7; }
html[data-theme="dark"] .shell-menu__user-email { color: #a8aaae; }
html[data-theme="dark"] .shell-menu .ant-dropdown-menu-item-divider {
    background-color: #3f4448 !important;
}
html[data-theme="dark"] .shell-menu__logout .ant-dropdown-menu-title-content,
html[data-theme="dark"] .shell-menu__logout:hover .ant-dropdown-menu-title-content,
html[data-theme="dark"] .shell-menu__logout:hover .ant-dropdown-menu-title-content > .anticon {
    color: #ff6b6b !important;
}
html[data-theme="dark"] .shell-menu__logout:hover {
    background: rgba(255, 107, 107, 0.10) !important;
}

/* ── Bell de descargas — dropdown con la lista de archivos generados ──── */
.shell-notifications-overlay .ant-dropdown-menu {
    padding: 0 !important;
    min-width: 320px !important;
    max-width: 360px;
}
.notifications-menu {
    background: #ffffff;
    border-radius: 6px;
    overflow: hidden;
}
.notifications-menu__header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 12px 14px;
    border-bottom: 1px solid #E5E5E5;
}
.notifications-menu__title {
    font-size: 0.875rem;
    font-weight: 600;
    color: #32363A;
}
.notifications-menu__pulse {
    font-size: 0.72rem;
    color: #0A6ED1;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.notifications-menu__empty {
    padding: 28px 16px;
    text-align: center;
    color: #6A6D70;
}
.notifications-menu__empty p { margin: 8px 0 4px; font-size: 0.875rem; color: #32363A; }
.notifications-menu__empty small { font-size: 0.78rem; color: #6A6D70; }

.notifications-menu__list {
    list-style: none;
    margin: 0;
    padding: 0;
    max-height: 380px;
    overflow-y: auto;
}
.notifications-item {
    display: flex;
    align-items: center;
    gap: 12px;
    padding: 10px 14px;
    border-bottom: 1px solid #F0F0F0;
    cursor: pointer;
    transition: background 0.12s ease;
    position: relative;
}
.notifications-item:last-child { border-bottom: 0; }
.notifications-item:hover { background: #F0F6FB; }
.notifications-item--ready::before {
    content: "";
    position: absolute;
    left: 0;
    top: 0;
    bottom: 0;
    width: 3px;
    background: #0A6ED1;
}
.notifications-item__icon {
    font-size: 1.6rem;
    flex-shrink: 0;
}
.notifications-item__body { flex: 1; min-width: 0; }
.notifications-item__name {
    font-size: 0.8125rem;
    font-weight: 500;
    color: #32363A;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
.notifications-item__status {
    font-size: 0.72rem;
    color: #6A6D70;
    margin-top: 2px;
    display: inline-flex;
    align-items: center;
    gap: 4px;
}
.notifications-item__status.is-processing { color: #0A6ED1; }
.notifications-item__status.is-ready      { color: #1D7044; font-weight: 600; }
.notifications-item__status.is-failed     { color: #C8281D; }
.notifications-item__app-body {
    font-size: 0.72rem;
    color: #6A6D70;
    margin-top: 2px;
    line-height: 1.35;
    /* 1 sola linea con elipsis — el detalle completo se ve en /notifications.
       El bell debe ser un "preview" rapido. */
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
}
html[data-theme="dark"] .notifications-item__app-body { color: #a0a3a6; }
/* Tag con el nombre del workspace al lado del titulo — solo visible para
   super con notifs de automation, para distinguir cross-tenant. */
.notifications-item__tenant-badge {
    margin-left: 6px !important;
    font-size: 0.65rem !important;
    line-height: 1.4 !important;
    padding: 0 6px !important;
    vertical-align: middle;
}

.notifications-item__actions { display: inline-flex; gap: 2px; flex-shrink: 0; }
.notifications-item__btn {
    background: transparent;
    border: 0;
    width: 28px;
    height: 28px;
    border-radius: 4px;
    display: inline-flex;
    align-items: center;
    justify-content: center;
    cursor: pointer;
    color: #6A6D70;
    transition: background 0.12s ease, color 0.12s ease;
}
.notifications-item__btn:hover { background: #E6F1FB; color: #0A6ED1; }
.notifications-item__btn--danger:hover { background: rgba(200, 40, 29, 0.08); color: #C8281D; }

.notifications-menu__footer {
    border-top: 1px solid #E5E5E5;
    padding: 8px;
    background: #F8FAFC;
}
.notifications-menu__view-all {
    width: 100%;
    background: transparent;
    border: 0;
    padding: 8px 12px;
    font-size: 0.8125rem;
    font-weight: 500;
    color: #0A6ED1;
    cursor: pointer;
    border-radius: 4px;
    transition: background 0.12s ease;
}
.notifications-menu__view-all:hover { background: #E6F1FB; }

/* Dark mode */
html[data-theme="dark"] .notifications-menu { background: #2c3034; }
html[data-theme="dark"] .notifications-menu__header { border-bottom-color: #3f4448; }
html[data-theme="dark"] .notifications-menu__title { color: #e5e6e7; }
html[data-theme="dark"] .notifications-menu__pulse { color: #4db6e8; }
html[data-theme="dark"] .notifications-menu__empty p { color: #e5e6e7; }
html[data-theme="dark"] .notifications-menu__empty small { color: #a8aaae; }
html[data-theme="dark"] .notifications-item { border-bottom-color: #3f4448; }
html[data-theme="dark"] .notifications-item:hover { background: #313a44; }
html[data-theme="dark"] .notifications-item--ready::before { background: #4db6e8; }
html[data-theme="dark"] .notifications-item__name { color: #e5e6e7; }
html[data-theme="dark"] .notifications-item__btn { color: #a8aaae; }
html[data-theme="dark"] .notifications-item__btn:hover { background: rgba(77, 182, 232, 0.12); color: #4db6e8; }
html[data-theme="dark"] .notifications-menu__footer { background: #29313a; border-top-color: #3f4448; }
html[data-theme="dark"] .notifications-menu__view-all { color: #4db6e8; }
html[data-theme="dark"] .notifications-menu__view-all:hover { background: rgba(77, 182, 232, 0.12); }
</style>
