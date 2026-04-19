<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\PlatformSetting;
use App\Services\AuditLogger;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformSettingController extends Controller
{
    public function index(Request $request): View
    {
        DB::statement('SET search_path TO public');

        $settings = PlatformSetting::all()->groupBy('category');

        return view('platform.settings.index', compact('settings'));
    }

    public function update(Request $request): RedirectResponse
    {
        DB::statement('SET search_path TO public');

        $data = $request->except('_token', '_method');

        foreach ($data as $key => $value) {
            PlatformSetting::set($key, $value);
        }

        /** @var \App\Models\SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web');
        AuditLogger::log('super_admin', $superAdmin->id, null, 'platform.settings.update_batch', $request, ['keys' => array_keys($data)]);

        return redirect()->back()->with('status', 'Paramètres mis à jour avec succès.');
    }
}
