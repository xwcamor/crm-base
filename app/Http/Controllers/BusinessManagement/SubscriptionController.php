<?php

namespace App\Http\Controllers\BusinessManagement;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use Illuminate\Http\Request;

class SubscriptionController extends Controller
{
    public function index(Request $request)
    {
        $perPage = (int) $request->get('per_page', 25);
        $q = Subscription::query()
            ->with(['tenant:id,name'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->status))
            ->orderBy('starts_at', 'desc');
        return inertia('Subscriptions/Index', [
            'subscriptions' => $q->paginate($perPage)->withQueryString(),
            'filters' => ['status' => $request->get('status', '')],
            'statusOptions' => [
                ['value' => 'trial', 'label' => 'Trial'],
                ['value' => 'active', 'label' => 'Activa'],
                ['value' => 'expired', 'label' => 'Expirada'],
                ['value' => 'cancelled', 'label' => 'Cancelada'],
                ['value' => 'suspended', 'label' => 'Suspendida'],
            ],
        ]);
    }
}
