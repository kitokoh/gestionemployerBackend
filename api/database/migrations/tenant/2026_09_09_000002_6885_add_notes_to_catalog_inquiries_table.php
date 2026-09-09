<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6885 (BC-28 CATALOG, C-BACKOFFICE) — Notes internes sur les demandes de
 * devis B2B (back-office tenant : suivi « nouveau → contacté → devis envoyé
 * → clos/perdu »). Additif + idempotent (garde schemaHasColumn, #5431).
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('catalog_inquiries')) {
            return;
        }

        if (! schemaHasColumn('catalog_inquiries', 'notes')) {
            Schema::table('catalog_inquiries', function (Blueprint $table): void {
                $table->text('notes')->nullable()->after('message');
            });

            DB::statement("COMMENT ON COLUMN catalog_inquiries.notes IS 'Notes internes du back-office tenant (C-BACKOFFICE/#6885).';");
        }
    }

    public function down(): void
    {
        if (schemaHasColumn('catalog_inquiries', 'notes')) {
            Schema::table('catalog_inquiries', function (Blueprint $table): void {
                $table->dropColumn('notes');
            });
        }
    }
};
