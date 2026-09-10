<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * #6875 (BC-27 SHOWCASE, V-RGPD) — messages du formulaire de contact public.
 *
 * Table tenant `showcase_contact_messages` : minimisation RGPD (nom, e-mail,
 * message, consentement horodaté, empreinte IP hachée, date de rétention
 * bornée) — aucune donnée RH, aucun autre champ interne. Le message est
 * notifié aux responsables du tenant via l'événement `ShowcaseContactReceived`
 * (BC-13) au moment de la soumission.
 *
 * Tenant-scoped, sans FK (colonnes simples + index nommés, conventions
 * migrations tenant §2.6). Idempotente + `down()` complet.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('showcase_contact_messages')) {
            Schema::create('showcase_contact_messages', function (Blueprint $table): void {
                $table->id();
                $table->uuid('company_id');
                $table->unsignedBigInteger('showcase_id');
                $table->string('name', 150);
                $table->string('email', 255);
                $table->text('message');
                $table->timestamp('consent_at');
                $table->date('retention_until');
                $table->string('ip_hash', 64)->nullable();
                $table->timestamps();

                $table->index('company_id', 'showcase_contact_messages_company_index');
                $table->index(['showcase_id', 'created_at'], 'showcase_contact_messages_showcase_created_index');
            });

            DB::statement("COMMENT ON TABLE showcase_contact_messages IS 'Messages du formulaire de contact public d une vitrine - minimisation RGPD, consentement horodate, conservation bornee (BC-27/#6875).';");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('showcase_contact_messages');
    }
};
