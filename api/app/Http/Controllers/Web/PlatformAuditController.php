<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformAuditController extends Controller
{
    public function index(Request $request): View
    {
        DB::statement('SET search_path TO public');

        $query = AuditLog::query()->latest('created_at');

        if ($request->filled('action')) {
            $query->where('action', 'like', '%' . $request->action . '%');
        }

        if ($request->filled('actor_type')) {
            $query->where('actor_type', $request->actor_type);
        }

        $logs = $query->paginate(30)->withQueryString();

        return view('platform.audit.index', compact('logs'));
    }
}
