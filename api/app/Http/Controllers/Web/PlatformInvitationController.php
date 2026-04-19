<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\UserInvitation;
use App\Services\AuditLogger;
use App\Services\UserInvitationService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PlatformInvitationController extends Controller
{
    public function __construct(private readonly UserInvitationService $userInvitationService)
    {
    }

    public function index(Request $request): View
    {
        DB::statement('SET search_path TO public');

        $query = UserInvitation::with('company')
            ->whereNull('accepted_at')
            ->latest('created_at');

        $invitations = $query->paginate(20);

        return view('platform.invitations.index', compact('invitations'));
    }

    public function resend(Request $request, UserInvitation $invitation): RedirectResponse
    {
        DB::statement('SET search_path TO public');

        if ($invitation->accepted_at) {
            return redirect()->back()->with('error', 'Cette invitation a deja ete acceptee.');
        }

        /** @var \App\Models\SuperAdmin $superAdmin */
        $superAdmin = $request->user('super_admin_web');
        $this->userInvitationService->resend($invitation, $superAdmin->email);

        AuditLogger::log('super_admin', $superAdmin->id, $invitation->company_id, 'platform.invitations.resend', $request, [
            'email' => $invitation->email,
        ]);

        return redirect()->back()->with('status', "Invitation renvoyee avec succes a {$invitation->email}.");
    }
}
