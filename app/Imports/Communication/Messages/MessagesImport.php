<?php

namespace App\Imports\Communication\Messages;

use App\Models\Message;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;

/**
 * MessagesImport — bulk creacion/update de Messages desde XLSX/CSV.
 *
 * Clon del patron DiscountsImport adaptado a Messages.
 * IMPORTANTE: el body se importa como texto plano (NO HTML rico). El rich
 * editor solo se usa al crear/editar desde la UI.
 */
class MessagesImport implements ToCollection, WithHeadingRow
{
    public int $created = 0;
    public int $updated = 0;
    public int $skipped = 0;

    /** @var array<int, array{row:int, message:string, value?:string}> */
    public array $errors = [];

    public array $preview = [];

    public function __construct(
        protected string $mode = 'update_or_create',
        protected bool $dryRun = false,
    ) {
    }

    public function collection(Collection $rows): void
    {
        DB::beginTransaction();

        try {
            $seenInFileBySubject = [];

            foreach ($rows as $i => $row) {
                $absoluteRow = $i + 2;

                $subject = $this->normalizeString($row['subject'] ?? null);
                if ($subject === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_subject_required'),
                        'value'   => '—',
                    ];
                    continue;
                }
                if (mb_strlen($subject) > 200) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_subject_too_long'),
                        'value'   => mb_substr($subject, 0, 60) . '…',
                    ];
                    continue;
                }

                $body = $this->normalizeText($row['body'] ?? null, 100000);
                if ($body === null) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_body_required'),
                        'value'   => $subject,
                    ];
                    continue;
                }

                $audienceType = $this->normalizeString($row['audience_type'] ?? null) ?? Message::AUDIENCE_GLOBAL;
                if (!in_array($audienceType, Message::AUDIENCES, true)) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_invalid_audience'),
                        'value'   => $audienceType,
                    ];
                    continue;
                }

                $audienceId = $this->normalizeInt($row['audience_id'] ?? null);
                if ($audienceType !== Message::AUDIENCE_GLOBAL && !$audienceId) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('messages.audience_id_required'),
                        'value'   => $subject,
                    ];
                    continue;
                }

                $subjectKey = mb_strtolower($subject);
                if (isset($seenInFileBySubject[$subjectKey])) {
                    $this->errors[] = [
                        'row'     => $absoluteRow,
                        'message' => __('imports.err_duplicate_in_file', ['row' => $seenInFileBySubject[$subjectKey]]),
                        'value'   => $subject,
                    ];
                    continue;
                }
                $seenInFileBySubject[$subjectKey] = $absoluteRow;

                $allowReplies = $this->normalizeBool($row['allow_replies'] ?? null, default: false);
                $isActive     = $this->normalizeBool($row['is_active'] ?? null, default: true);
                $expiresAt    = $this->normalizeDate($row['expires_at'] ?? null);

                $existing = $this->findExistingBySubject($subject);

                $payload = [
                    'body'          => $body,
                    'audience_type' => $audienceType,
                    'audience_id'   => $audienceType === Message::AUDIENCE_GLOBAL ? null : $audienceId,
                    'allow_replies' => $allowReplies,
                    'is_active'     => $isActive,
                    'expires_at'    => $expiresAt,
                ];

                if ($existing) {
                    if ($this->mode === 'create_only') {
                        $this->skipped++;
                        $this->preview[] = ['row' => $absoluteRow, 'subject' => $subject, 'action' => 'skipped'];
                        continue;
                    }

                    $patch = [];
                    foreach ($payload as $k => $v) {
                        if ((string) $existing->{$k} !== (string) $v) {
                            $patch[$k] = $v;
                        }
                    }
                    if (!empty($patch)) {
                        $existing->fill($patch)->save();
                    }

                    $this->updated++;
                    $this->preview[] = ['row' => $absoluteRow, 'subject' => $subject, 'action' => 'updated'];
                } else {
                    Message::create(array_merge($payload, [
                        'subject'    => $subject,
                        'created_by' => Auth::id(),
                    ]));

                    $this->created++;
                    $this->preview[] = ['row' => $absoluteRow, 'subject' => $subject, 'action' => 'created'];
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
            'created'     => $this->created,
            'updated'     => $this->updated,
            'skipped'     => $this->skipped,
            'error_count' => count($this->errors),
            'total_rows'  => $this->created + $this->updated + $this->skipped + count($this->errors),
            'errors'      => array_slice($this->errors, 0, 50),
            'preview'     => array_slice($this->preview, 0, 100),
            'dry_run'     => $this->dryRun,
        ];
    }

    protected function normalizeString(mixed $value): ?string
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        return $s === '' ? null : $s;
    }

    protected function normalizeText(mixed $value, int $maxLen): ?string
    {
        if ($value === null) return null;
        $s = trim((string) $value);
        return $s === '' ? null : mb_substr($s, 0, $maxLen);
    }

    protected function normalizeInt(mixed $value): ?int
    {
        if ($value === null || $value === '') return null;
        return is_numeric($value) ? (int) $value : null;
    }

    protected function normalizeDate(mixed $value): ?string
    {
        $s = $this->normalizeString($value);
        if ($s === null) return null;
        try {
            return \Carbon\Carbon::parse($s)->format('Y-m-d H:i:s');
        } catch (\Throwable) {
            return null;
        }
    }

    protected function findExistingBySubject(string $subject): ?Message
    {
        return Message::query()
            ->whereRaw('LOWER(subject) = LOWER(?)', [$subject])
            ->first();
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
