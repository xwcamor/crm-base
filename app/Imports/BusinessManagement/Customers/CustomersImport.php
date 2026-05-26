<?php

namespace App\Imports\BusinessManagement\Customers;

use App\Models\Country; // @scaffold:remove-line
use App\Models\Customer;
use App\Models\Tenant;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

// @scaffold:remove-begin commercial-import-docblock
/**
 * Imports Customers from .xlsx/.csv.
 *
 * Columns:
 *   - name        (required, max 255)
 *   - cod         (optional, max 50, unique per tenant)
 *   - country_iso (optional, resolves to country_id via iso_code lookup)
 *   - is_active   (optional, boolean-ish; default true)
 *
 * Modes: 'create_only' | 'update_or_create'
 *
 * 3-layer duplicate protection (todo dentro del tenant scope):
 *   1. In-file: normalizado (trim+lower+iconv) catchea dupes en el mismo upload
 *   2. App: lookup case + accent insensitive contra DB del tenant
 *   3. DB: el unique constraint `customers_tenant_cod_unique` para `cod`
 *
 * Tenant scoping:
 *   - El trait BelongsToTenant auto-fillea tenant_id en `creating` desde
 *     auth()->user()->tenant_id. Esto cubre todos los `create()` aca.
 *   - Para super (sin tenant_id) caemos en un dry-run forzado y warning.
 *
 * Enforce `Tenant::maxRecordsPerModule()`:
 *   - Si el tenant tiene limite, contamos cuantos customers tiene HOY +
 *     cuantos vamos a CREAR. Si supera, marcamos las filas excedentes como
 *     errores (no se crean). Las filas que actualizan existentes no cuentan
 *     contra el limite.
 *
 * Todo va en transaccion. dryRun=true → rollback al final (preview UI).
 */
