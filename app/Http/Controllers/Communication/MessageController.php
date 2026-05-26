<?php

namespace App\Http\Controllers\Communication;

use App\Http\Controllers\Controller;
use App\Http\Requests\Communication\Message\BulkDeleteMessageRequest;
use App\Http\Requests\Communication\Message\BulkRestoreMessageRequest;
use App\Http\Requests\Communication\Message\BulkSetActiveMessageRequest;
use App\Http\Requests\Communication\Message\DeleteMessageRequest;
use App\Http\Requests\Communication\Message\EditAllUpdateMessageRequest;
use App\Http\Requests\Communication\Message\ForceDeleteMessageRequest;
use App\Http\Requests\Communication\Message\ImportMessageRequest;
use App\Http\Requests\Communication\Message\StoreMessageRequest;
use App\Http\Requests\Communication\Message\UpdateMessageRequest;
use App\Http\Resources\AuditLogResource;
use App\Jobs\Communication\Messages\GenerateMessagesCsvJob;
use App\Jobs\Communication\Messages\GenerateMessagesExcelJob;
use App\Jobs\Communication\Messages\GenerateMessagesPdfJob;
use App\Jobs\Communication\Messages\GenerateMessagesWordJob;
use App\Models\AuditLog;
use App\Models\Message;
use App\Models\MessageRecipient;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Communication\MessageService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Permisos: todas las rutas viven dentro del middleware `role:super` en
 * routes/communication.php. No hace falta validar rol por metodo.
 *
 * Tier 1 parity: 24 actions clonadas del patron DiscountController.
 */
class MessageController extends Controller
{
    public function __construct(protected MessageService $service)
    {
    }

