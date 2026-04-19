<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        DB::statement('SET search_path TO public');

        $this->command->info('');
        $this->command->info('LEOPARDO RH — Initialisation de la base de donnees');
        $this->command->info(str_repeat('=', 60));

        $this->call([
            PlanSeeder::class,
            LanguageSeeder::class,
            HrModelSeeder::class,
            SuperAdminSeeder::class,
            PlatformSettingsSeeder::class,
        ]);

        $this->command->info('');
        $this->command->info(str_repeat('=', 60));
        $this->command->info('Base de donnees initialisee avec succes.');
        $this->command->info('');
        $this->command->info('Prochaines etapes :');
        $this->command->info('  1. Configurer Nginx');
        $this->command->info('  2. Configurer Supervisor');
        $this->command->info('  3. php artisan horizon:start');
        $this->command->info('  4. Tester : GET /api/health');

        if (app()->environment('local', 'development')) {
            $this->command->info('');
            $this->command->info('Environnement local detecte');
            $this->command->info('Pour creer une company de demo avec des donnees :');
            $this->command->info('php artisan db:seed --class=DemoCompanySeeder');
        }
    }
}
