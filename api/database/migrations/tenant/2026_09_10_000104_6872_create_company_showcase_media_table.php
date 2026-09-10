<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6872 (BC-27 SHOWCASE, V-MEDIA) — médias de la vitrine : table
 * `company_showcase_media`.
 *
 * Un média est un fichier stocké via le service de stockage existant
 * (`Storage`, disque `public` — cf. `config/filesystems.php`, pattern
 * CabinetService #1817 / CompanyBrandingController). La base ne stocke JAMAIS
 * de chemin absolu côté client : `disk` + `path` (serveur) et un `uuid` stable
 * public qui sert de clé de référence (`settings.logo_id`,
 * `content.image_id`). Le rendu public passe par une route dédiée qui vérifie
 * l'appartenance tenant AVANT de servir le fichier (cache headers longs).
 *
 * Tenant-scoped, `showcase_id`/`section_id` sans FK (conventions migrations
 * tenant §2.6 — pattern RestaurantManager #6167). Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('company_showcase_media')) {
            Schema::create('company_showcase_media', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('showcase_id')->nullable();
                $table->unsignedBigInteger('section_id')->nullable();

                // Identifiant public stable (référencé par le contenu/les
                // settings) — jamais un chemin, jamais l'id interne.
                $table->uuid('uuid')->unique('company_showcase_media_uuid_unique');

                // `logo` (variable de marque) ou `image` (visuel de section).
                $table->string('kind', 20)->default('image');

                $table->string('disk', 30)->default('public');
                $table->string('path', 500);
                $table->string('original_name', 255);
                $table->string('mime_type', 120);
                $table->unsignedInteger('size');
                $table->unsignedInteger('width')->nullable();
                $table->unsignedInteger('height')->nullable();
                $table->string('checksum', 64)->nullable();

                $table->timestamps();

                $table->index('company_id', 'company_showcase_media_company_index');
                $table->index(['showcase_id', 'kind'], 'company_showcase_media_showcase_kind_index');
                $table->index('section_id', 'company_showcase_media_section_index');
            });

            DB::statement("COMMENT ON TABLE company_showcase_media IS 'Medias de la vitrine publique (logo/images) - fichier sur disque, reference par uuid stable, jamais de chemin client (BC-27/#6872).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('company_showcase_media');
    }
};
