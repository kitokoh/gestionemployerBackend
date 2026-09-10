<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6875 (BC-27 SHOWCASE, V-RGPD) — Bloc légal éditable de la vitrine.
 *
 * `legal` JSON nullable : mentions légales et politique de confidentialité
 * éditées par le tenant
 * `{ "notice": "...", "privacy": "...", "contact_email": "..." }`.
 * Fallback côté ressource publique : texte par défaut documenté
 * (`ShowcaseLegalDefaults`) — la page publique n'est jamais publiée sans
 * mentions légales. Aucun cookie tiers n'est posé par la vitrine (politique
 * exposée dans le DTO public `cookies`).
 *
 * Additif + idempotent (garde schemaHasColumn, #5431).
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
                $table->json('legal')->nullable()->after('settings');
            });

            DB::statement("COMMENT ON COLUMN company_showcases.legal IS 'Mentions legales + politique de confidentialite du tenant (V-RGPD/#6875).';");
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
