<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6871 (BC-27 SHOWCASE, V-PUBLISH) — workflow de publication : jeton
 * d'aperçu privé (`preview_token`) sur `company_showcases`.
 *
 * Le statut `draft|published` et `published_at` existent déjà (#6865) ; ce lot
 * ajoute le jeton d'aperçu qui permet au responsable de consulter un brouillon
 * via la route publique (`GET /public/vitrine/{slug}?token=...`, réponse
 * `X-Robots-Tag: noindex`) sans le publier. Le jeton est révoqué à la
 * publication et n'est JAMAIS exposé par l'API publique.
 *
 * Additif + idempotent (garde `schemaHasColumn`, conventions migrations tenant
 * §2.6 / #1613), `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('company_showcases')) {
            return;
        }

        if (! schemaHasColumn('company_showcases', 'preview_token')) {
            Schema::table('company_showcases', function (Blueprint $table): void {
                $table->string('preview_token', 64)->nullable();
            });

            DB::statement("COMMENT ON COLUMN company_showcases.preview_token IS 'Jeton d apercu prive d un brouillon (V-PUBLISH/#6871) - jamais expose en public.';");
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('company_showcases', 'preview_token')) {
            Schema::table('company_showcases', function (Blueprint $table): void {
                $table->dropColumn('preview_token');
            });
        }
    }
};
