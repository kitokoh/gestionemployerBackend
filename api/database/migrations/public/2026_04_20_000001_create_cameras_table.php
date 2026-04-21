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
        Schema::connection('platform')->create('cameras', function (Blueprint $blueprint) {
            $blueprint->id();
            $blueprint->foreignId('company_id')->constrained('companies')->onDelete('cascade');
            $blueprint->string('name');
            $blueprint->text('rtsp_url'); // Sera chiffré au niveau du modèle
            $blueprint->string('location')->nullable();
            $blueprint->boolean('is_active')->default(true);
            $blueprint->unsignedBigInteger('created_by')->nullable();
            $blueprint->jsonb('metadata')->nullable();
            $blueprint->timestamps();

            $blueprint->index('company_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::connection('platform')->dropIfExists('cameras');
    }
};
