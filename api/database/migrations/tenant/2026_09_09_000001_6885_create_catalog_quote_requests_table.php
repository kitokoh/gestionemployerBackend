<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6885 (BC-28 CATALOG) — Demandes de devis/contact B2B (back-office).
 *
 * - `catalog_quote_requests` : demande reçue sur le catalogue public
 *   (#6884) — référence UUID, produit (slug + nom, instantané), quantité,
 *   acheteur (société, contact, email, phone), message, statut du workflow
 *   `new|contacted|quote_sent|closed|lost`, notes internes (back-office),
 *   trace consentement RGPD horodatée. Le lead CRM BC-11 (source=catalog)
 *   reste l'intégration commerciale (#6884) — corrélable par `reference`.
 *
 * Tenant-scoped, sans FK (conventions migrations tenant §2.6, pattern
 * #6167/#6880). Idempotente + down() complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('catalog_quote_requests')) {
            Schema::create('catalog_quote_requests', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('reference', 36);
                $table->string('product_slug', 160);
                $table->string('product_name', 150);
                $table->unsignedInteger('quantity')->nullable();
                $table->string('buyer_company', 255);
                $table->string('contact_name', 255);
                $table->string('email', 255);
                $table->string('phone', 40)->nullable();
                $table->text('message')->nullable();
                $table->string('status', 20)->default('new');
                $table->text('internal_notes')->nullable();
                $table->timestamp('consented_at')->nullable();

                $table->timestamps();

                $table->unique('reference', 'catalog_quote_requests_reference_unique');
                $table->index(['company_id', 'status'], 'catalog_quote_requests_company_status_idx');
                $table->index(['company_id', 'created_at'], 'catalog_quote_requests_company_created_idx');
            });

            DB::statement("COMMENT ON TABLE catalog_quote_requests IS 'Demandes de devis/contact B2B recues sur le catalogue public - workflow new|contacted|quote_sent|closed|lost, trace consentement RGPD (BC-28/#6885).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_quote_requests');
    }
};
