<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\SuperAdmin;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformDashboardController extends Controller
{
    public function index(Request $request): View
    {
        /** @var SuperAdmin|null $superAdmin */
        $superAdmin = $request->user('super_admin_web') ?? $request->user('super_admin_api');

        if ($superAdmin) {
            AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.dashboard.index', $request);
        }

        $stats = [
            'total_companies' => Company::count(),
            'active_companies' => Company::where('status', 'active')->count(),
            'trial_companies' => Company::where('status', 'trial')->count(),
            'suspended_companies' => Company::whereIn('status', ['suspended', 'expired'])->count(),
            'total_employees' => 0,
            'mrr' => 0,
        ];

        // Global Employee Count
        try {
            $stats['total_employees'] = DB::table('shared_tenants.employees')->count();
        } catch (\Throwable $e) {
            // Log if needed
        }

        // MRR calculation (Total monthly price of all active companies)
        try {
            $mrrData = DB::connection('public')->selectOne("
                SELECT SUM(p.price_monthly) as total 
                FROM companies c 
                JOIN plans p ON c.plan_id = p.id 
                WHERE c.status = 'active'
            ");
            $stats['mrr'] = $mrrData->total ?? 0;
        } catch (\Throwable $e) {
            $stats['mrr'] = 0;
        }

        $recentCompanies = Company::query()
            ->select('id', 'name', 'city', 'country', 'created_at')
            ->orderBy('created_at', 'desc')
            ->limit(5)
            ->get();

        return view('platform.dashboard', compact('stats', 'recentCompanies'));
    }
}
