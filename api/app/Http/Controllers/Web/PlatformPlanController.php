<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Models\SuperAdmin;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformPlanController extends Controller
{
    public function index(): View
    {
        DB::statement('SET search_path TO public');
        $plans = Plan::all()->sortBy('price_monthly');

        return view('platform.plans.index', compact('plans'));
    }

    public function create(): View
    {
        return view('platform.plans.create');
    }

    public function store(Request $request): RedirectResponse
    {
        DB::statement('SET search_path TO public');

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:plans,name',
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_employees' => 'nullable|integer|min:1',
            'trial_days' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'features' => 'array',
        ]);

        $validated['features'] = $validated['features'] ?? [];
        $validated['is_active'] = $request->has('is_active');

        $plan = Plan::create($validated);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web');
        AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.plans.create', $request, ['plan_id' => $plan->id, 'name' => $plan->name]);

        return redirect()->route('platform.plans.index')->with('status', 'Plan créé avec succès.');
    }

    public function edit(Plan $plan): View
    {
        return view('platform.plans.edit', compact('plan'));
    }

    public function update(Request $request, Plan $plan): RedirectResponse
    {
        DB::statement('SET search_path TO public');

        $validated = $request->validate([
            'name' => 'required|string|max:50|unique:plans,name,'.$plan->id,
            'price_monthly' => 'required|numeric|min:0',
            'price_yearly' => 'required|numeric|min:0',
            'max_employees' => 'nullable|integer|min:1',
            'trial_days' => 'required|integer|min:0',
            'is_active' => 'boolean',
            'features' => 'array',
        ]);

        $validated['features'] = $validated['features'] ?? [];
        $validated['is_active'] = $request->has('is_active');

        $plan->update($validated);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web');
        AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.plans.update', $request, ['plan_id' => $plan->id]);

        return redirect()->route('platform.plans.index')->with('status', 'Plan mis à jour.');
    }
}
