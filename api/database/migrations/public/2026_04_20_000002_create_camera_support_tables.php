<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // Accès tiers ou temporaires
        Schema::connection('platform')->create('camera_access_tokens', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('camera_id')->constrained('cameras')->onDelete('cascade');
            $blueprint->string('token')->unique();
            $blueprint->timestamp('expires_at')->nullable();
            $blueprint->jsonb('metadata')->nullable();
            $blueprint->timestamps();
        });

        // Permissions granulaires pour les employés
        Schema::connection('platform')->create('camera_permissions', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('camera_id')->constrained('cameras')->onDelete('cascade');
            $blueprint->foreignId('employee_id')->constrained('employees')->onDelete('cascade');
            $blueprint->string('permission_level')->default('view'); // view, manage
            $blueprint->timestamps();

            $blueprint->unique(['camera_id', 'employee_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('platform')->dropIfExists('camera_permissions');
        Schema::connection('platform')->dropIfExists('camera_access_tokens');
    }
};
