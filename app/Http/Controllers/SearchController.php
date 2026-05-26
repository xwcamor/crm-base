<?php

namespace App\Http\Controllers;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Deal;
use App\Models\Invoice;
use App\Models\Quote;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * SearchController — busqueda global cross-entidad del top bar.
 *
 * El input del header dispara GET /search?q=Acme. Este controller busca
 * en los 5 modelos principales (Companies, Contacts, Deals, Quotes,
 * Invoices) con LIKE/ILIKE sobre los campos relevantes (name, reference,
 * email, etc.) y devuelve un JSON agrupado por modulo.
 *
 * Scope:
 *   - Tenant: cada modelo usa BelongsToTenant trait, asi que el filtro
 *     por tenant_id es automatico.
 *   - Permisos: solo busca en modulos donde el user tiene <modulo>.view.
 *   - Limite: 5 resultados por modulo (15-25 total). Mas que eso satura
 *     el dropdown — el user puede ir al modulo y filtrar mejor.
 */
class SearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->get('q', ''));

        // Minimo 2 caracteres para empezar a buscar — evita N queries
        // por cada keystroke al escribir.
        if (mb_strlen($q) < 2) {
            return response()->json(['groups' => [], 'q' => $q]);
        }

        $user   = $request->user();
        $isPg   = \DB::connection()->getDriverName() === 'pgsql';
        $likeOp = $isPg ? 'ILIKE' : 'LIKE';
        $needle = '%' . str_replace(['%', '_'], ['\%', '\_'], $q) . '%';

        $groups = [];

        if ($user?->can('companies.view')) {
            $companies = Company::query()
                ->where(function ($w) use ($likeOp, $needle) {
                    $w->where('name', $likeOp, $needle)
                      ->orWhere('legal_name', $likeOp, $needle);
                })
                ->limit(5)
                ->get(['id', 'slug', 'name', 'legal_name']);

            if ($companies->isNotEmpty()) {
                $groups[] = [
                    'module' => 'companies',
                    'label'  => __('sidebar.companies'),
                    'route'  => 'crm.companies.show',
                    'results' => $companies->map(fn ($c) => [
                        'id'       => $c->id,
                        'slug'     => $c->slug,
                        'title'    => $c->name,
                        'subtitle' => $c->legal_name && $c->legal_name !== $c->name ? $c->legal_name : null,
                    ])->all(),
                ];
            }
        }

        if ($user?->can('contacts.view')) {
            $contacts = Contact::query()
                ->where(function ($w) use ($likeOp, $needle) {
                    $w->where('name', $likeOp, $needle)
                      ->orWhere('primary_email', $likeOp, $needle)
                      ->orWhere('first_name', $likeOp, $needle)
                      ->orWhere('last_name', $likeOp, $needle);
                })
                ->limit(5)
                ->get(['id', 'slug', 'name', 'primary_email', 'job_title']);

            if ($contacts->isNotEmpty()) {
                $groups[] = [
                    'module' => 'contacts',
                    'label'  => __('sidebar.contacts'),
                    'route'  => 'crm.contacts.show',
                    'results' => $contacts->map(fn ($c) => [
                        'id'       => $c->id,
                        'slug'     => $c->slug,
                        'title'    => $c->name,
                        'subtitle' => $c->primary_email ?: $c->job_title,
                    ])->all(),
                ];
            }
        }

        if ($user?->can('deals.view')) {
            $deals = Deal::query()
                ->where(function ($w) use ($likeOp, $needle) {
                    $w->where('name', $likeOp, $needle)
                      ->orWhere('reference', $likeOp, $needle);
                })
                ->limit(5)
                ->get(['id', 'slug', 'name', 'reference', 'status', 'value', 'currency_code']);

            if ($deals->isNotEmpty()) {
                $groups[] = [
                    'module' => 'deals',
                    'label'  => __('sidebar.deals'),
                    'route'  => 'crm.deals.show',
                    'results' => $deals->map(fn ($d) => [
                        'id'       => $d->id,
                        'slug'     => $d->slug,
                        'title'    => $d->name,
                        'subtitle' => trim(($d->reference ? $d->reference . ' · ' : '') . ($d->status ?: '')) ?: null,
                    ])->all(),
                ];
            }
        }

        if ($user?->can('quotes.view')) {
            $quotes = Quote::query()
                ->where('reference', $likeOp, $needle)
                ->limit(5)
                ->get(['id', 'slug', 'reference', 'status']);

            if ($quotes->isNotEmpty()) {
                $groups[] = [
                    'module' => 'quotes',
                    'label'  => __('sidebar.quotes'),
                    'route'  => 'business_management.quotes.show',
                    'results' => $quotes->map(fn ($q) => [
                        'id'       => $q->id,
                        'slug'     => $q->slug,
                        'title'    => $q->reference ?: ('#' . $q->id),
                        'subtitle' => $q->status,
                    ])->all(),
                ];
            }
        }

        if ($user?->can('invoices.view')) {
            $invoices = Invoice::query()
                ->where('reference', $likeOp, $needle)
                ->limit(5)
                ->get(['id', 'slug', 'reference', 'status']);

            if ($invoices->isNotEmpty()) {
                $groups[] = [
                    'module' => 'invoices',
                    'label'  => __('sidebar.invoices'),
                    'route'  => 'business_management.invoices.show',
                    'results' => $invoices->map(fn ($i) => [
                        'id'       => $i->id,
                        'slug'     => $i->slug,
                        'title'    => $i->reference ?: ('#' . $i->id),
                        'subtitle' => $i->status,
                    ])->all(),
                ];
            }
        }

        return response()->json([
            'q'      => $q,
            'groups' => $groups,
            'total'  => array_sum(array_map(fn ($g) => count($g['results']), $groups)),
        ]);
    }
}
