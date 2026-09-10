<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Core\Auth\Domain\Models\Employee;
use App\Core\Tenant\Domain\Models\Company;
use App\Events\ShowcaseContactReceived;
use App\Modules\Notification\Infrastructure\Services\CommunicationService;

/**
 * BC-27 SHOWCASE (#6875 V-RGPD) — consomme `showcase.contact_received` :
 * notifie les responsables du tenant (BC-13, canal in-app `app` via
 * CommunicationService — préférences/audit respectés).
 *
 * Exécuté en synchronie dans la requête publique : le contexte tenant est
 * posé par le contrôleur (`TenantManager::withinTenant`) autour du dispatch,
 * donc les lectures tombent dans le schéma du tenant concerné. Le contenu du
 * message n'est jamais journalisé ici (minimisation RGPD) — seul
 * l'identifiant est transmis au canal.
 */
class NotifyTenantOnShowcaseContact
{
    public function __construct(private readonly CommunicationService $communicationService) {}

    public function handle(ShowcaseContactReceived $event): void
    {
        $company = Company::query()->find($event->companyId);

        if (! $company instanceof Company) {
            return;
        }

        $managers = Employee::query()
            ->where('company_id', $company->id)
            ->where('status', 'active')
            ->where('role', 'manager')
            ->whereIn('manager_role', ['principal', 'rh', 'manager'])
            ->limit(20)
            ->get();

        foreach ($managers as $manager) {
            $this->communicationService->notifyEmployee(
                $manager,
                'showcase.contact_received',
                [
                    'category' => 'showcase',
                    'title' => (string) __('showcase.contact_notification_title', ['company' => $company->name]),
                    'body' => (string) __('showcase.contact_notification_body', ['name' => $event->message->name]),
                    'data' => ['message_id' => $event->message->id],
                ],
                ['app']
            );
        }
    }
}
