<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6874 (BC-27 SHOWCASE, V-I18N) — Contenu de section multilingue.
 *
 * `content` reste le contenu de la langue par défaut (fr) ; `content_i18n`
 * porte les surcharges par locale `{ "en": {...}, "ar": {...} }` validées
 * contre le même JSON Schema que `content` (ShowcaseSectionSchemaValidator).
 * La résolution au rendu public choisit la surcharge correspondant à la
 * langue demandée (Accept-Language ou `?lang=`), sinon retombe sur `content`.
 *
 * Additif + idempotent (garde schemaHasColumn, #5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('company_showcase_sections')) {
            return;
        }

        if (! schemaHasColumn('company_showcase_sections', 'content_i18n')) {
            Schema::table('company_showcase_sections', function (Blueprint $table): void {
                $table->json('content_i18n')->nullable()->after('content');
            });

            DB::statement("COMMENT ON COLUMN company_showcase_sections.content_i18n IS 'Surcharges de contenu par locale (fr/en/ar/tr), meme contrat JSON Schema que content (V-I18N/#6874).';");
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('company_showcase_sections', 'content_i18n')) {
            Schema::table('company_showcase_sections', function (Blueprint $table): void {
                $table->dropColumn('content_i18n');
            });
        }
    }
};