    // ─── INDEX + filtros ─────────────────────────────────────────────────────

    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100, 200]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'desc']);
        }

        $userId = $request->user()?->id;

        $messages = Message::query()
            ->select('messages.*')
            ->withCount(['recipients as recipients_count', 'replies as replies_count'])
            ->withCount(['recipients as read_count' => function ($q) {
                $q->whereNotNull('read_at');
            }])
            ->with(['creator:id,name,email,photo,updated_at'])
            ->orderByFavoriteFirst($userId)
            ->filter($request)
            ->paginate($perPage)
            ->withQueryString();

        $totalUnfiltered = Message::count();

        $subjects = $request->get('subject', []);
        if (is_string($subjects)) $subjects = $subjects === '' ? [] : [$subjects];

        return inertia('Messages/Index', [
            'messages' => array_merge($messages->toArray(), [
                'total_unfiltered' => $totalUnfiltered,
            ]),
            'exportLimits'    => \App\Models\Setting::getExportLimits('messages'),
            'filters' => [
                'subject'        => array_values($subjects),
                'audience_type'  => $request->get('audience_type', ''),
                'is_active'      => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'allow_replies'  => $request->has('allow_replies') && $request->allow_replies !== ''
                    ? filter_var($request->allow_replies, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'published_from' => $request->get('published_from', ''),
                'published_to'   => $request->get('published_to', ''),
                'created_from'   => $request->get('created_from', ''),
                'created_to'     => $request->get('created_to', ''),
                'only_favorites' => $request->boolean('only_favorites'),
                'sort'           => $request->get('sort', 'id'),
                'direction'      => $request->get('direction', 'desc'),
                'per_page'       => $perPage,
                'advanced_where' => $this->parseAdvancedWhere($request),
            ],
            'audienceOptions' => $this->audienceOptions(),
            'filterSchema'    => Message::filterSchema(),
        ]);
    }

    protected function parseAdvancedWhere(Request $request): array
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

    // ─── CRUD core ───────────────────────────────────────────────────────────

    public function create()
    {
        return inertia('Messages/Form', [
            'message' => null,
            'tenants' => $this->loadTenants(),
            'users'   => $this->loadUsers(),
        ]);
    }

    public function store(StoreMessageRequest $request): RedirectResponse
    {
        $message = $this->service->create($request->validated());

        return redirect()
            ->route('communication.messages.show', $message->slug)
            ->with('success', __('messages.created_success'));
    }

    public function show(Request $request, string $slug)
    {
        $message = Message::with(['creator:id,name,email,photo,updated_at', 'deleter:id,name,email'])
            ->where('slug', $slug)
            ->firstOrFail();

        $stats = [
            'recipients_count' => MessageRecipient::where('message_id', $message->id)->count(),
            'read_count'       => MessageRecipient::where('message_id', $message->id)->whereNotNull('read_at')->count(),
        ];
        $stats['read_pct'] = $stats['recipients_count'] > 0
            ? round(($stats['read_count'] * 100) / $stats['recipients_count'], 1)
            : 0;

        $replies = $message->replies()
            ->with(['user:id,name,email,photo,updated_at'])
            ->get()
            ->map(fn ($r) => [
                'id'         => $r->id,
                'body'       => $r->body,
                'user'       => $r->user ? [
                    'id'        => $r->user->id,
                    'name'      => $r->user->name,
                    'email'     => $r->user->email,
                    'photo_url' => $r->user->photo_url,
                ] : null,
                'created_at' => $r->created_at?->toIso8601String(),
            ])
            ->all();

        $activity = AuditLogResource::collection(
            AuditLog::query()
                ->where('auditable_type', Message::class)
                ->where('auditable_id', $message->id)
                ->with('user:id,name,email')
                ->orderByDesc('created_at')
                ->limit(20)
                ->get(['id', 'user_id', 'event', 'old_values', 'new_values', 'created_at'])
        )->resolve();

        $audienceName = $this->resolveAudienceName($message);

        return inertia('Messages/Show', [
            'message' => array_merge($this->payload($message, withAudit: true), [
                'audience_name' => $audienceName,
                'body'          => $message->body,
            ]),
            'stats'    => $stats,
            'replies'  => $replies,
            'activity' => $activity,
        ]);
    }

    public function edit(string $slug)
    {
        $message = Message::where('slug', $slug)->firstOrFail();

        return inertia('Messages/Form', [
            'message' => $this->payload($message) + ['body' => $message->body],
            'tenants' => $this->loadTenants(),
            'users'   => $this->loadUsers(),
        ]);
    }

    public function update(UpdateMessageRequest $request, string $slug): RedirectResponse
    {
        $message = Message::where('slug', $slug)->firstOrFail();
        $this->service->update($message, $request->validated());

        return redirect()
            ->route('communication.messages.show', $message->slug)
            ->with('success', __('messages.updated_success'));
    }

    public function delete(string $slug)
    {
        $message = Message::where('slug', $slug)->firstOrFail();
        return inertia('Messages/Delete', [
            'message' => $this->payload($message),
        ]);
    }

    public function deleteSave(DeleteMessageRequest $request, string $slug): RedirectResponse
    {
        $message = Message::where('slug', $slug)->firstOrFail();
        $data = $request->validated();

        if ($data['subject_confirmation'] !== $message->subject) {
            return back()->withErrors(['subject_confirmation' => __('messages.confirm_subject_mismatch')]);
        }

        $this->service->delete($message, $data['deleted_description']);
        $this->storeUndoableDelete([$message->id]);

        return redirect()
            ->route('communication.messages.index')
            ->with('success', __('global.deleted_success'))
            ->with('recentDelete', $this->buildRecentDeletePayload([$message->id]));
    }

    protected function storeUndoableDelete(array $ids): void
    {
        session(['messages.recent_delete' => [
            'ids'        => array_values($ids),
            'expires_at' => now()->addSeconds((int) config('messages.undo_window_seconds', 60))->toIso8601String(),
        ]]);
    }

    protected function buildRecentDeletePayload(array $ids): array
    {
        return [
            'count'   => count($ids),
            'seconds' => (int) config('messages.undo_window_seconds', 60),
        ];
    }

    // ─── Trash + restore + force delete ──────────────────────────────────────

    public function trash(Request $request)
    {
        $subject = $request->get('subject', '');
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        $messages = Message::onlyTrashed()
            ->with('deleter:id,name,email')
            ->when($subject !== '', fn ($q) => $q->where('subject', 'like', "%{$subject}%"))
            ->orderByDesc('deleted_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Messages/Trash', [
            'messages' => $messages,
            'filters'  => [
                'subject'  => $subject,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function restore(Request $request, string $slug): RedirectResponse
    {
        $message = Message::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $this->service->restore($message);

        return redirect()
            ->route('communication.messages.trash')
            ->with('success', __('global.restored_success'));
    }

    public function forceDelete(ForceDeleteMessageRequest $request, string $slug): RedirectResponse
    {
        $message = Message::onlyTrashed()->where('slug', $slug)->firstOrFail();
        $data    = $request->validated();

        if (trim($data['subject_confirmation']) !== $message->subject) {
            return back()->withErrors(['subject_confirmation' => __('global.force_delete_name_mismatch')]);
        }

        $this->service->forceDelete($message, $data['reason']);

        return redirect()
            ->route('communication.messages.trash')
            ->with('success', __('global.force_deleted_success'));
    }

    // ─── Edit-all + duplicate ────────────────────────────────────────────────

    public function editAll(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $perPage = in_array($perPage, [10, 25, 50, 100]) ? $perPage : 25;

        if (!$request->filled('sort')) {
            $request->merge(['sort' => 'id', 'direction' => 'asc']);
        }

        $messages = Message::query()
            ->filter($request)
            ->select('messages.id', 'messages.slug', 'messages.subject', 'messages.is_active', 'messages.audience_type', 'messages.published_at')
            ->paginate($perPage)
            ->withQueryString();

        return inertia('Messages/EditAll', [
            'messages' => $messages,
            'filters'  => [
                'subject'   => $request->get('subject', ''),
                'is_active' => $request->has('is_active') && $request->is_active !== ''
                    ? filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN)
                    : null,
                'sort'      => $request->get('sort', 'id'),
                'direction' => $request->get('direction', 'asc'),
                'per_page'  => $perPage,
            ],
        ]);
    }

    public function editAllUpdate(EditAllUpdateMessageRequest $request): RedirectResponse
    {
        $touched = $this->service->editAllUpdate($request->validated()['changes']);

        return redirect()
            ->route('communication.messages.edit_all')
            ->with('success', __('global.updated_success') . " ({$touched})");
    }

    public function duplicate(Request $request, string $slug): RedirectResponse
    {
        $message = Message::where('slug', $slug)->firstOrFail();
        $clone   = $this->service->duplicate($message);

        if (!$clone) {
            return back()->with('error', __('global.duplicate_failed'));
        }

        return redirect()
            ->route('communication.messages.index')
            ->with('success', __('global.duplicated_success'));
    }

    // ─── Bulk ops ────────────────────────────────────────────────────────────

    public function bulkDelete(BulkDeleteMessageRequest $request): RedirectResponse
    {
        $data   = $request->validated();
        $result = $this->service->bulkDelete($data['ids'], $data['deleted_description']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        $deletedIds = $result['deleted'];
        $this->storeUndoableDelete($deletedIds);

        return back()
            ->with('success', __('global.deleted_success') . ' (' . count($deletedIds) . ')')
            ->with('recentDelete', $this->buildRecentDeletePayload($deletedIds));
    }

    public function bulkRestore(BulkRestoreMessageRequest $request): RedirectResponse
    {
        $result = $this->service->bulkRestore($request->validated()['ids']);

        if (!empty($result['queued'])) {
            return redirect()
                ->route('communication.messages.trash')
                ->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return redirect()
            ->route('communication.messages.trash')
            ->with('success', __('global.restored_success') . " ({$result['restored']})");
    }

    public function bulkSetActive(BulkSetActiveMessageRequest $request): RedirectResponse
    {
        $data   = $request->validated();
        $result = $this->service->bulkSetActive($data['ids'], (bool) $data['is_active']);

        if (!empty($result['queued'])) {
            return back()->with('success', __('global.bulk_in_queue', ['count' => $result['count']]));
        }

        return back()->with('success', __('global.updated_success') . " ({$result['changed']})");
    }

    public function undoLastDelete(Request $request): RedirectResponse
    {
        $claim = session('messages.recent_delete');
        if (!$claim || !is_array($claim) || empty($claim['ids']) || empty($claim['expires_at'])) {
            return back()->with('error', __('global.undo_failed'));
        }
        if (now()->isAfter($claim['expires_at'])) {
            session()->forget('messages.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        $restored = $this->service->undoLastDelete($claim['ids'], (int) auth()->id());

        if (empty($restored)) {
            session()->forget('messages.recent_delete');
            return back()->with('error', __('global.undo_failed'));
        }

        session()->forget('messages.recent_delete');

        return back()->with('success', __('global.undo_done'));
    }

    // ─── Exports ─────────────────────────────────────────────────────────────

    public function exportCsv(Request $request)
    {
        return $this->dispatchExport($request, 'csv', GenerateMessagesCsvJob::class);
    }

    public function exportExcel(Request $request)
    {
        return $this->dispatchExport($request, 'excel', GenerateMessagesExcelJob::class);
    }

    public function exportPdf(Request $request)
    {
        return $this->dispatchExport($request, 'pdf', GenerateMessagesPdfJob::class);
    }

    public function exportWord(Request $request)
    {
        return $this->dispatchExport($request, 'word', GenerateMessagesWordJob::class);
    }

    protected function dispatchExport(Request $request, string $format, string $jobClass): RedirectResponse
    {
        $options = $this->buildExportOptions($request, $format);
        $this->assertExportLimit($format, $options);
        $this->recordExportAudit($format, $options);
        $jobClass::dispatch(auth()->id(), $options);

        return back()->with('success', __('global.download_in_queue'));
    }

    protected function assertExportLimit(string $format, array $options): void
    {
        if (\App\Support\FeatureGate::allows('export_unlimited_rows', auth()->user())
            && config('features.features.export_unlimited_rows') !== null) {
            return;
        }

        $limit = \App\Models\Setting::getExportLimit('messages', $format);
        if ($limit === 0) return;

        $count = $this->countForExport($options);
        if ($count > $limit) {
            abort(422, __('messages.export_limit_exceeded', [
                'count'  => number_format($count),
                'limit'  => number_format($limit),
                'format' => strtoupper($format),
            ]));
        }
    }

    protected function countForExport(array $options): int
    {
        $scope = $options['scope'] ?? 'filtered';

        if ($scope === 'selected') {
            return count($options['selected_ids'] ?? []);
        }
        if ($scope === 'all') {
            return Message::query()->count();
        }
        $fakeReq = new Request($options['filters'] ?? []);
        return Message::query()->filter($fakeReq)->count();
    }

    protected function buildExportOptions(Request $request, string $format): array
    {
        $allowedColumns = [
            'id', 'subject', 'audience_type', 'audience_id', 'allow_replies',
            'is_active', 'status', 'published_at', 'expires_at',
            'slug', 'created_at', 'updated_at', 'creator',
        ];

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
            'title'                   => $data['title']                   ?? __('messages.export_title'),
            'include_filters_summary' => $data['include_filters_summary'] ?? true,
            'filters'                 => $data['filters']                 ?? [],
            'orientation'             => $data['orientation']             ?? 'portrait',
            'paper_size'              => $data['paper_size']              ?? 'a4',
            'autofilter'              => $data['autofilter']              ?? true,
            'freeze_header'           => $data['freeze_header']           ?? true,
        ];
    }

    protected function recordExportAudit(string $format, array $options): void
    {
        AuditLog::create([
            'user_id'        => auth()->id(),
            'event'          => 'export_queued',
            'auditable_type' => Message::class,
            'auditable_id'   => null,
            'module'         => 'messages',
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
            'url'        => route('communication.messages.index'),
            'ip_address' => request()?->ip(),
            'user_agent' => substr((string) request()?->userAgent(), 0, 500),
            'created_at' => now(),
        ]);
    }

    // ─── Imports ─────────────────────────────────────────────────────────────

    public function importTemplate()
    {
        return \Maatwebsite\Excel\Facades\Excel::download(
            new \App\Exports\Communication\Messages\MessagesImportTemplate(),
            __('messages.import_template_filename')
        );
    }

    public function import(ImportMessageRequest $request)
    {
        $data   = $request->validated();
        $mode   = $data['mode'] ?? 'update_or_create';
        $dryRun = filter_var($data['dry_run'] ?? false, FILTER_VALIDATE_BOOLEAN);

        $importer = new \App\Imports\Communication\Messages\MessagesImport(
            mode:   $mode,
            dryRun: $dryRun,
        );

        try {
            \Maatwebsite\Excel\Facades\Excel::import($importer, $data['file']);
        } catch (\Throwable $e) {
            \Log::error('MessagesImport failed', [
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

    // ─── Helpers ─────────────────────────────────────────────────────────────

    protected function payload(Message $m, bool $withAudit = false): array
    {
        $base = [
            'id'             => $m->id,
            'slug'           => $m->slug,
            'subject'        => $m->subject,
            'audience_type'  => $m->audience_type,
            'audience_id'    => $m->audience_id,
            'allow_replies'  => $m->allow_replies,
            'is_active'      => $m->is_active,
            'published_at'   => $m->published_at,
            'expires_at'     => $m->expires_at,
            'is_favorite'    => (bool) ($m->is_favorite ?? false),
            'created_at'     => $m->created_at,
            'updated_at'     => $m->updated_at,
            'deleted_at'     => $m->deleted_at,
        ];
        if ($withAudit) {
            $base['deleted_description'] = $m->deleted_description;
            $base['creator'] = $m->creator ? ['id' => $m->creator->id, 'name' => $m->creator->name, 'email' => $m->creator->email, 'photo_url' => $m->creator->photo_url ?? null] : null;
            $base['deleter'] = $m->deleter ? ['id' => $m->deleter->id, 'name' => $m->deleter->name, 'email' => $m->deleter->email] : null;
        }
        return $base;
    }

    protected function audienceOptions(): array
    {
        return collect(Message::AUDIENCES)
            ->map(fn ($t) => ['value' => $t, 'label' => __('messages.audience_' . $t)])
            ->all();
    }

    protected function loadTenants(): array
    {
        return Tenant::query()
            ->withoutGlobalScopes()
            ->where('is_active', true)
            ->orderBy('name')
            ->limit(500)
            ->get(['id', 'name', 'slug'])
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'slug' => $t->slug])
            ->all();
    }

    protected function loadUsers(): array
    {
        return User::query()
            ->withoutGlobalScopes()
            ->whereNull('deleted_at')
            ->where('is_active', true)
            ->whereDoesntHave('roles', fn ($q) => $q->where('name', 'api'))
            ->orderBy('name')
            ->limit(1000)
            ->get(['id', 'name', 'email', 'tenant_id'])
            ->map(fn ($u) => [
                'id'        => $u->id,
                'name'      => $u->name,
                'email'     => $u->email,
                'tenant_id' => $u->tenant_id,
            ])
            ->all();
    }

    protected function resolveAudienceName(Message $message): ?string
    {
        if ($message->audience_type === Message::AUDIENCE_GLOBAL) {
            return __('messages.audience_global');
        }
        if ($message->audience_type === Message::AUDIENCE_TENANT && $message->audience_id) {
            $t = Tenant::withoutGlobalScopes()->find($message->audience_id);
            return $t?->name;
        }
        if ($message->audience_type === Message::AUDIENCE_USER && $message->audience_id) {
            $u = User::withoutGlobalScopes()->find($message->audience_id);
            return $u ? "{$u->name} <{$u->email}>" : null;
        }
        return null;
    }
}
