<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Interfaces\Console\Commands;

use App\Core\Tenant\Domain\Models\Company;
use App\Core\Tenant\TenantManager;
use App\Modules\Catalog\Domain\Models\CatalogInquiry;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

/**
 * BC-28 CATALOG (C-RGPD #6889) — Purge des demandes de devis B2B dont la
 * durée de conservation est échue (`retention_until < aujourd'hui`,
 * conservation bornée 90 j à la création — spec §9).
 *
 * Effacement DÉFINITIF (données acheteur : société, email, message) ; la
 * propagation aux leads CRM BC-11 du même acheteur est faite par
 * l'événement `catalog.inquiry_erased` (listener CRM).
 *
 * Parcours les tenants activant `b2b_catalog` via TenantManager
 * (topologie shared comme dédiée). Options :
 *   --company={slug}  ne traiter qu'un tenant ;
 *   --dry-run         afficher le nombre à purger sans supprimer.
 *
 * Usage ops conseillé : journalisation hebdomadaire
 * (`catalog:purge-expired-inquiries --dry-run` puis exécution).
 */
final class PurgeExpiredCatalogInquiries extends Command
{
    protected $signature = 'catalog:purge-expired-inquiries
        {--company= : slug d un tenant specifique (optionnel)}
        {--dry-run : afficher sans supprimer}';

    protected $description = 'Purge les demandes de devis B2B dont retention_until est depasse (RGPD #6889)';

    public function __construct(private readonly TenantManager $tenants)
    {
        parent::__construct();
    }

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $companySlug = $this->option('company');

        $companies = Company::query()
            ->when(is_string($companySlug) && $companySlug !== '', fn ($q) => $q->where('slug', $companySlug))
            ->orderBy('id')
            ->get();

        $purgedTotal = 0;

        foreach ($companies as $company) {
            if (! $company->hasFeature('b2b_catalog')) {
                continue;
            }

            $purgedTotal += $this->tenants->withinTenant(
                $company,
                fn (): int => $this->purgeTenant($company, $dryRun)
            );
        }

        $this->info(sprintf(
            'Purge terminée : %d demande(s) %s (%d tenant(s) catalogue analysé(s)).',
            $purgedTotal,
            $dryRun ? 'à purger (dry-run)' : 'supprimée(s)',
            $companies->count()
        ));

        return self::SUCCESS;
    }

    private function purgeTenant(Company $company, bool $dryRun): int
    {
        $query = CatalogInquiry::query()
            ->where('retention_until', '<', Carbon::today());

        $count = (int) (clone $query)->count();

        if (! $dryRun) {
            $query->delete();
        }

        if ($count > 0) {
            $this->line(sprintf(
                '  [%s] %d demande(s) %s',
                $company->slug,
                $count,
                $dryRun ? 'expirée(s)' : 'purgee(s)'
            ));
        }

        return $count;
    }
}
