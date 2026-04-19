<?php

namespace Database\Seeders;

use App\Models\PlatformSetting;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class PlatformSettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        DB::statement('SET search_path TO public');

        $settings = [
            [
                'key' => 'platform_name',
                'value' => 'Leopardo RH',
                'label' => 'Nom de la plateforme',
                'category' => 'general',
                'type' => 'string',
            ],
            [
                'key' => 'contact_email',
                'value' => 'support@leopardo.com',
                'label' => 'Email de contact',
                'category' => 'general',
                'type' => 'string',
            ],
            [
                'key' => 'primary_color',
                'value' => '#6366f1',
                'label' => 'Couleur primaire',
                'category' => 'branding',
                'type' => 'string',
            ],
            [
                'key' => 'maintenance_mode',
                'value' => '0',
                'label' => 'Mode Maintenance',
                'category' => 'security',
                'type' => 'boolean',
            ],
            [
                'key' => 'maintenance_message',
                'value' => 'La plateforme est actuellement en maintenance pour mise à jour. Nous revenons très vite.',
                'label' => 'Message de maintenance',
                'category' => 'security',
                'type' => 'string',
            ],
        ];

        foreach ($settings as $setting) {
            PlatformSetting::updateOrCreate(
                ['key' => $setting['key']],
                $setting
            );
        }
    }
}
