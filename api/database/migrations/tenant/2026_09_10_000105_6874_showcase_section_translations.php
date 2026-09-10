<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6874 (BC-27 SHOWCASE, V-I18N) — contenu multilingue des sections :
 * colonne `translations` sur `company_showcase_sections`.
 *
 * `content` reste le contenu de référence (locale par défaut `fr`, schéma
 * complet validé) ; `translations` porte les surcouches partielles par locale
 * (`{ "en": {...}, "ar": {...}, "tr": {...} }`), chacune validée contre le
 * MÊME JSON Schema de section (mode partiel : les champs non traduits
 * retombent sur `content` au rendu). Aucune donnée n'est dupliquée pour les
 * champs non linguistiques (médias, icônes, URLs).
 *
 * Additif + idempotent (garde `schemaHasColumn`, conventions migrations tenant
 * §2.6 / #1613), `down()` complet. Locales supportées : fr/en/ar/tr
 * (cf. ShowcaseSectionSchemaRegistry::SUPPORTED_LOCALES).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('company_showcase_sections')) {
            return;
        }

        if (! schemaHasColumn('company_showcase_sections', 'translations')) {
            Schema::table('company_showcase_sections', function (Blueprint $table): void {
                $table->json('translations')->nullable();
            });

            DB::statement("COMMENT ON COLUMN company_showcase_sections.translations IS 'Surcouches de contenu par locale (fr/en/ar/tr, partiel) validees par le meme JSON Schema de section (V-I18N/#6874).';");
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('company_showcase_sections', 'translations')) {
            Schema::table('company_showcase_sections', function (Blueprint $table): void {
                $table->dropColumn('translations');
            });
        }
    }
};
