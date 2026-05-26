<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Http\Requests\Crm\Contact\BulkDeleteContactRequest;
use App\Http\Requests\Crm\Contact\BulkRestoreContactRequest;
use App\Http\Requests\Crm\Contact\BulkSetActiveContactRequest;
use App\Http\Requests\Crm\Contact\DeleteContactRequest;
use App\Http\Requests\Crm\Contact\EditAllUpdateContactRequest;
use App\Http\Requests\Crm\Contact\ForceDeleteContactRequest;
use App\Http\Requests\Crm\Contact\ImportContactRequest;
use App\Http\Requests\Crm\Contact\StoreContactRequest;
use App\Http\Requests\Crm\Contact\UpdateContactRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\Crm\Contacts\GenerateContactsCsvJob;
use App\Jobs\Crm\Contacts\GenerateContactsExcelJob;
use App\Jobs\Crm\Contacts\GenerateContactsPdfJob;
use App\Jobs\Crm\Contacts\GenerateContactsWordJob;
use App\Models\AuditLog;
use App\Models\Contact;
use App\Services\Crm\ContactService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'desc']);
        }

        $userId  = $request->user()?->id;
        $isSuper = $request->user()?->hasRole('super') ?? false;

        // Solo super necesita el tenant eager-loaded — admins ven solo los suyos
        // y la columna workspace queda oculta en el frontend.
        $with = [
            'creator:id,name,email',
            // Enriquece el listado: empresa (con link), owner.
            'company:id,name,slug',
            'owner:id,name,email',
        ];
        if ($isSuper) {
            $with[] = 'tenant:id,name';
        }

        $contacts = Contact::query()
            ->select('contacts.*')
            ->with($with)
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = Contact::count();

        $names = $request->get('name', []);
        if (is_string($names)) $names = $names === '' ? [] : [$names];

        return inertia('Contacts/Index', [
            'contacts' => array_merge($contacts->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            // Limites de export por formato — el frontend deshabilita formatos
            // que exceden su limite. CSV con 0 = sin limite (streaming).
            'exportLimits' => \App\Models\Setting::getExportLimits('contacts'),
            'filters' => [
                'name'         => array_values($names),
'is_active'    => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'created_from' => $request->get('created_from', ''),
                'created_to'   => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'         => $request->get('sort', 'id'),
                'direction'    => $request->get('direction', 'desc'),
                'per_page'     => $perPage,
                // Filtros avanzados: array de clausulas {field, op, value}
                // que el drawer construye. Lo persisto para que al recargar
                // la pagina (paginate, sort) el filtro siga aplicado.
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'isSuper'        => $isSuper,
            // Schema de campos filtrables — alimenta el drawer "Filtros
            // avanzados" del frontend (selects de field/op + control tipado
            // del valor). Cada modulo declara el suyo en su modelo.
            'filterSchema'   => Contact::filterSchema(),
        ]);
    }

    /**
     * Normaliza `advanced_where` del request: viene como JSON string o
     * array directo segun como Inertia lo serialice. Filtra clausulas
     * vacias o incompletas antes de pasarlo al frontend.
     */
    protected function parseAdvancedWhere(\Illuminate\Http\Request $request): array
    {
        $raw = $request->input('advanced_where', []);
        if (is_string($raw)) {
            $raw = json_decode($raw, true) ?: [];
        }
        if (!is_array($raw)) return [];

        return array_values(array_filter($raw, fn ($c) =>
            is_array($c) && !empty($c['field']) && !empty($c['op'])
        ));
    }

    public function show(Request $request, Contact $contact)
    {
        $contact->load([
            'creator:id,name,email',
            'deleter:id,name,email',
            'tags:id,name,color',
            'company:id,name,slug',
            'owner:id,name,email',
            'reportsTo:id,name,slug,first_name,last_name',
            'preferredLanguage:id,name,code',
        ]);

        $canSeeAudit = $request->user()?->hasAnyRole(['super', 'admin']) ?? false;
        $activity = $canSeeAudit
            ? AuditLogResource::collection(
                AuditLog::query()
                    ->where('auditable_type', Contact::class)
                    ->where('auditable_id', $contact->id)
                    ->with('user:id,name,email')
                    ->orderByDesc('created_at')
                    ->limit(20)
                    ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
            )->resolve()
            : [];

        $activities = $request->user()?->can('activities.view')
            ? \App\Models\Activity::query()
                ->where('activitable_type', Contact::class)
                ->where('activitable_id', $contact->id)
                ->with('actor:id,name,email')
                ->orderByDesc('created_at')
                ->limit(100)
                ->get()
                ->map(fn ($a) => $a->toPanelArray())
                ->all()
            : [];

        // Tab Deals: oportunidades donde este contacto es el primario. (Pivot
        // deal_contacts queda fuera del scope inicial — si el contacto aparece
        // como secundario en otros deals, no se ve aqui. Se puede agregar
        // despues con whereHas.)
        $deals = $request->user()?->can('deals.view')
            ? \App\Models\Deal::query()
                ->where('contact_id', $contact->id)
                ->orderByDesc('id')
                ->limit(100)
                ->get(['id', 'slug', 'name', 'status', 'value', 'currency_code', 'expected_close_date'])
                ->map(fn ($d) => [
                    'id'       => $d->id,
                    'slug'     => $d->slug,
                    'name'     => $d->name,
                    'status'   => $d->status,
                    'value'    => $d->value,
                    'currency' => $d->currency_code,
                    'expected_close_date' => $d->expected_close_date?->toDateString(),
                ])
                ->all()
            : [];

        return inertia('Contacts/Show', [
            'contact'    => $this->payload($contact, withAudit: true),
            'activity'   => $activity,
            'activities' => $activities,
            'deals'      => $deals,
            'canManageActivities' => $request->user()?->can('activities.create') ?? false,
        ]);
    }

    public function create()
    {
        return inertia('Contacts/Form', array_merge(
            ['contact' => null],
            $this->formSelectOptions()
        ));
    }

    /** Opciones para los Select del Form. */
    protected function formSelectOptions(): array
    {
        $u = auth()->user();

        return [
            'companyOptions' => \App\Models\Company::query()
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
                ->all(),

            'contactOptions' => \App\Models\Contact::query()
                ->orderBy('name')->get(['id', 'name'])
                ->map(fn ($c) => ['value' => $c->id, 'label' => $c->name])
                ->all(),

            'ownerOptions' => (function () use ($u) {
                $q = \App\Models\User::query();
                if (!$u || !$u->hasRole('super')) {
                    $q->when($u?->tenant_id, fn ($qq, $tid) => $qq->where('tenant_id', $tid));
                    if ($u && !$u->hasAnyRole(['super', 'admin'])) {
                        $q->where('id', $u->id);
                    }
                }
                return $q->orderBy('name')->get(['id', 'name', 'email'])
                    ->map(fn ($x) => ['value' => $x->id, 'label' => $x->name . ' (' . $x->email . ')'])
                    ->all();
            })(),

            'languageOptions' => \App\Models\Language::query()
                ->where('is_active', true)->orderBy('name')
                ->get(['id', 'name', 'iso_code'])
                ->map(fn ($l) => ['value' => $l->id, 'label' => $l->name . ' (' . $l->iso_code . ')'])
                ->all(),

            'stageOptions' => collect(\App\Models\Contact::LIFECYCLE_STAGES)
                ->map(fn ($s) => ['value' => $s, 'label' => __('contacts.lifecycle_stage_options.' . $s)])
                ->all(),

            'ratingOptions' => collect(\App\Models\Contact::RATINGS)
                ->map(fn ($r) => ['value' => $r, 'label' => __('contacts.rating_options.' . $r)])
                ->all(),

            'salutationOptions' => collect(\App\Models\Contact::SALUTATIONS)
                ->map(fn ($s) => ['value' => $s, 'label' => $s])
                ->all(),

            'genderOptions' => collect(\App\Models\Contact::GENDERS)
                ->map(fn ($g) => ['value' => $g, 'label' => __('contacts.gender_options.' . $g)])
                ->all(),

            'seniorityOptions' => collect(\App\Models\Contact::SENIORITY_LEVELS)
                ->map(fn ($s) => ['value' => $s, 'label' => __('contacts.seniority_level_options.' . $s)])
                ->all(),

            'decisionRoleOptions' => collect(\App\Models\Contact::DECISION_ROLES)
                ->map(fn ($r) => ['value' => $r, 'label' => __('contacts.decision_role_options.' . $r)])
                ->all(),

            'channelOptions' => collect(\App\Models\Contact::PREFERRED_CHANNELS)
                ->map(fn ($c) => ['value' => $c, 'label' => __('contacts.preferred_channel_options.' . $c)])
                ->all(),

            'strengthOptions' => collect(\App\Models\Contact::RELATIONSHIP_STRENGTHS)
                ->map(fn ($s) => ['value' => $s, 'label' => __('contacts.strength_options.' . $s)])
                ->all(),
        ];
    }

    public function store(StoreContactRequest $request, ContactService $service): RedirectResponse
    {
        // Limite de registros por modulo segun el plan del tenant.
        // super no tiene tenant → no aplica. -1 = ilimitado.
        $tenant = $request->user()?->tenant;
        if ($tenant) {
            $max = $tenant->maxRecordsPerModule();
            if ($max > 0 && Contact::count() >= $max) {
                return back()->with('error', __('plans.limit_records_reached', ['max' => $max]));
            }
        }

        $service->create($request->validated());

        return redirect()
            ->route('crm.contacts.index')
            ->with('success', __('contacts.created'));
    }

    public function edit(Contact $contact)
    {
        return inertia('Contacts/Form', array_merge(
            ['contact' => $this->payload($contact)],
            $this->formSelectOptions()
        ));
    }

public function update(UpdateContactRequest $request, Contact $contact, ContactService $service): RedirectResponse
    {
        $service->update($contact, $request->validated());

        return redirect()
            ->route('crm.contacts.index')
            ->with('success', __('contacts.saved'));
    }

    public function delete(Contact $contact)
    {
        return inertia('Contacts/Delete', [
            'contact' => $this->payload($contact),
        ]);
    }

    public function deleteSave(DeleteContactRequest $request, Contact $contact, ContactService $service): RedirectResponse
    {
        $service->delete($contact, $request->validated()['deleted_description']);

        $this->storeUndoableDelete([$contact->id]);

        return redirect()
            ->route('crm.contacts.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$contact->id]));
    }

    /** Persiste el claim en sesion por el window de undo (60s). */
    protected function storeUndoableDelete(array $ids): void
    {
        session(['contacts.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds(60)->toIso8601String(),
        ]]);
    }

    /** Payload que va al frontend via flash para disparar el toast. */
    protected function buildRecentDeletePayload(array $ids): array
    {
        return [
            'count'   => count($ids),
            'seconds' => 60,
        ];
    }

    public function trash(Request $request)
    {
        abort_unless($request->user()?->hasRole('super'), 403);

        $name    = $request->get('name', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $contacts = Contact::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($name !== '', fn ($q) => $q->where('name', 'like', "%{$name}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Contacts/Trash', [
            'contacts' => $contacts,
            'filters'   => [
                'name'     => $name,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, $slug, ContactService $service): RedirectResponse
    {
        abort_unless($request->user()?->hasRole('super'), 403);
        $model = Contact::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $service->restore($model);

        return redirect()
            ->route('crm.contacts.trash')
            ->with('success', __('global.restored_success'));
    }

    /**
     * Edit All — pagina con tabla editable in-line de name + is_active.
     * El submit hace batch update en transaccion (editAllUpdate).
     */
    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $contacts = Contact::query()
            ->filter($request)
            ->select('contacts.id', 'contacts.slug', 'contacts.name',
                'contacts.is_active')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Contacts/EditAll', [
            'contacts' => $contacts,
            'filters'   => [
                'name'      => $request->get('name', ''),
                'is_active' => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function editAllUpdate(EditAllUpdateContactRequest $request, ContactService $service): RedirectResponse
    {
        $touched = $service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('crm.contacts.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    /**
     * Clona el contact. Sufijo "(copia)" con sanity guard de 100 intentos.
     */
    public function duplicate(Request $request, Contact $contact, ContactService $service): RedirectResponse
    {
        $clone = $service->duplicate($contact);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('crm.contacts.index')
            ->with('success', __('global.duplicated_success'));
    }

    public function bulkRestore(BulkRestoreContactRequest $request, ContactService $service): RedirectResponse
    {
        $result = $service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('crm.contacts.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('crm.contacts.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function forceDelete(ForceDeleteContactRequest $request, $slug, ContactService $service): RedirectResponse
    {
        $model = Contact::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data  = $request->validated();

        if (trim($data['name_confirmation']) !== $model->name) {
            return back()->withErrors(['name_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $service->forceDelete($model, $data['reason']);

        return redirect()
            ->route('crm.contacts.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    protected function payload(Contact $m, bool $withAudit = false): array
    {
        $base = [
            'id'           => $m->id,
            'slug'         => $m->slug,
            'tags'         => $m->relationLoaded('tags')
                ? $m->tags->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'color' => $t->color])->all()
                : [],
            'name'         => $m->name,
            'first_name'   => $m->first_name,
            'last_name'    => $m->last_name,
            'middle_name'  => $m->middle_name,
            'salutation'   => $m->salutation,
            'job_title'    => $m->job_title,
            'department'   => $m->department,
            'description'  => $m->description,

            'primary_email' => $m->primary_email,
            'primary_phone' => $m->primary_phone,
            'mobile_phone'  => $m->mobile_phone,

            'company_id'             => $m->company_id,
            'reports_to_contact_id'  => $m->reports_to_contact_id,
            'is_primary_for_company' => (bool) $m->is_primary_for_company,

            'lifecycle_stage' => $m->lifecycle_stage,
            'lead_source'     => $m->lead_source,
            'rating'          => $m->rating,
            'score'           => $m->score,

            'owner_id'              => $m->owner_id,
            'preferred_language_id' => $m->preferred_language_id,
            'timezone'              => $m->timezone,

            'email_opt_in'    => (bool) $m->email_opt_in,
            'sms_opt_in'      => (bool) $m->sms_opt_in,
            'whatsapp_opt_in' => (bool) $m->whatsapp_opt_in,
            'do_not_contact'  => (bool) $m->do_not_contact,
            'gdpr_consent_at' => $m->gdpr_consent_at,

            'date_of_birth' => $m->date_of_birth,
            'gender'        => $m->gender,

            'linkedin_url'   => $m->linkedin_url,
            'twitter_handle' => $m->twitter_handle,
            'photo_url'      => $m->photo_url,
            'external_id'    => $m->external_id,

            // Sales qualification
            'nickname'          => $m->nickname,
            'seniority_level'   => $m->seniority_level,
            'decision_role'     => $m->decision_role,
            'is_decision_maker' => (bool) $m->is_decision_maker,
            'preferred_channel' => $m->preferred_channel,

            // Assistant + Marketing
            'assistant_name'           => $m->assistant_name,
            'assistant_email'          => $m->assistant_email,
            'assistant_phone'          => $m->assistant_phone,
            'marketing_opt_in_at'      => $m->marketing_opt_in_at,
            'marketing_opt_in_source'  => $m->marketing_opt_in_source,
            'unsubscribed_at'          => $m->unsubscribed_at,
            'unsubscribed_reason'      => $m->unsubscribed_reason,
            'relationship_strength'    => $m->relationship_strength,
            'last_engagement_at'       => $m->last_engagement_at,

            'is_active'   => $m->is_active,
            'is_favorite' => (bool) ($m->is_favorite ?? false),
            'created_at'  => $m->created_at,
            'updated_at'  => $m->updated_at,
            'deleted_at'  => $m->deleted_at,
        ];

        $rel = fn ($name, array $cols = ['id', 'name']) => $m->relationLoaded($name) && $m->$name
            ? collect($cols)->mapWithKeys(fn ($c) => [$c => $m->$name->$c])->all()
            : null;
        $base['company']            = $rel('company', ['id', 'name', 'slug']);
        $base['owner']              = $rel('owner', ['id', 'name', 'email']);
        $base['reports_to']         = $rel('reportsTo', ['id', 'name', 'slug']);
        $base['preferred_language'] = $rel('preferredLanguage', ['id', 'name', 'code']);

        if ($withAudit) {
            $base['deleted_description'] = $m->deleted_description;
            $base['creator'] = $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name, 'email' => $m->creator->email] : null;
            $base['deleter'] = $m->deleter ? ['id' => $m->deleter->id, 'name' => $m->deleter->name, 'email' => $m->deleter->email] : null;
        }
        return $base;
    }

    // ── EXPORTS ─────────────────────────────────────────────────────────
    // Los 4 formatos van a queue como jobs async (mismo patron que Regions).
    // El job se encarga de la query con scope + render + Download record.

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateContactsCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateContactsExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateContactsPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateContactsWordJob::class);
    }

    /**
     * Helper comun de los 4 export endpoints: parse options → limit check →
     * audit → dispatch. Mismo patron que Region.
     */
    protected function dispatchExport(Request $request, string $format, string $jobClass): RedirectResponse
    {
        $options = $this->buildExportOptions($request, $format);
        $this->assertExportLimit($format, $options);
        $this->recordExportAudit($format, $options);
        $jobClass::dispatch(auth()->id(), $options);

        return back()->with('success', __('global.download_in_queue'));
    }

    /**
     * Valida que el dataset no exceda el limite del formato. Usuarios con
     * plan premium (feature flag `export_unlimited_rows`) saltean el limite.
     */
    protected function assertExportLimit(string $format, array $options): void
    {
        if (\App\Support\FeatureGate::allows('export_unlimited_rows', auth()->user())
            && config('features.features.export_unlimited_rows') !== null) {
            return;
        }

        $limit = \App\Models\Setting::getExportLimit('contacts', $format);
        if ($limit === 0) return; // CSV streaming, sin limite

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('contacts.export_limit_exceeded', [
                'count'  => number_format($count),
                'limit'  => number_format($limit),
                'format' => strtoupper($format),
            ]));
        }
    }

    /** Cuenta filas a exportar segun scope+filters. */
    protected function countForExport(array $options): int
    {
        $scope = $options['scope'] ?? 'filtered';

        if ($scope === 'selected') {
            return count($options['selected_ids'] ?? []);
        }
        if ($scope === 'all') {
            return Contact::query()->count();
        }
        // Filters como Request para reusar scopeFilter.
        $fakeReq = new Request($options['filters'] ?? []);
        return Contact::query()->filter($fakeReq)->count();
    }

    // ── IMPORTS (two-phase: dry_run preview + commit) ────────────────────
    // El frontend sube 2 veces: primero dry_run=true (preview con summary),
    // despues dry_run=false (commit).

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Crm\Contacts\ContactsImportTemplate(),
            __('contacts.import_template_filename')
        );
    }

    public function import(ImportContactRequest $request)
    {
        $data    = $request->validated();
        $mode    = $data['mode'] ?? 'update_or_create';
        $dryRun  = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\Crm\Contacts\ContactsImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('ContactsImport failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return response()->json([
                'ok'      => false,
                'dry_run' => $dryRun,
                'message' => $this->humanizeImportError($e),
            ], 422);
        }

        return response()->json([
            'ok'      => true,
            'dry_run' => $dryRun,
            'summary' => $importer->summary(),
        ], 200);
    }

    /**
     * Convierte una excepcion de import en mensaje legible para el usuario.
     * El detalle tecnico queda en el log, no llega al cliente.
     */
    protected function humanizeImportError(\Throwable $e): string
    {
        $msg = $e->getMessage();

        if ($e instanceof \Illuminate\Database\QueryException) {
            if (str_contains($msg, 'unique') || str_contains($msg, 'duplicate')) {
                return __('imports.err_unique_violation');
            }
            if (str_contains($msg, 'NOT NULL') || str_contains($msg, 'null value')) {
                return __('imports.err_not_null_violation');
            }
            if (str_contains($msg, 'foreign key') || str_contains($msg, 'violates foreign')) {
                return __('imports.err_foreign_key_violation');
            }
        }

        return __('imports.process_failed');
    }

    // ── BULK OPERATIONS ─────────────────────────────────────────────────
    public function bulkDelete(BulkDeleteContactRequest $request, ContactService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkDelete($data['ids'], $data['deleted_description']);

        if (!empty($result['queued'])) {
            // Async: el delete real ocurre despues del redirect; el undo
            // window de 60s no calza con un job que tarda minutos.
            return back()
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        $deletedIds = $result['deleted'];
        $this->storeUndoableDelete($deletedIds);

        return back()
            ->with('success', __('global.deleted_success') . ' (' . count($deletedIds) . ')')
            ->with('recentDelete', $this->buildRecentDeletePayload($deletedIds));
    }

    /**
     * Undo dentro del window de 60s. Validamos contra session claim:
     * quien borro puede deshacer su propio error sin permisos extra.
     * Defense in depth: el service solo restaura las filas con
     * deleted_by = current user.
     */
    public function undoLastDelete(Request $request, ContactService $service): RedirectResponse
    {
        $claim = session('contacts.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('contacts.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('contacts.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('contacts.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    public function bulkSetActive(BulkSetActiveContactRequest $request, ContactService $service): RedirectResponse
    {
        $data   = $request->validated();
        $result = $service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    // ── Export helpers ──────────────────────────────────────────────────

    /**
     * Opciones normalizadas que reciben todos los jobs de export. Allowlist
     * de columnas previene inyeccion de campos sensibles.
     */
    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = ['id', 'name',
            'is_active', 'slug', 'created_at', 'updated_at', 'creator'];

        $rules = [
            'scope'                   => 'nullable|in:filtered,selected,all',
            'selected_ids'            => 'array',
            'selected_ids.*'          => 'integer',
            'columns'                 => 'array|min:1',
            'columns.*'               => 'in:' . implode(',', $allowedColumns),
            'title'                   => 'nullable|string|max:120',
            'include_filters_summary' => 'boolean',
            'filters'                 => 'array',
        ];
        if ($format === 'pdf') {
            $rules['orientation'] = 'nullable|in:portrait,landscape';
            $rules['paper_size']  = 'nullable|in:a4,letter';
        }
        if ($format === 'excel') {
            $rules['autofilter']    = 'boolean';
            $rules['freeze_header'] = 'boolean';
        }

        $data = $request->validate($rules);

        return [
            'scope'                   => $data['scope']                   ?? 'filtered',
            'selected_ids'            => $data['selected_ids']            ?? [],
            'columns'                 => $data['columns']                 ?? $allowedColumns,
            'title'                   => $data['title']                   ?? __('contacts.export_title'),
            'include_filters_summary' => $data['include_filters_summary'] ?? true,
            'filters'                 => $data['filters']                 ?? [],
            'orientation'             => $data['orientation']             ?? 'portrait',
            'paper_size'              => $data['paper_size']              ?? 'a4',
            'autofilter'              => $data['autofilter']              ?? true,
            'freeze_header'           => $data['freeze_header']           ?? true,
        ];
    }

    /**
     * Escribe audit log manual del export. Event = 'export_queued' registra
     * la INTENCION del usuario; el estado final (ready/failed) vive en `downloads`.
     */
    protected function recordExportAudit(string $format, array $options): void
    {
        AuditLog::create([
            'user_id'        => auth()->id(),
            'event'          => 'export_queued',
            'auditable_type' => Contact::class,
            'auditable_id'   => null,
            'module'         => 'contacts',
            'old_values'     => null,
            'new_values'     => [
                'format'                  => $format,
                'scope'                   => $options['scope']        ?? 'filtered',
                'columns'                 => $options['columns']      ?? [],
                'title'                   => $options['title']        ?? null,
                'orientation'             => $format === 'pdf'   ? ($options['orientation']    ?? null) : null,
                'paper_size'              => $format === 'pdf'   ? ($options['paper_size']     ?? null) : null,
                'autofilter'              => $format === 'excel' ? ($options['autofilter']     ?? null) : null,
                'freeze_header'           => $format === 'excel' ? ($options['freeze_header']  ?? null) : null,
                'include_filters_summary' => $options['include_filters_summary'] ?? false,
                'filters'                 => $options['filters']      ?? [],
                'selected_ids_count'      => count($options['selected_ids'] ?? []),
            ],
            'url'        => route('crm.contacts.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }
}
