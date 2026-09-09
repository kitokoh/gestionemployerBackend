<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A5 (#6852, BC-23) — journal d'exécution des outils « via assistant IA ».
 *
 * `ai_tool_executions` rend rejouable la chaîne conversation → action
 * proposée → confirmation → exécution → résultat (exigence EPIC #6846) :
 * chaque outil exécuté (lecture), proposé (écriture sensible en attente de
 * confirmation) ou confirmé/refusé par l'humain est tracé avec le contexte
 * de corrélation (conversation_id, pending_action_id), les arguments
 * SANITISÉS (PII masquées, A6 #6853), l'issue (stage + succès) et un résumé
 * — le tout marqué `source = 'assistant'`.
 *
 * ⚠ Miroir requis dans api/tests/Support/CreatesMvpSchema.php (bloc module
 * ai_*) — garde check-mvp-schema-parity.sh (#5443) : toute migration tenant
 * récente créant une table doit l'ajouter à la fixture dans la même PR.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! schemaTableExists('ai_tool_executions')) {
            Schema::create('ai_tool_executions', function (Blueprint $table): void {
                $table->bigIncrements('id');
                $table->uuid('company_id')->index();
                $table->unsignedInteger('user_id');
                $table->unsignedBigInteger('conversation_id')->nullable();
                // UUID de l'action en attente (PendingActionStore) — permet de
                // relier la proposition (chat) à sa confirmation/refus.
                $table->string('pending_action_id', 64)->nullable();
                $table->string('tool_name', 100);
                $table->json('tool_input')->nullable();
                // executed | confirmation_required | rejected | error.
                $table->string('stage', 30)->default('executed');
                $table->boolean('success')->default(true);
                $table->text('result_summary')->nullable();
                $table->text('error')->nullable();
                // Marqueur « exécuté via assistant IA » — constante 'assistant'.
                $table->string('source', 20)->default('assistant');
                $table->timestampTz('created_at')->useCurrent();

                $table->index(['company_id', 'created_at']);
                $table->index(['conversation_id']);
                $table->index(['pending_action_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ai_tool_executions');
    }
};