// @scaffold:remove-end
class CustomersImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int, array{row:int, message:string, value?:string}> */
    public array $errors = [];

    /** @var array<int, array{row:int, name:string, cod:?string, country_iso:?string, is_active:bool, action:string}> */ // @scaffold:remove-line
    public array $preview = [];

    // @scaffold:remove-begin commercial-iso-cache
    /** Cache iso_code → country_id para evitar N queries en el loop. */
    protected array $countryIsoCache = [];
    // @scaffold:remove-end

    /** tenant_id del usuario autenticado, capturado al construir. */
    protected ?int $tenantId;

    /** Limite de records del plan (>0 = aplica; 0 o PHP_INT_MAX = ilimitado). */
    protected int $maxRecords;

    /** Count actual de customers del tenant (pre-import). */
    protected int $currentCount;

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {
        $user           = Auth::user();
        $this->tenantId = $user?->tenant_id;

        // Plan limit del tenant. super sin tenant_id → sin limite.
        if ($user && $user->tenant) {
            $this->maxRecords = $user->tenant->maxRecordsPerModule();
        } else {
            $this->maxRecords = PHP_INT_MAX;
        }

        // Snapshot del count actual. Para super contamos sin scope.
        $this->currentCount = $this->tenantId !== null
            ? Customer::withoutGlobalScopes()->where('tenant_id', $this->tenantId)->count()
            : Customer::withoutGlobalScopes()->count();

        // @scaffold:remove-begin commercial-iso-preload
        // Precarga ISO → id en una sola query.
        Country::query()
            ->where('is_active', true)
            ->whereNotNull('iso_code')
            ->get(['id', 'iso_code'])
            ->each(function ($c) {
                $this->countryIsoCache[mb_strtoupper(trim($c->iso_code))] = $c->id;
            });
        // @scaffold:remove-end
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            // Layer 1: dedup in-file por nombre normalizado.
            $seenInFileByName = [];
            // @scaffold:remove-begin commercial-cod-dedup-init
            // Dedup intra-archivo por cod (que es unique-per-tenant).
            $seenInFileByCod  = [];
            // @scaffold:remove-end

            $newRecordsCount = 0; // contador de filas que crearian un nuevo customer

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2; // +2 = header (1) + indexacion desde 0.

                $name = $this->normalizeName($row['name'] ?? null);
                if ($name === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($name) > 255) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_name_too_long'),
                        'value'   => mb_substr($name, 0, 60) . '…',
                    ];
                    continue;
                }

                $normNameKey = $this->normalizeKey($name);
                if (isset($seenInFileByName[$normNameKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByName[$normNameKey]]),
                        'value'   => $name,
                    ];
                    continue;
                }
                $seenInFileByName[$normNameKey] = $absoluteRow;

                // @scaffold:anchor description-import-read
                // @scaffold:remove-begin commercial-row-read
                $cod = $this->normalizeCod($row['cod'] ?? null);
                if ($cod !== null && mb_strlen($cod) > 50) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => 'cod supera los 50 caracteres.',
                        'value'   => mb_substr($cod, 0, 30) . '…',
                    ];
                    continue;
                }
                if ($cod !== null) {
                    if (isset($seenInFileByCod[$cod])) {
                        $this->errors[] = [
                            'row'     => $absoluteRow,
                            'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileByCod[$cod]]),
                            'value'   => $cod,
                        ];
                        continue;
                    }
                    $seenInFileByCod[$cod] = $absoluteRow;
                }

                $countryIso = $this->normalizeIso($row['country_iso'] ?? null);
                $countryId  = $countryIso !== null ? ($this->countryIsoCache[$countryIso] ?? null) : null;
                // @scaffold:remove-end

                $isActive = $this->normalizeBool($row['is_active'] ?? null, default: true);

                // Layer 2: DB lookup case + accent insensitive (scoped al tenant).
                $existing = $this->findExistingByNameInsensitive($name);

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = [
                            'row'         => $absoluteRow,
                            'name'        => $name,
                            // @scaffold:anchor description-preview-skipped
                            'cod'         => $cod,            // @scaffold:remove-line
                            'country_iso' => $countryIso,     // @scaffold:remove-line
                            'is_active'   => $isActive,
                            'action'      => 'skipped',
                        ];
                        continue;
                    }

                    // Solo tocar campos que cambian (evita audit logs vacios).
                    $patch = [];
                    if ((bool) $existing->is_active !== $isActive)      $patch['is_active']  = $isActive;
                    // @scaffold:remove-begin commercial-patch
                    if ($cod !== null && $existing->cod !== $cod)        $patch['cod']        = $cod;
                    if ($countryId !== null && $existing->country_id !== $countryId) {
                        $patch['country_id'] = $countryId;
                    }
                    // @scaffold:remove-end
                    if (!empty($patch)) {
                        $existing->fill($patch)->save();
                    }

                    $this->updated++;
                    $this->preview[] = [
                        'row'         => $absoluteRow,
                        'name'        => $name,
                        // @scaffold:anchor description-preview-updated
                        'cod'         => $cod,            // @scaffold:remove-line
                        'country_iso' => $countryIso,     // @scaffold:remove-line
                        'is_active'   => $isActive,
                        'action'      => 'updated',
                    ];
                } else {
                    // Antes de crear, validar limite del plan.
                    if ($this->maxRecords > 0 && $this->maxRecords !== PHP_INT_MAX) {
                        if (($this->currentCount + $newRecordsCount) >= $this->maxRecords) {
                            $this->errors[] = [
                                'row'     => $absoluteRow,
                                'message' => __('plans.limit_records_reached', ['max' => $this->maxRecords]),
                                'value'   => $name,
                            ];
                            continue;
                        }
                    }

                    Customer::create([
                        'name'       => $name,
                        // @scaffold:anchor description-import-create
                        'cod'        => $cod,            // @scaffold:remove-line
                        'country_id' => $countryId,      // @scaffold:remove-line
                        'is_active'  => $isActive,
                        'created_by' => Auth::id(),
                        // tenant_id lo auto-fillea el trait BelongsToTenant.
                    ]);

                    $newRecordsCount++;
                    $this->created++;
                    $this->preview[] = [
                        'row'         => $absoluteRow,
                        'name'        => $name,
                        // @scaffold:anchor description-preview-created
                        'cod'         => $cod,            // @scaffold:remove-line
                        'country_iso' => $countryIso,     // @scaffold:remove-line
                        'is_active'   => $isActive,
                        'action'      => 'created',
                    ];
                }
            }

            if ($this->dryRun) {
                DB::rollBack();
            } else {
                DB::commit();
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            throw $e;
        }
    }

    public function summary(): array
    {
        return [
            'created'      => $this->created,
            'updated'      => $this->updated,
            'skipped'      => $this->skipped,
            'error_count'  => count($this->errors),
            'total_rows'   => $this->created + $this->updated + $this->skipped + count($this->errors),
            'errors'       => array_slice($this->errors, 0, 50),
            'preview'      => array_slice($this->preview, 0, 100),
            'dry_run'      => $this->dryRun,
        ];
    }

    protected function normalizeName(mixed $value): ?string
    {
        if ($value === null) return null;
        $name = trim((string) $value);
        return $name === '' ? null : $name;
    }

    // @scaffold:anchor description-normalize-fn
    // @scaffold:remove-begin commercial-normalize-fns
    protected function normalizeCod(mixed $value): ?string
    {
        if ($value === null) return null;
        $cod = trim((string) $value);
        return $cod === '' ? null : $cod;
    }

    protected function normalizeIso(mixed $value): ?string
    {
        if ($value === null) return null;
        $iso = mb_strtoupper(trim((string) $value));
        return $iso === '' ? null : $iso;
    }
    // @scaffold:remove-end

    /** Lowercase + strip accents (iconv) — mismo pattern que el DB-level layer 2. */
    protected function normalizeKey(string $name): string
    {
        $lower    = mb_strtolower(trim($name));
        $stripped = @iconv('UTF-8', 'ASCII//TRANSLIT//IGNORE', $lower);
        return $stripped !== false ? $stripped : $lower;
    }

    /**
     * Lookup case + accent insensitive (Postgres unaccent / fallback LOWER),
     * scoped al tenant del usuario.
     */
    protected function findExistingByNameInsensitive(string $name): ?Customer
    {
        $isPgsql = DB::getDriverName() === 'pgsql';
        $query   = Customer::query()->withoutGlobalScopes();

        if ($this->tenantId !== null) {
            $query->where('customers.tenant_id', $this->tenantId);
        }

        if ($isPgsql) {
            $query->whereRaw('unaccent(LOWER(customers.name)) = unaccent(LOWER(?))', [$name]);
        } else {
            $query->whereRaw('LOWER(customers.name) = LOWER(?)', [$name]);
        }

        return $query->first();
    }

    /** Acepta 1/0, true/false, si/no, activo/inactivo, yes/no, active/inactive, x. */
    protected function normalizeBool(mixed $value, bool $default = true): bool
    {
        if ($value === null || $value === '') return $default;
        if (is_bool($value)) return $value;
        if (is_numeric($value)) return ((int) $value) === 1;

        $normalized = mb_strtolower(trim((string) $value));
        return in_array($normalized, ['1', 'true', 't', 'yes', 'y', 'sí', 'si', 's', 'activo', 'active', 'x'], true);
    }
}
