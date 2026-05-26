<?php

namespace App\Http\Middleware;

use App\Models\Download;
use App\Support\Tz;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template loaded on the first page visit.
     */
    protected $rootView = 'app';

    /**
     * Determine the asset version (cache-busting on deploy).
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Props shared with every Inertia response.
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        return array_merge(parent::share($request), [
            'auth' => [
                'user' => $user ? [
                    'id'           => $user->id,
                    'name'         => $user->name,
                    'email'        => $user->email,
                    // photo_url tiene cache-busting (?v={updated_at}) — el header
                    // avatar y cualquier render del avatar del usuario logueado
                    // se refresca automatico cuando sube una foto nueva.
                    'photo_url'    => $user->photo_url,
                    'permissions'  => $user->getAllPermissions()->pluck('name'),
                    'roles'        => $user->getRoleNames(),
                    // Mapa de módulos → ISO timestamp del tour completado.
                    // El frontend usa esto para decidir si dispara el
                    // onboarding tour de un módulo en su primera visita.
                    'module_tours' => $user->module_tours ?? [],
                    // Features del plan del tenant — el frontend las usa para
                    // mostrar/ocultar entradas del sidebar según lo que el plan
                    // permite. super no tiene tenant: se le da acceso a todo.
                    'plan_features' => $this->buildPlanFeatures($user),
                    // Info visible del plan: slug, name, icon, color, dias
                    // restantes. Lo usa el badge del header + dropdown del avatar.
                    'plan_info'     => $this->buildPlanInfo($user),
                    // TZ efectivo resuelto por backend (user → tenant → country
                    // → UTC). Siempre devuelve string no-null. El frontend
                    // (useDateFormat) lo usa para mostrar fechas en el huso
                    // horario del usuario, sin tener que recalcularlo por vista.
                    'timezone'      => Tz::for($user),
                ] : null,
            ],
            // Bloque `tz`:
            //   - default: TZ del user actual, listo para usar en cualquier vista.
            //   - available: lista completa de timezones para los selectores
            //     (Profile, Tenant edit). Se cachea forever — la lista no
            //     cambia entre requests; tampoco vale la pena recargarla en
            //     cada response.
            'tz' => [
                'default'   => $user ? Tz::for($user) : config('app.timezone', 'UTC'),
                // Closure → solo se evalúa si la vista lee la prop. Combina
                // con Cache::rememberForever para evitar el filter() en
                // cada request donde sí se accede.
                'available' => fn () => Cache::rememberForever(
                    'tz.available_timezones.v1',
                    fn () => Tz::availableTimezones(),
                ),
            ],
            'flash' => [
                // pull() lee Y BORRA en el mismo paso — garantiza que el flash
                // se consuma una sola vez. Sin esto, en Inertia SPA el flash
                // sobrevive entre XHRs y los toasts aparecen en cada nav.
                'success'      => fn () => $request->session()->pull('success'),
                'error'        => fn () => $request->session()->pull('error'),
                // One-time-only API token returned by Workspaces > tokens.create.
                // Shown to super in the Show page modal and never again.
                'newToken'     => fn () => $request->session()->pull('newToken'),
                // Para el patrón "Eliminado. Deshacer (60s)": el controller
                // de delete deja un claim en sesión y un payload en flash;
                // el frontend muestra el toast con botón Undo.
                'recentDelete' => fn () => $request->session()->pull('recentDelete'),
            ],
            // Recientes del usuario — últimos 10 registros vistos (cualquier
            // módulo). El frontend los muestra en el dropdown del avatar para
            // que el usuario pueda volver rápido a algo que estaba mirando.
            'recentViews' => fn () => $user ? $this->buildRecentViewsPayload($user->id) : [],
            // Inbox del bell — recent + unread + processing para el badge.
            // El nombre `inbox` evita name-collision con el page-prop
            // `notifications` que la página /notifications usa para su
            // listado paginado.
            'inbox' => fn () => $user ? $this->buildInboxPayload($user->id) : null,
            // Frecuencia del polling del bell — configurable desde el modulo
            // Settings (key `notifications.poll_interval_seconds`). El frontend
            // lo clampea a [1, 60]. Closure → solo se evalua si la vista la lee.
            'notificationsPollInterval' => fn () => \App\Models\Setting::getInt('notifications.poll_interval_seconds', 4),
            // Branding global — leido desde Settings. Cualquier vista puede usar
            // page.props.appName / page.props.appLogoUrl para mostrarlos. Sin
            // duplicar logica en cada modulo.
            'appName'    => fn () => \App\Models\Setting::get('app.name', config('app.name', 'Application Name')),
            'appLogoUrl' => fn () => \App\Models\Setting::get('app.logo_url', '') ?: null,
            // Feature flag: si false el Login.vue oculta el boton "Continuar
            // con Google". Setting tiene prioridad sobre la presencia de las
            // credenciales OAuth en .env.
            'googleLoginEnabled' => fn () => \App\Models\Setting::getBool('features.google_login_enabled', false),
            'locale' => app()->getLocale(),
            // Idiomas disponibles para el selector del navbar. Intersección de:
            //   1) config('laravellocalization.supportedLocales') — locales que el router URL acepta
            //   2) Language::where('is_active', true) — los que el super tiene activos en el módulo
            // Si super desactiva uno desde la UI core, desaparece del dropdown.
            // Para agregar un idioma nuevo: alta en módulo Languages + alta en config laravellocalization.
            'availableLocales' => fn () => $this->buildAvailableLocales(),
            // Traducciones del locale actual cargadas desde lang/{locale}/*.php.
            // Esto deja un solo source of truth (PHP lang files) y permite a
            // Vue usar $t('global.active') con el mismo string que __() en
            // PHP/Blade. Solo cargamos los namespaces que el frontend usa
            // (no los de email/auth) para mantener el payload chico.
            'translations' => fn () => $this->loadTranslations(),
            'app'    => [
                'name' => config('app.name'),
            ],
            // Estado de suscripción del tenant del user — drives el banner global
            // de warning ("tu plan expira en N días") en AppLayout. null si el user
            // no tiene tenant (super) o si tenant no fue resuelto.
            'subscription' => fn () => $this->buildSubscriptionStatus($user),
        ]);
    }

    /**
     * Construye el mapa de features del plan actual del tenant del user.
     * Super recibe `__all__: true` para que el frontend lo trate como
     * full-access sin tener que enumerar cada feature key.
     */
    protected function buildPlanFeatures($user): array
    {
        if ($user->hasRole('super')) {
            return ['__all__' => true];
        }
        if (!$user->tenant_id) return [];
        $tenant = $user->tenant;
        if (!$tenant) return [];
        $plan = \App\Models\Plan::findBySlug($tenant->currentPlan());
        return $plan?->features ?? [];
    }

    /**
     * Info compacta del plan para mostrarlo en el header + dropdown del avatar.
     * Devuelve null para super (no tiene plan asociado a un workspace).
     */
    protected function buildPlanInfo($user): ?array
    {
        if ($user->hasRole('super')) return null;
        if (!$user->tenant_id) return null;
        $tenant = $user->tenant()->with('activeSubscription')->first();
        if (!$tenant) return null;
        $plan = \App\Models\Plan::findBySlug($tenant->currentPlan());
        if (!$plan) return null;

        $sub = $tenant->activeSubscription;
        return [
            'slug'           => $plan->slug,
            'name'           => $plan->name,
            'icon'           => $plan->icon,
            'color'          => $plan->color,
            'tagline'        => $plan->tagline,
            'days_remaining' => $sub?->daysRemaining(),
            'ends_at'        => $sub?->ends_at?->toIso8601String(),
            'is_trial'       => $sub?->isTrial() ?? false,
        ];
    }

    /**
     * Estado de sub para el banner global. Devuelve null si no aplica banner.
     * El warning se dispara solo si: días_restantes <= 7 OR está en trial.
     */
    protected function buildSubscriptionStatus($user): ?array
    {
        if (!$user || !$user->tenant_id) return null;
        if ($user->hasRole('super')) return null;  // super no ve banner

        $sub = \App\Models\Subscription::where('tenant_id', $user->tenant_id)
            ->current()
            ->orderByDesc('ends_at')
            ->first();

        if (!$sub) return null;

        $days = $sub->daysRemaining();
        $isTrial = $sub->isTrial();
        $showBanner = $isTrial || $days <= 7;
        if (!$showBanner) return null;

        return [
            'plan'           => $sub->plan,
            'status'         => $sub->status,
            'is_trial'       => $isTrial,
            'days_remaining' => $days,
            'ends_at'        => $sub->ends_at?->toIso8601String(),
        ];
    }

    /**
     * Carga los namespaces de traducción que el frontend Vue necesita.
     *
     * Mantenemos esta lista corta a propósito — `auth.php`, `passwords.php`,
     * etc. solo se usan desde Blade y no hace falta mandarlos al cliente.
     */
    /**
     * Construye la lista de idiomas que aparece en el dropdown del navbar.
     * Cacheo en memory por request — se llama muchas veces si Inertia hace
     * navegación parcial.
     */
    protected function buildAvailableLocales(): array
    {
        static $cached = null;
        if ($cached !== null) return $cached;

        // Locales que el router URL acepta (es, en, pt, etc.).
        $supported = array_keys(config('laravellocalization.supportedLocales', []));
        if (empty($supported)) return $cached = [];

        // Languages activos en el módulo del super.
        $activeIsos = \App\Models\Language::query()
            ->where('is_active', true)
            ->whereIn('iso_code', $supported)
            ->orderBy('name')
            ->get(['iso_code', 'name'])
            ->map(fn ($l) => ['code' => $l->iso_code, 'label' => $l->name])
            ->all();

        return $cached = $activeIsos;
    }

    protected function loadTranslations(): array
    {
        // Auto-discover: leemos todos los archivos .php del directorio del
        // locale activo (más fallback) — así módulos nuevos generados por
        // scaffold tienen sus traducciones disponibles sin tocar este código.
        $locale         = app()->getLocale();
        $fallbackLocale = config('app.fallback_locale', 'en');

        $namespaces = [];
        foreach (array_unique([$locale, $fallbackLocale]) as $loc) {
            $dir = resource_path("lang/{$loc}");
            if (!is_dir($dir)) continue;
            foreach (glob($dir . '/*.php') as $file) {
                $namespaces[] = basename($file, '.php');
            }
        }
        $namespaces = array_values(array_unique($namespaces));

        $out = [];
        foreach ($namespaces as $ns) {
            $messages = trans($ns);
            // trans() devuelve el key string si no encuentra el archivo;
            // descartamos esos casos.
            if (is_array($messages)) {
                $out[$ns] = $messages;
            }
        }
        return $out;
    }

    /**
     * Recientes del usuario (últimos 10 vistos, cualquier módulo).
     *
     * Devuelve cada item con label + URL para el dropdown del avatar.
     * Mapeamos el morph type al route name correspondiente. Si un módulo
     * no tiene ruta show definida (o el slug ya no existe), lo skipeamos.
     */
    protected function buildRecentViewsPayload(int $userId): array
    {
        $rows = \App\Models\UserRecentView::where('user_id', $userId)
            ->orderByDesc('viewed_at')
            ->limit(10)
            ->get();

        // Agrupamos por type para hacer 1 query por módulo (evita N+1).
        // No todos los modelos polimorficos tienen `slug` (ej. Automation usa
        // id como route key) ni `name` (ej. Quote usa `reference`). Detectamos
        // routeKey y la columna de display real antes de seleccionar columnas
        // — sin esto la query peta con "column does not exist". Envolvemos en
        // try/catch por type para que un módulo roto no tumbe todo el shell.
        $grouped = $rows->groupBy('viewable_type');
        $resolved = [];
        foreach ($grouped as $type => $items) {
            if (!class_exists($type)) continue;
            $ids = $items->pluck('viewable_id')->all();

            try {
                $proto    = new $type;
                $routeKey = $proto->getRouteKeyName();
                $labelCol = $this->displayColumnFor($proto);
                $columns  = array_values(array_unique(array_filter(['id', $labelCol, $routeKey])));

                $models = $type::query()->whereIn('id', $ids)->get($columns)->keyBy('id');
            } catch (\Throwable $e) {
                Log::warning("RecentViews: skipped {$type} ({$e->getMessage()})");
                continue;
            }

            foreach ($items as $item) {
                $m = $models->get($item->viewable_id);
                if (!$m) continue;
                $resolved[] = [
                    'id'         => $m->id,
                    'name'       => (string) ($m->{$labelCol} ?? "#{$m->id}"),
                    'module'     => $this->moduleSlugFor($type),
                    'url'        => $this->showUrlFor($type, $m->{$routeKey}),
                    'viewed_at'  => $item->viewed_at?->toIso8601String(),
                ];
            }
        }

        // Re-ordenar por viewed_at desc, limit 10 (ya venía ordenado pero
        // groupBy puede mezclar el orden).
        usort($resolved, fn ($a, $b) => strcmp($b['viewed_at'] ?? '', $a['viewed_at'] ?? ''));
        return array_slice($resolved, 0, 10);
    }

    /**
     * Detecta la mejor columna de la tabla para usar como label en Recientes.
     * Prioriza name, title, reference, subject, display_name; si ninguna existe
     * cae al routeKey (normalmente slug). Cacheado en memoria por request.
     */
    protected function displayColumnFor(\Illuminate\Database\Eloquent\Model $model): string
    {
        static $cache = [];
        $table = $model->getTable();
        if (isset($cache[$table])) return $cache[$table];

        try {
            $cols = Schema::getColumnListing($table);
        } catch (\Throwable $e) {
            return $cache[$table] = $model->getRouteKeyName();
        }
        foreach (['name', 'title', 'reference', 'subject', 'display_name'] as $candidate) {
            if (in_array($candidate, $cols, true)) {
                return $cache[$table] = $candidate;
            }
        }
        return $cache[$table] = $model->getRouteKeyName();
    }

    /** FQCN → slug del módulo. Lee del allowlist único en config/polymorphic.php. */
    protected function moduleSlugFor(string $type): string
    {
        foreach (config('polymorphic.modules', []) as $slug => $cfg) {
            if (($cfg['model'] ?? null) === $type) return $slug;
        }
        return class_basename($type);
    }

    /** FQCN → URL del show del módulo, o null si no aplica. */
    protected function showUrlFor(string $type, $slugOrId): ?string
    {
        $slug = $this->moduleSlugFor($type);
        $routeName = config("polymorphic.modules.{$slug}.show_route");
        if (!$routeName) return null;
        try {
            return route($routeName, $slugOrId);
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * Inbox payload (bell): las 10 notificaciones más recientes + contadores
     * para el badge (unread) y el polling automático (processing).
     *
     * Hoy solo "downloads" (archivos exportados listos), pero la shape ya está
     * pensada como bandeja unificada — cada item lleva un `kind` que permite
     * renderizar diferente según el tipo (download/task/alert).
     */
    protected function buildInboxPayload(int $userId): array
    {
        $toIso = function ($value): ?string {
            if ($value === null) return null;
            if ($value instanceof \Carbon\CarbonInterface) return $value->toIso8601String();
            try {
                return \Carbon\Carbon::parse($value)->toIso8601String();
            } catch (\Throwable $e) {
                return null;
            }
        };

        // ── Downloads (exports listos) ───────────────────────────────────
        $downloads = Download::where('user_id', $userId)
            ->where('expires_at', '>=', now())
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'slug', 'type', 'filename', 'status', 'created_at', 'downloaded_at', 'error_message'])
            ->map(fn ($d) => [
                'id'            => "dl-{$d->id}",
                'slug'          => $d->slug,
                'kind'          => 'download',
                'type'          => $d->type,
                'filename'      => $d->filename,
                'status'        => $d->status,
                'created_at'    => $toIso($d->created_at),
                'downloaded_at' => $toIso($d->downloaded_at),
                'error_message' => $d->error_message,
            ])
            ->all();

        // ── App notifications (tabla `notifications` estándar de Laravel) ──
        // El channel `database` guarda acá cualquier $user->notify(...).
        // Incluye las de Automations (InAppNotificationAction → AutomationTriggered)
        // y cualquier otra futura sin tocar este código.
        $appNotifs = \DB::table('notifications')
            ->where('notifiable_type', \App\Models\User::class)
            ->where('notifiable_id', $userId)
            ->orderByDesc('created_at')
            ->limit(10)
            ->get(['id', 'type', 'data', 'read_at', 'created_at'])
            ->map(function ($n) use ($toIso) {
                $data = json_decode($n->data, true) ?? [];
                return [
                    'id'          => "app-{$n->id}",
                    'raw_id'      => $n->id, // necesario para mark-as-read
                    'kind'        => 'app',
                    'type'        => $data['category'] ?? class_basename($n->type),
                    'title'       => $data['title'] ?? '',
                    'body'        => $data['body']  ?? '',
                    // tenant_name solo se setea en notifs de automation cuando
                    // el frontend lo usa como badge (visible al super que ve
                    // notifs cross-tenant).
                    'tenant_name' => $data['tenant_name'] ?? null,
                    'channel'     => $data['channel'] ?? null, // 'in_app' | 'email' (solo automations)
                    'status'      => $n->read_at ? 'read' : 'unread',
                    'created_at'  => $toIso($n->created_at),
                    'read_at'     => $toIso($n->read_at),
                ];
            })
            ->all();

        // ── Merge + sort por fecha ────────────────────────────────────────
        $recent = collect($downloads)->concat($appNotifs)
            ->sortByDesc('created_at')
            ->take(10)
            ->values()
            ->all();

        // Contadores: unread cubre los 2 kinds.
        $unread = collect($recent)->filter(function ($n) {
            if ($n['kind'] === 'download') {
                return $n['status'] === 'ready' && empty($n['downloaded_at']);
            }
            if ($n['kind'] === 'app') {
                return $n['status'] === 'unread';
            }
            return false;
        })->count();

        $processing = collect($recent)
            ->filter(fn ($n) => $n['kind'] === 'download' && $n['status'] === 'processing')
            ->count();

        // Mensajes no leidos del modulo Messages + Inbox (canal super -> users).
        // Pasamos $userId directamente al service — antes hacíamos un
        // User::find() extra por cada poll del bell. MessageService acepta
        // int|User. Try/catch porque la tabla messages puede no existir aun
        // en entornos pre-migration.
        $unreadMessages = 0;
        $messagesPreview = [];
        try {
            $service = app(\App\Services\Communication\MessageService::class);
            $unreadMessages = $service->unreadCountForUser($userId);

            // Top 5 mensajes recientes (leidos o no) para el dropdown del bell.
            // Los mostramos en una seccion separada del recent[] (downloads + app
            // notifs) para que el user los pueda diferenciar visualmente.
            $messagesPreview = $service->inboxFor($userId)
                ->orderByDesc('messages.published_at')
                ->limit(5)
                ->get(['messages.id', 'messages.slug', 'messages.subject',
                       'messages.published_at', 'message_recipients.read_at'])
                ->map(fn ($m) => [
                    'id'         => "msg-{$m->id}",
                    'slug'       => $m->slug,
                    'kind'       => 'message',
                    'subject'    => $m->subject,
                    'status'     => $m->read_at ? 'read' : 'unread',
                    'created_at' => $toIso($m->published_at),
                    'read_at'    => $toIso($m->read_at),
                ])
                ->all();
        } catch (\Throwable $e) {
            $unreadMessages = 0;
            $messagesPreview = [];
        }

        return [
            'recent'          => $recent,
            'unread'          => $unread,
            'processing'      => $processing,
            'unread_messages' => $unreadMessages,
            // Preview de mensajes para mostrar como seccion separada en el
            // dropdown del bell — bajo volumen (max 5), no se mezcla con `recent`.
            'messages'        => $messagesPreview,
        ];
    }
}
