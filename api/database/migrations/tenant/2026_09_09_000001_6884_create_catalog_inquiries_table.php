<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6884 (BC-28 CATALOG, C-LEAD) — Demandes de devis/contact B2B reçues via
 * le formulaire public (`POST /public/catalog/{companySlug}/inquiries`).
 *
 * Tenant-scoped (le lead appartient au tenant producteur), RGPD §9 spec :
 * données acheteur minimisées (société, email, message), consentement
 * explicite horodaté (`consent_at`), conservation bornée
 * (`retention_until` — purge C-RGPD #6889), IP hashée (jamais en clair).
 * `product_name` est un instantané au moment de la demande (le produit peut
 * être dépublié/supprimé ensuite — le back-office #6885 reste lisible).
 * Statut whitelisté en application (CatalogInquiryStatus) — v1 n'écrit que
 * `new`. Sans FK (conventions migrations tenant §2.6). Idempotente.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('catalog_inquiries')) {
            Schema::create('catalog_inquiries', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id')->index();

                $table->string('product_slug', 160);
                $table->string('product_name', 150);
                $table->unsignedBigInteger('quantity')->nullable();
                $table->string('company_name', 150);
                $table->string('email', 255);
                $table->text('message')->nullable();
                $table->string('status', 20)->default('new');
                $table->timestamp('consent_at')->nullable();
                $table->date('retention_until')->nullable();
                $table->string('ip_hash', 64)->nullable();

                $table->timestamps();

                $table->index(['company_id', 'status'], 'catalog_inquiries_company_status_idx');
                $table->index(['company_id', 'created_at'], 'catalog_inquiries_company_created_idx');
                $table->index(['product_slug'], 'catalog_inquiries_product_slug_idx');
            });

            DB::statement("COMMENT ON TABLE catalog_inquiries IS 'Demandes de devis B2B publiques - donnees acheteur minimisees, consentement horodate, conservation bornee (BC-28 C-LEAD/#6884).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('catalog_inquiries');
    }
};
