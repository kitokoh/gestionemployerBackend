<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformSetting extends Model
{
    protected $fillable = [
        'key',
        'value',
        'label',
        'category',
        'type',
    ];

    public $timestamps = true;

    /**
     * Get a setting value by key with caching.
     */
    public static function get(string $key, $default = null)
    {
        if (! self::tableExists()) {
            return $default;
        }

        return Cache::rememberForever("platform_setting_{$key}", function () use ($key, $default) {
            DB::statement('SET search_path TO public');
            $setting = DB::table('platform_settings')->where('key', $key)->first();
            
            if (!$setting) {
                return $default;
            }

            return self::castValue($setting->value, $setting->type);
        });
    }

    /**
     * Update a setting value and clear cache.
     */
    public static function set(string $key, $value): void
    {
        if (! self::tableExists()) {
            return;
        }

        DB::statement('SET search_path TO public');

        self::query()->updateOrCreate(
            ['key' => $key],
            [
                'value' => is_bool($value) ? ($value ? '1' : '0') : (string) $value,
                'label' => str($key)->replace('_', ' ')->headline()->toString(),
                'category' => self::inferCategory($key),
                'type' => self::inferType($value),
            ]
        );

        Cache::forget("platform_setting_{$key}");
    }

    private static function castValue($value, $type)
    {
        return match ($type) {
            'boolean', 'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'integer', 'int' => (int) $value,
            'json', 'array' => json_decode($value, true),
            default => $value,
        };
    }

    public function getTable(): string
    {
        return DB::getDriverName() === 'pgsql' ? 'public.platform_settings' : 'platform_settings';
    }

    private static function tableExists(): bool
    {
        if (DB::getDriverName() !== 'pgsql') {
            return Schema::hasTable('platform_settings');
        }

        $table = DB::selectOne("select to_regclass('public.platform_settings') as table_name");

        return $table?->table_name !== null;
    }

    private static function inferCategory(string $key): string
    {
        return match (true) {
            str($key)->contains('maintenance') => 'security',
            str($key)->contains(['color', 'logo', 'brand']) => 'branding',
            default => 'general',
        };
    }

    private static function inferType(mixed $value): string
    {
        return match (true) {
            is_bool($value) => 'boolean',
            is_int($value) => 'integer',
            is_array($value) => 'json',
            default => 'string',
        };
    }
}
