<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6875 (BC-27 SHOWCASE, V-RGPD) — bloc légal éditable par le tenant.
 *
 * Ajoute `legal` (JSON) sur `company_showcases` : mentions légales, politique
 * de confidentialité et e-mail de contact public (scalaires, allowlist
 * applicative `UpdateShowcaseSettingsAction`). Une vitrine publiée sans bloc
 * édité expose des textes génériques (repli i18n côté DTO public) — la page
 * publique n'est jamais servie sans mentions légales.
 *
 * Additif + idempotent (garde `schemaHasColumn`), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('company_showcases')) {
            return;
        }

        if (! schemaHasColumn('company_showcases', 'legal')) {
            Schema::table('company_showcases', function (Blueprint $table): void {
                $table->json('legal')->nullable();
            });

            DB::statement("COMMENT ON COLUMN company_showcases.legal IS 'Bloc legal editable (mentions + confidentialite + contact) - RGPD/#6875.';");
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('company_showcases', 'legal')) {
            Schema::table('company_showcases', function (Blueprint $table): void {
                $table->dropColumn('legal');
            });
        }
    }
};
