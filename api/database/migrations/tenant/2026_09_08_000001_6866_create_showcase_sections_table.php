<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6866 (BC-27 SHOWCASE) - Sections typées de la vitrine tenant.
 *
 * - `showcase_id` : vitrine propriétaire (company_showcases.id) — sans FK
 *   (conventions migrations tenant §2.6, pattern company_showcases #6865) ;
 * - `type` : type de section v1 (hero|features|products|gallery|
 *   testimonials|contact|footer — enum PHP ShowcaseSectionType) ;
 * - `schema_version` : version du contrat JSON Schema ayant validé le
 *   contenu (ShowcaseSectionSchemas::VERSION, #6866) ;
 * - `content` : JSON validé à l'écriture (clés inconnues refusées,
 *   longueurs/tailles bornées) ;
 * - `position` : ordre d'affichage dans la page, unique par vitrine
 *   (réordonnancement bulk, spec §6).
 *
 * Tenant-scoped (company_id), idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('showcase_sections')) {
            Schema::create('showcase_sections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('showcase_id');

                $table->string('type', 30);
                $table->unsignedSmallInteger('schema_version')->default(1);
                $table->json('content');
                $table->unsignedInteger('position')->default(0);

                $table->timestamps();

                $table->index('company_id', 'showcase_sections_company_idx');
                $table->index('showcase_id', 'showcase_sections_showcase_idx');
                $table->unique(['showcase_id', 'position'], 'showcase_sections_position_unique');
            });

            DB::statement("COMMENT ON TABLE showcase_sections IS 'Sections typees ordonnees de la vitrine publique du tenant - contenu JSON valide par JSON Schema versionne par type (BC-27/#6866).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_sections');
    }
};
