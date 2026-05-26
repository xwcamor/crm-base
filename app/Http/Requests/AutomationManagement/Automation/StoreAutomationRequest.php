<?php

namespace App\Http\Requests\AutomationManagement\Automation;

use App\Services\Automations\DataSourceRegistry;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validación al crear o editar una automation. Usa la misma forma para
 * ambos (no hay constraints únicos en el shape, así que reusamos).
 *
 * Los catálogos de data_source y action_type vienen del frontend (registries
 * del backend los exponen). Acá validamos que vengan como strings — la
 * existencia real en los registries la chequea el controller.
 */
class StoreAutomationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $isSuper = $this->user()?->hasRole('super') ?? false;

        return [
            // Super DEBE elegir workspace al crear/editar (las automations sin
            // tenant no funcionan: los DataSources filtran por tenant_id y
            // devolverian 0 records). Admin no envia este campo — el trait
            // BelongsToTenant le autoasigna su propio tenant.
            'tenant_id' => $isSuper
                ? ['required', 'integer', Rule::exists('tenants', 'id')->whereNull('deleted_at')]
                : ['nullable'],

            'name'             => ['required', 'string', 'max:150'],
            'description'      => ['nullable', 'string', 'max:1000'],
            'is_active'        => ['sometimes', 'boolean'],

            'trigger_type'     => ['required', 'string', 'in:schedule'],
            'trigger_config'   => ['required', 'array'],
            'trigger_config.kind' => ['required', 'string', 'in:cron,daily,weekly,monthly'],
            'trigger_config.expression' => ['required_if:trigger_config.kind,cron', 'nullable', 'string', 'max:120'],
            'trigger_config.time'       => ['required_unless:trigger_config.kind,cron', 'nullable', 'date_format:H:i'],
            'trigger_config.day'        => ['nullable', 'integer', 'min:0', 'max:31'],

            'data_source'      => [
                'nullable', 'string', 'max:60',
                // Solo data sources permitidos para el rol actual (ej.
                // subscriptions queda fuera para admin/user).
                function ($attribute, $value, $fail) {
                    if (!$value) return;
                    if (!app(DataSourceRegistry::class)->userCanUseKey($value)) {
                        $fail(__('automations.data_source_not_allowed'));
                    }
                },
            ],
            'data_filter'      => ['nullable', 'array'],
            'data_filter.where'         => ['nullable', 'array', 'max:10'],
            'data_filter.where.*.field' => ['required_with:data_filter.where', 'string', 'max:60'],
            'data_filter.where.*.op'    => ['required_with:data_filter.where', 'string', 'in:=,!=,>,<,>=,<=,in,contains'],
            'data_filter.where.*.value' => ['nullable'],
            'data_filter.limit'         => ['nullable', 'integer', 'min:1', 'max:1000'],

            'action_type'      => ['required', 'string', 'in:email,in_app_notification'],
            'action_config'    => ['required', 'array'],
        ];
    }
}
