<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SuperAdmin;
use App\Services\AuditLogger;
use App\Services\CompanyProvisioningService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PlatformCompanyController extends Controller
{
    public function __construct(
        private readonly CompanyProvisioningService $companyProvisioningService,
    ) {}

    public function export(Request $request): StreamedResponse
    {
        DB::statement('SET search_path TO public');

        $fileName = 'leopardo_companies_export_'.date('Y-m-d').'.csv';
        $headers = [
            'Content-type' => 'text/csv',
            'Content-Disposition' => "attachment; filename={$fileName}",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        $columns = ['ID', 'Nom', 'Slug', 'Email', 'Ville', 'Pays', 'Plan', 'Statut', 'Type tenancy', 'Schema', 'Cree le'];

        $callback = function () use ($columns): void {
            $file = fopen('php://output', 'w');
            fputcsv($file, $columns, ';');

            Company::query()
                ->with('plan')
                ->orderByDesc('created_at')
                ->chunk(100, function ($companies) use ($file): void {
                    foreach ($companies as $company) {
                        fputcsv($file, [
                            $company->id,
                            $company->name,
                            $company->slug,
                            $company->email,
                            $company->city,
                            $company->country,
                            $company->plan?->name ?? $company->plan_id,
                            $company->status,
                            $company->tenancy_type,
                            $company->schema_name,
                            $company->created_at?->format('Y-m-d H:i:s'),
                        ], ';');
                    }
                });

            fclose($file);
        };

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web');
        AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.companies.export', $request);

        return response()->stream($callback, 200, $headers);
    }

    public function index(Request $request): View|JsonResponse
    {
        DB::statement('SET search_path TO public');

        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');
        if ($superAdmin) {
            AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.companies.index', $request);
        }

        $companies = Company::query()
            ->with('plan')
            ->when($request->string('q')->isNotEmpty(), function ($query) use ($request): void {
                $term = '%'.$request->string('q')->toString().'%';
                $query->where(function ($subQuery) use ($term): void {
                    $subQuery
                        ->where('name', 'like', $term)
                        ->orWhere('email', 'like', $term)
                        ->orWhere('city', 'like', $term)
                        ->orWhere('slug', 'like', $term);
                });
            })
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        if ($request->expectsJson()) {
            return new JsonResponse([
                'data' => $companies->items(),
                'meta' => [
                    'current_page' => $companies->currentPage(),
                    'per_page' => $companies->perPage(),
                    'total' => $companies->total(),
                ],
            ]);
        }

        return view('platform.companies.index', [
            'companies' => $companies,
        ]);
    }

    public function create(): View
    {
        DB::statement('SET search_path TO public');

        return view('platform.companies.create', [
            'plans' => DB::table('plans')->where('is_active', true)->orderBy('price_monthly')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        DB::statement('SET search_path TO public');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'slug' => ['nullable', 'string', 'max:100', Rule::unique('companies', 'slug')],
            'sector' => ['required', 'string', 'max:100'],
            'country' => ['required', 'string', 'size:2'],
            'city' => ['required', 'string', 'max:100'],
            'address' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:150', Rule::unique('companies', 'email')],
            'phone' => ['nullable', 'string', 'max:30'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'language' => ['nullable', 'string', 'size:2'],
            'timezone' => ['nullable', 'string', 'max:50'],
            'currency' => ['nullable', 'string', 'size:3'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'manager_first_name' => ['required', 'string', 'max:100'],
            'manager_last_name' => ['required', 'string', 'max:100'],
            'manager_email' => ['required', 'email', 'max:150'],
            'manager_phone' => ['nullable', 'string', 'max:30'],
        ]);

        if (DB::getDriverName() === 'pgsql' && DB::table('public.user_lookups')->where('email', $validated['manager_email'])->exists()) {
            return back()
                ->withInput()
                ->withErrors(['manager_email' => 'Cet email est deja utilise par un utilisateur existant.']);
        }

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');

        $result = $this->companyProvisioningService->provisionSharedCompany($validated, $superAdmin);

        AuditLogger::log('super_admin', $superAdmin->id, $result['company']->id, 'platform.companies.store', $request, [
            'manager_email' => $result['manager']->email,
            'plan_id' => $result['company']->plan_id,
        ]);

        if ($request->expectsJson()) {
            return new JsonResponse([
                'data' => [
                    'company' => $result['company'],
                    'manager' => [
                        'id' => $result['manager']->id,
                        'email' => $result['manager']->email,
                        'role' => $result['manager']->role,
                        'manager_role' => $result['manager']->manager_role,
                    ],
                ],
            ], 201);
        }

        return redirect()
            ->route('platform.companies.index')
            ->with('status', 'Societe creee et invitation manager envoyee.');
    }

    public function show(Company $company, Request $request): View
    {
        DB::statement('SET search_path TO public');

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');
        AuditLogger::log('super_admin', $superAdmin->id, $company->id, 'platform.companies.show', $request);

        $managerLookup = DB::getDriverName() === 'pgsql'
            ? DB::table('public.user_lookups')
                ->where('company_id', $company->id)
                ->where('role', 'manager')
                ->first()
            : null;

        $employeesCount = 0;

        try {
            if ($company->tenancy_type === 'shared') {
                DB::statement('SET search_path TO shared_tenants, public');
                $employeesCount = DB::table('shared_tenants.employees')->where('company_id', $company->id)->count();
            } else {
                DB::statement("SET search_path TO {$company->schema_name}, public");
                $employeesCount = DB::table("{$company->schema_name}.employees")->count();
            }
        } catch (\Throwable) {
            $employeesCount = 0;
        }

        DB::statement('SET search_path TO public');

        return view('platform.companies.show', [
            'company' => $company->load('plan'),
            'managerLookup' => $managerLookup,
            'employeesCount' => $employeesCount,
        ]);
    }

    public function edit(Company $company): View
    {
        DB::statement('SET search_path TO public');

        return view('platform.companies.edit', [
            'company' => $company,
            'plans' => DB::table('plans')->orderBy('price_monthly')->get(),
        ]);
    }

    public function update(Request $request, Company $company): RedirectResponse
    {
        DB::statement('SET search_path TO public');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'plan_id' => ['required', 'integer', Rule::exists('plans', 'id')],
            'status' => ['required', 'string', Rule::in(['trial', 'active', 'suspended', 'expired'])],
        ]);

        $company->update($validated);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');
        AuditLogger::log('super_admin', $superAdmin->id, $company->id, 'platform.companies.update', $request, $validated);

        return redirect()->route('platform.companies.show', $company)->with('status', 'Entreprise mise a jour avec succes.');
    }

    public function suspend(Request $request, Company $company): RedirectResponse
    {
        DB::statement('SET search_path TO public');
        $company->update(['status' => 'suspended']);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');
        AuditLogger::log('super_admin', $superAdmin->id, $company->id, 'platform.companies.suspend', $request);

        return redirect()->back()->with('status', 'Entreprise suspendue. Les acces sont desormais bloques.');
    }

    public function reactivate(Request $request, Company $company): RedirectResponse
    {
        DB::statement('SET search_path TO public');
        $company->update(['status' => 'active']);

        /** @var SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');
        AuditLogger::log('super_admin', $superAdmin->id, $company->id, 'platform.companies.reactivate', $request);

        return redirect()->back()->with('status', 'Entreprise reactivee. Acces retablis.');
    }
}
