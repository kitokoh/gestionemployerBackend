<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PlatformSetting extends Model
{
    protected $connection = 'platform';

    protected $table = 'platform_settings';

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
        if (!self::tableExists()) {
            return $default;
        }

        return Cache::rememberForever("platform_setting_{$key}", function () use ($key, $default) {
            $setting = self::query()->where('key', $key)->first();

            if (! $setting) {
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

    private static function tableExists(): bool
    {
        return Schema::connection('platform')->hasTable('platform_settings');
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
