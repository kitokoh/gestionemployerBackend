<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6866 (BC-27 SHOWCASE) - Sections de vitrine : table
 * `company_showcase_sections`.
 *
 * - Page publique = liste ORDONNÉE de sections typées (`hero`, `features`,
 *   `gallery`, `testimonials`, `contact`, `footer` — `produits` ajouté avec
 *   le composant BC-28 #6891) ;
 * - `content` JSON validé par type au niveau applicatif (JSON Schema v1 par
 *   type, `ShowcaseSectionSchemaRegistry`) ; `schema_version` versionne le
 *   contrat (migration douce si nouveau type/champ) ;
 * - `showcase_id` référence `company_showcases` SANS FK (conventions
 *   migrations tenant §2.6 — pattern RestaurantManager #6167) ;
 *   l'isolation reste portée par `company_id` (BelongsToCompany).
 *
 * Idempotente + down() complet (conventions #1613 / Render pré-vol #6916).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('company_showcase_sections')) {
            Schema::create('company_showcase_sections', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('showcase_id');
                $table->string('type', 40);
                $table->json('content')->nullable();
                $table->unsignedInteger('sort_order')->default(0);
                $table->unsignedSmallInteger('schema_version')->default(1);
                $table->timestamps();

                $table->index('company_id', 'company_showcase_sections_company_index');
                $table->index(['showcase_id', 'sort_order'], 'company_showcase_sections_showcase_order_index');
            });

            DB::statement("COMMENT ON TABLE company_showcase_sections IS 'Sections ordonnees de la vitrine publique du tenant - content JSON valide par type (JSON Schema v1), schema_version pour migration douce du contrat (BC-27/#6866).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_showcase_sections');
    }
};
