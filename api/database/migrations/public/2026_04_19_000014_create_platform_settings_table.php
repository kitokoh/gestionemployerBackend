<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public bool $withinTransaction = false;

    public function up(): void
    {
        if (Schema::hasTable('platform_settings')) {
            return;
        }

        Schema::create('platform_settings', function (Blueprint $table): void {
            $table->increments('id');
            $table->string('key', 100)->unique();
            $table->text('value')->nullable();
            $table->string('label', 150);
            $table->string('category', 50)->default('general');
            $table->string('type', 20)->default('string');
            $table->timestamps();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("COMMENT ON TABLE platform_settings IS 'Parametres globaux de la plateforme SuperAdmin et du branding SaaS.'");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('platform_settings');
    }
};
